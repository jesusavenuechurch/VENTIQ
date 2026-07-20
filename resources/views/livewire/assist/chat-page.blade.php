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

    public function mount(?int $conversation = null): void
    {
        $this->conversation = $conversation
            ? AssistConversation::where('organization_id', auth()->user()->organization_id)
                ->findOrFail($conversation)
            : AssistConversation::create([
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

    public function poll(): void
    {
        // Pick up any assistant messages whose job finished since last poll
        $pending = $this->conversation->messages()->where('status', 'pending')->get();

        foreach ($pending as $msg) {
            $result = AiGenerationResult::where('job_id', $msg->job_id)->first();
            if (!$result) continue;

            if ($result->status === 'completed') {
                $msg->update(['content' => $result->result, 'status' => 'completed']);
            } elseif ($result->status === 'failed') {
                $msg->update(['content' => $result->error ?? 'Something went wrong.', 'status' => 'failed']);
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