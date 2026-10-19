<?php

namespace App\Http\Controllers;

use App\Services\Assist\AssistChat;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Ask Ventiq's panel on the React pages: the conversation, and asking. */
class AssistController extends Controller
{
    public function __construct(private AssistChat $chat) {}

    public function index(Request $request)
    {
        // Conversations belong to an organization (super admins have none).
        abort_unless($request->user()->organization_id, 403);
        $conversation = $this->chat->conversation($request->user());
        $this->chat->refresh($conversation);

        return response()->json([
            'messages' => $conversation->messages->map(fn ($m) => [
                'id'      => $m->id,
                'role'    => $m->role,
                'status'  => $m->status,
                // Answers are Markdown, written by VENTIQ's own assistant.
                'html'    => $m->role === 'assistant' && $m->content ? Str::markdown($m->content, ['html_input' => 'escape']) : null,
                'text'    => $m->role === 'user' ? $m->content : null,
            ])->values(),
            'pending' => $conversation->messages->contains('status', 'pending'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->organization_id, 403);
        $data = $request->validate(['text' => ['required', 'string', 'max:4000']]);
        $this->chat->ask($this->chat->conversation($request->user()), $request->user(), $data['text']);

        return $this->index($request);
    }
}
