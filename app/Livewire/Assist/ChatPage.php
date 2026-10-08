<?php

namespace App\Livewire\Assist;

use App\Models\AssistConversation;
use App\Models\AssistMessage;
use App\Models\AiGenerationResult;
use App\Jobs\GenerateAssistReply;
use Illuminate\Support\Str;
use Livewire\Component;

class ChatPage extends Component
{
    public ?AssistConversation $conversation = null;
    public string $input = '';

    // Embedded on every page (see layouts/app.blade.php), not a route —
    // gated there to org users only, so no superadmin/organization_id guard
    // needed here. Picks up the user's own most recent conversation rather
    // than always starting fresh, since mount() now fires on every page
    // load instead of once per explicit visit to a dedicated page.
    public function mount(): void
    {
        $this->conversation = AssistConversation::where('organization_id', auth()->user()->organization_id)
            ->where('user_id', auth()->id())
            ->latest()
            ->first() ?? AssistConversation::create([
                'organization_id' => auth()->user()->organization_id,
                'user_id'         => auth()->id(),
            ]);
    }

    public function getPollingIntervalProperty(): ?string
    {
        // Only poll while an assistant message is pending — same idea as extraction_job_id
        return $this->conversation->messages()->where('status', 'pending')->exists() ? '2s' : null;
    }

    public function sendMessage(): void
    {
        $this->validate(['input' => 'required|string|max:4000']);

        // 1. Save the user's turn immediately — appears instantly, no wait
        AssistMessage::create([
            'conversation_id' => $this->conversation->id,
            'role'            => 'user',
            'content'         => $this->input,
            'status'          => 'completed',
        ]);

        if (!$this->conversation->title) {
            $this->conversation->update(['title' => Str::limit($this->input, 40)]);
        }

        // 2. Create the "thinking" placeholder — this IS the fake-streaming trick
        $jobId = (string) Str::uuid();
        $assistantMessage = AssistMessage::create([
            'conversation_id' => $this->conversation->id,
            'role'            => 'assistant',
            'content'         => null,
            'status'          => 'pending',
            'job_id'          => $jobId,
        ]);

        AiGenerationResult::create([
            'job_id'  => $jobId,
            'user_id' => auth()->id(),
            'type'    => 'assist_chat',
            'status'  => 'pending',
        ]);

        GenerateAssistReply::dispatch(
            jobId: $jobId,
            messageId: $assistantMessage->id,
            userText: $this->input,
            organizationId: auth()->user()->organization_id,
        );

        $this->input = '';
        $this->dispatch('scroll-to-bottom');
    }

    // How long a message is allowed to sit "pending" before poll() gives up
    // waiting on it and fails it client-side — independent of whether the
    // job itself ever resolves. This is the actual safety net: the job's
    // own timeout/retry settings only protect against a running job that
    // errors out; they do nothing if the worker process dies mid-job or
    // never picks the job up at all. Without this ceiling that leaves the
    // message pending forever — "thinking" dots with no way out.
    private const STALE_AFTER_SECONDS = 45;

    public function poll(): void
    {
        // Pick up any assistant messages whose job finished since last poll
        // (the job writes directly to AssistMessage, so this is mostly a
        // trigger for Livewire to re-render with fresh data — but it's also
        // where a stuck message gets force-resolved.)
        $pending = $this->conversation->messages()->where('status', 'pending')->get();

        foreach ($pending as $msg) {
            $result = AiGenerationResult::where('job_id', $msg->job_id)->first();

            if ($result?->status === 'completed') {
                $msg->update(['content' => $result->result, 'status' => 'completed']);
                continue;
            }

            if ($result?->status === 'failed') {
                $msg->update(['content' => $result->error ?? 'Something went wrong.', 'status' => 'failed']);
                continue;
            }

            if ($msg->created_at->diffInSeconds(now()) >= self::STALE_AFTER_SECONDS) {
                $msg->update([
                    'status'  => 'failed',
                    'content' => "That took longer than expected. Please try asking again.",
                ]);
            }
        }

        $this->conversation->refresh();
    }

    public function render()
    {
        return view('livewire.assist.chat-page', [
            'messages' => $this->conversation->messages,
        ]);
    }
}
