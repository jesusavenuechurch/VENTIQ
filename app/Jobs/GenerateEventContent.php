<?php

namespace App\Jobs;

use App\Models\AiGenerationResult;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\EventDescriptionPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEventContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout    = 180; // 3 minutes max for AI generation
    public int $tries      = 2;   // retry once if it fails
    public int $backoff    = 10;  // wait 10 seconds before retry

    public function __construct(
        public readonly string $jobId,
        public readonly int    $userId,
        public readonly array  $promptData,
    ) {}

    public function handle(AIService $ai): void
    {
        // Mark as processing
        AiGenerationResult::where('job_id', $this->jobId)
            ->update(['status' => 'processing']);

        try {
            $prompt = (new EventDescriptionPrompt())->with($this->promptData);
            $result = $ai->generate($prompt);

            if (!$result->success) {
                AiGenerationResult::where('job_id', $this->jobId)
                    ->update([
                        'status' => 'failed',
                        'error'  => $result->error,
                    ]);
                return;
            }

            $sections = $ai->parseSections($result->content);

            // Parse titles into clean array
            if (!empty($sections['titles'])) {
                $sections['titles'] = collect(explode("\n", $sections['titles']))
                    ->map(fn ($t) => trim($t, "•-– \t1234567890."))
                    ->filter()
                    ->values()
                    ->toArray();
            }

            AiGenerationResult::where('job_id', $this->jobId)
                ->update([
                    'status'   => 'completed',
                    'result'   => $result->content,
                    'sections' => $sections,
                    'duration' => $result->duration,
                ]);

        } catch (\Exception $e) {
            Log::error('GenerateEventContent job failed', [
                'job_id' => $this->jobId,
                'error'  => $e->getMessage(),
            ]);

            AiGenerationResult::where('job_id', $this->jobId)
                ->update([
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        AiGenerationResult::where('job_id', $this->jobId)
            ->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
    }
}