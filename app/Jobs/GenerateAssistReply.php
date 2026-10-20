<?php

namespace App\Jobs;

use App\Models\AiGenerationResult;
use App\Models\AssistMessage;
use App\Models\Organization;
use App\Services\Memory\AskVentiq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * An Ask Ventiq answer, worked out in the background: the organization's
 * own records found for the question, put into words by the model on the
 * Mac mini (App\Services\Memory\AskVentiq). The organization searched is
 * always the one the question was asked in.
 */
class GenerateAssistReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // The Mac mini answers in well under a minute, but over Tailscale and
    // with a long question give it room; no retry, so a failure shows once.
    public int $timeout = 240;
    public int $tries   = 1;

    private const UNAVAILABLE_MESSAGE = "Ventiq Assist couldn't reach its server just now. Please try again in a moment.";

    public function __construct(
        public readonly string $jobId,
        public readonly int    $messageId,
        public readonly string $userText,
        public readonly int    $organizationId,
    ) {}

    public function handle(AskVentiq $ask): void
    {
        AiGenerationResult::where('job_id', $this->jobId)->update(['status' => 'processing']);

        try {
            $organization = Organization::findOrFail($this->organizationId);
            $result = $ask->answer($organization, $this->userText);

            if (!$result['ok']) {
                $this->markFailed(self::UNAVAILABLE_MESSAGE, (string) $result['error']);

                return;
            }

            AssistMessage::where('id', $this->messageId)->update([
                'content' => $result['answer'],
                'sources' => json_encode($result['sources']),
                'status'  => 'completed',
            ]);
            AiGenerationResult::where('job_id', $this->jobId)->update(['status' => 'completed', 'result' => $result['answer']]);
        } catch (\Throwable $e) {
            Log::error('GenerateAssistReply failed', ['job_id' => $this->jobId, 'message_id' => $this->messageId, 'error' => $e->getMessage()]);
            $this->markFailed(self::UNAVAILABLE_MESSAGE, $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->markFailed(self::UNAVAILABLE_MESSAGE, $e->getMessage());
    }

    // The user sees a short message; the technical one is kept for debugging.
    protected function markFailed(string $userMessage, string $technicalError): void
    {
        AiGenerationResult::where('job_id', $this->jobId)->update(['status' => 'failed', 'error' => $technicalError]);
        AssistMessage::where('id', $this->messageId)->update(['status' => 'failed', 'content' => $userMessage]);
    }
}
