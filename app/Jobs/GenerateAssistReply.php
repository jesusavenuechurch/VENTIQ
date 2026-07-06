<?php

namespace App\Jobs;

use App\Models\AiGenerationResult;
use App\Models\AssistMessage;
use App\Models\Organization;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\AssistChatPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAssistReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 2;
    public int $backoff = 10;

    public function __construct(
        public readonly string $jobId,
        public readonly int    $messageId,
        public readonly string $userText,
        public readonly int    $organizationId,
    ) {}

    public function handle(AIService $ai): void
    {
        AiGenerationResult::where('job_id', $this->jobId)
            ->update(['status' => 'processing']);

        try {
            $prompt = (new AssistChatPrompt())->with([
                'user_text'    => $this->userText,
                'organisation' => Organization::find($this->organizationId)?->name,
            ]);

            $result = $ai->generate($prompt);

            if (!$result->success) {
                $this->markFailed($result->error);
                return;
            }

            AssistMessage::where('id', $this->messageId)->update([
                'content' => $result->content,
                'status'  => 'completed',
            ]);

            AiGenerationResult::where('job_id', $this->jobId)->update([
                'status'   => 'completed',
                'result'   => $result->content,
                'duration' => $result->duration,
            ]);

            Log::info('Assist chat reply generated', [
                'message_id' => $this->messageId,
                'duration'   => $result->duration,
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateAssistReply failed', [
                'job_id'     => $this->jobId,
                'message_id' => $this->messageId,
                'error'      => $e->getMessage(),
            ]);
            $this->markFailed($e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->markFailed($e->getMessage());
    }

    protected function markFailed(string $error): void
    {
        AiGenerationResult::where('job_id', $this->jobId)
            ->update(['status' => 'failed', 'error' => $error]);

        AssistMessage::where('id', $this->messageId)
            ->update(['status' => 'failed', 'content' => $error]);
    }
}