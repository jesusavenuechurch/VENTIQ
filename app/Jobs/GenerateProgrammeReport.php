<?php

namespace App\Jobs;

use App\Models\AiGenerationResult;
use App\Models\Event;
use App\Models\Session;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\SessionSummaryPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateProgrammeReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly int $programmeId,
    ) {}

    public function handle(AIService $ai): void
    {
        $programme = Event::find($this->programmeId);
        if (!$programme) { $this->markFailed('Programme no longer exists.'); return; }

        // Only Sessions that actually finished being reported contribute.
        // A Programme rollup generated while Day 3 is still mid-capture
        // would just be wrong, not incomplete — so we gate on this
        // rather than silently rolling up whatever happens to exist.
        $sessions = Session::where('event_id', $programme->id)
            ->where('status', 'reported')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        if ($sessions->isEmpty()) {
            $this->markFailed('No sessions in this Programme have a completed report yet.');
            return;
        }

        // ── Overall rollup across every Session's own report ────────────
        // Reusing SessionSummaryPrompt deliberately — it already does
        // exactly this shape of task (roll up N summaries into overall
        // Themes/Recommendations). A "Programme" rollup and a "Session"
        // rollup are the same operation one level up; writing a near-
        // duplicate prompt class just to rename "presenters" to
        // "sessions" would be the over-engineering this build has been
        // avoiding all night.
        $sessionSummaryText = $sessions
            ->map(fn ($s) => "{$s->resolved_title} ({$s->date?->format('d M Y')}):\n{$s->session_report}")
            ->implode("\n\n==================\n\n");

        $overall = ['themes' => null, 'recommendations' => null];

        try {
            $rollupPrompt = (new SessionSummaryPrompt)->with([
                'title'             => $programme->name,
                'segment_summaries' => $sessionSummaryText,
            ]);
            $rollupResult = $ai->generate($rollupPrompt);

            if ($rollupResult->success) {
                $parsed = $ai->parseSections($rollupResult->content);
                $overall['themes'] = $parsed['themes'] ?? null;
                $overall['recommendations'] = $parsed['recommendations'] ?? null;
            } else {
                // Same graceful-degrade rule as the Session-level rollup:
                // a failed AI rollup doesn't invalidate the report — the
                // per-session summaries below are still real, valuable
                // content on their own.
                Log::warning('Programme rollup AI call failed', [
                    'programme_id' => $programme->id,
                    'error'        => $rollupResult->error,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Programme rollup AI call threw', ['programme_id' => $programme->id, 'error' => $e->getMessage()]);
        }

        $report = $this->buildReport($programme, $sessions, $overall);

        $programme->update([
            'programme_report'              => $report,
            'programme_report_generated_at' => now(),
        ]);
        AiGenerationResult::where('job_id', $this->jobId)->update(['status' => 'completed']);
    }

    private function buildReport(Event $programme, $sessions, array $overall): string
    {
        $org = $programme->organization?->name ?? '';
        $dateRange = $programme->duration_days > 1
            ? $programme->event_date?->format('d M') . ' – ' . $programme->event_date?->copy()->addDays($programme->duration_days - 1)->format('d M Y')
            : $programme->event_date?->format('d M Y');

        $overviewLines = [
            "Organization: {$org}",
            "Programme: {$programme->name}",
            "Dates: {$dateRange}",
            "Sessions Reported: {$sessions->count()}",
        ];
        if ($programme->venue) {
            $overviewLines[] = "Venue: {$programme->venue}";
        }

        $sessionBlocks = $sessions->map(function ($s) {
            $header = "{$s->resolved_title} — {$s->date?->format('d M Y')}";
            return "{$header}\n\n{$s->session_report}";
        })->implode("\n\n------------------\n\n");

        $body = array_filter([
            "{$org}\nVentiq Programme Report",
            "PROGRAMME OVERVIEW\n\n" . implode("\n", $overviewLines),
            $this->section('Overall Themes Across Sessions', $overall['themes'] ?? null),
            $this->section('Overall Recommendations', $overall['recommendations'] ?? null),
            "SESSION-BY-SESSION BREAKDOWN\n\n{$sessionBlocks}",
            "---\nGenerated by Ventiq Assist\nGenerated: " . now()->format('l, d F Y'),
        ], fn ($v) => $v !== '');

        return implode("\n\n", $body);
    }

    private function section(string $heading, ?string $content): string
    {
        $value = trim((string) $content);
        if ($value === '' || strcasecmp($value, 'None identified.') === 0) {
            return '';
        }
        return "{$heading}\n\n{$value}";
    }

    private function markFailed(string $reason): void
    {
        AiGenerationResult::where('job_id', $this->jobId)->update([
            'status'  => 'failed',
            'payload' => json_encode(['error' => $reason]),
        ]);
    }
}