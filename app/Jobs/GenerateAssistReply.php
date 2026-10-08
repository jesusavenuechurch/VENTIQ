<?php

namespace App\Jobs;

use App\Models\AiGenerationResult;
use App\Models\AssistMessage;
use App\Services\AssistSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAssistReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // No retry — a slow/unavailable AI provider should surface to the user
    // once, quickly, not silently retry after a 10s backoff and make them
    // wait through a second full timeout cycle with zero feedback.
    public int $timeout = 45;
    public int $tries   = 1;

    private const UNAVAILABLE_MESSAGE = "Ventiq Assist couldn't reach the AI provider just now. Please try again in a moment.";

    public function __construct(
        public readonly string $jobId,
        public readonly int    $messageId,
        public readonly string $userText,
        public readonly int    $organizationId,
    ) {}

    public function handle(AssistSearchService $search): void
    {
        AiGenerationResult::where('job_id', $this->jobId)
            ->update(['status' => 'processing']);

        try {
            // Search first, phrase second — the model never touches the
            // database directly. understandQuestion() only decides what to
            // look up; the org scope on search() always comes from this
            // job's own organizationId, never from the model's output.
            //
            // If understanding the question fails outright (provider down,
            // timed out), stop here — don't pay for a second slow call
            // that's very likely to fail the exact same way.
            $filters = $search->understandQuestion($this->userText);

            if ($filters === null) {
                $this->markFailed(self::UNAVAILABLE_MESSAGE, 'understandQuestion returned no result — AI call failed');
                return;
            }

            $sessions = $search->search($this->organizationId, $filters);
            $result   = $search->answer($this->userText, $sessions, (bool) $filters['count_only']);

            if (!$result->success) {
                $this->markFailed(self::UNAVAILABLE_MESSAGE, $result->error);
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

        } catch (\Throwable $e) {
            Log::error('GenerateAssistReply failed', [
                'job_id'     => $this->jobId,
                'message_id' => $this->messageId,
                'error'      => $e->getMessage(),
            ]);
            $this->markFailed(self::UNAVAILABLE_MESSAGE, $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->markFailed(self::UNAVAILABLE_MESSAGE, $e->getMessage());
    }

    // $userMessage is what shows up as the assistant's chat bubble — kept
    // short and free of raw technical detail. $technicalError is for
    // AiGenerationResult only, where it's actually useful for debugging.
    protected function markFailed(string $userMessage, string $technicalError): void
    {
        AiGenerationResult::where('job_id', $this->jobId)
            ->update(['status' => 'failed', 'error' => $technicalError]);

        AssistMessage::where('id', $this->messageId)
            ->update(['status' => 'failed', 'content' => $userMessage]);
    }
}