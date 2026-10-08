<?php

namespace App\Jobs;

use App\Models\AiGenerationResult;
use App\Models\Participant;
use App\Models\Session;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\SegmentSummaryPrompt;
use App\Services\AI\Prompts\SessionSummaryPrompt;
use App\Support\SessionType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSessionReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly int $userId,
        public readonly int $sessionId,
    ) {}

    public function handle(AIService $ai): void
    {
        $session = Session::with('segments')->find($this->sessionId);
        if (!$session) { $this->markFailed('Session no longer exists.'); return; }

        $sectionsConfig = SessionType::sections($session->type);

        // ── PASS 1 — per-segment summaries ──────────────────────────────
        $attempted = 0;
        $succeeded = 0;
        $lastError = null;

        foreach ($session->segments as $segment) {
            if (!$segment->is_presenting) continue;

            $rawText = collect($segment->raw_log ?? [])->pluck('text')->implode("\n");
            if (trim($rawText) === '') continue;

            $attempted++;

            $prompt = (new SegmentSummaryPrompt)->with([
                'presenter' => $segment->presenter_name,
                'role'      => $segment->role,
                'topic'     => $segment->title,
                'raw_notes' => $rawText,
                'sections'  => $sectionsConfig,
            ]);

            try {
                $result = $ai->generate($prompt);
            } catch (\Throwable $e) {
                $result = \App\Services\AI\Results\GeneratedContent::failure($e->getMessage(), 'unknown');
            }

            if (!$result->success) {
                $lastError = $result->error;
                Log::warning("Segment AI summary failed", [
                    'session_id' => $session->id,
                    'segment_id' => $segment->id,
                    'error'      => $result->error,
                ]);
                $segment->update(['ai_summary' => ['_ai_failed' => true, '_error' => $result->error]]);
                continue;
            }

            $succeeded++;
            $parsed = $ai->parseSections($result->content);

            $summary = [];
            foreach (array_keys($sectionsConfig) as $key) {
                $summary[$key] = $parsed[$key] ?? 'None identified.';
            }
            $segment->update(['ai_summary' => $summary]);
        }

        // Whole-session failure — same rule as before: don't fake success
        // if there was real content and every attempt at summarizing it failed.
        if ($attempted > 0 && $succeeded === 0) {
            $this->markFailed($lastError ?? 'AI generation failed for every segment.');
            return;
        }

        // ── PASS 2 — overall rollup across every segment's summary ─────
        // This genuinely never existed before — $overall was referenced
        // in the report builder but nothing ever computed it. Built now,
        // matching what SessionSummaryPrompt already expects: THEMES and
        // RECOMMENDATIONS sections, fed by every segment's own summary.
        $overall = ['themes' => null, 'recommendations' => null];

        $segmentSummaryText = $session->segments
            ->filter(fn ($s) => $s->is_presenting && !empty($s->ai_summary) && empty($s->ai_summary['_ai_failed']))
            ->map(function ($s) use ($sectionsConfig) {
                $lines = collect($sectionsConfig)
                    ->map(fn ($def, $key) => $this->nullifyEmpty($s->ai_summary[$key] ?? null) !== ''
                        ? "{$def['label']}: {$s->ai_summary[$key]}"
                        : null)
                    ->filter()
                    ->implode("\n");
                return "{$s->presenter_name}:\n{$lines}";
            })
            ->implode("\n\n");

        // Only worth asking for a rollup if there's actually more than
        // one presenter's worth of summarized content to roll up.
        if ($succeeded > 1 && trim($segmentSummaryText) !== '') {
            try {
                $rollupPrompt = (new SessionSummaryPrompt)->with([
                    'title'             => $session->resolved_title,
                    'segment_summaries' => $segmentSummaryText,
                ]);
                $rollupResult = $ai->generate($rollupPrompt);

                if ($rollupResult->success) {
                    $parsedRollup = $ai->parseSections($rollupResult->content);
                    $overall['themes'] = $parsedRollup['themes'] ?? null;
                    $overall['recommendations'] = $parsedRollup['recommendations'] ?? null;
                } else {
                    // A failed rollup does NOT invalidate the whole report —
                    // the per-segment summaries are still real, valuable
                    // content. Just log it and ship without the rollup
                    // section, same as any other "None identified." case.
                    Log::warning("Session rollup summary failed", [
                        'session_id' => $session->id,
                        'error'      => $rollupResult->error,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Session rollup summary threw", ['session_id' => $session->id, 'error' => $e->getMessage()]);
            }
        }

        // ── Metrics — also never actually computed before ──────────────
        $metrics = [
            'presenterCount'    => $session->segments->where('is_presenting', true)->count(),
            'participantCount'  => $session->event_id
                ? Participant::where('session_id', $session->id)->count()
                : null,
            'durationFormatted' => $this->formatDuration(
                $session->segments->sum(fn ($s) => $s->duration_seconds ?? 0)
            ),
        ];

        $session->update([
            'session_report' => $this->buildReport($session, $overall, $metrics, $sectionsConfig),
            'status'         => 'reported',
        ]);
        AiGenerationResult::where('job_id', $this->jobId)->update(['status' => 'completed']);
    }

    // The dispatcher that never existed. Generic on purpose — see the
    // note above the class. If a type ever needs genuinely different
    // structure (not just different wording), branch here.
    private function buildReport(Session $session, array $overall, array $metrics, array $sectionsConfig): string
    {
        return $this->buildGenericReport($session, $overall, $metrics, $sectionsConfig);
    }

    private function buildGenericReport(Session $session, array $overall, array $metrics, array $sectionsConfig): string
    {
        $org   = $session->organization?->name ?? '';
        $title = $session->resolved_title;
        $date  = $session->date?->format('l, d F Y') ?? $session->created_at->format('l, d F Y');
        $owner = $session->creator?->name ?? '';
        $typeLabel = $session->type === 'custom' && !empty($session->meta['custom_type_label'])
            ? $session->meta['custom_type_label']
            : SessionType::label($session->type);

        $overviewLines = [
            "Organization: {$org}",
            "Session: {$title}",
            "Session Type: {$typeLabel}",
            "Date: {$date}",
            "Presenters: {$metrics['presenterCount']}",
        ];
        if ($metrics['participantCount'] !== null) {
            $overviewLines[] = "Participants: {$metrics['participantCount']}";
        }
        $overviewLines[] = "Duration: {$metrics['durationFormatted']}";

        $presenterBlocks = $session->segments->map(function ($s) use ($sectionsConfig, $session) {
            $header = $s->presenter_name . ($s->role ? ' — ' . (SessionType::roles($session->type)[$s->role]['label'] ?? ucfirst($s->role)) : '');

            if (!$s->is_presenting) {
                return $header;
            }

            $sum = $s->ai_summary ?? [];

            if (!empty($sum['_ai_failed'])) {
                return $header . "\n\n[AI summary unavailable for this segment — raw notes were captured but couldn't be summarized. Review the Source Notes panel directly.]";
            }

            $parts = array_filter(array_merge(
                [$header],
                collect($sectionsConfig)->map(fn ($def, $key) => $this->section($def['label'], $sum[$key] ?? null))->all()
            ), fn ($v) => $v !== '');

            return implode("\n\n", $parts);
        })->implode("\n\n------------------\n\n");

        // "PRESENTATION BREAKDOWN" was hardcoded before, wrong for every
        // non-presentation type. Now it follows the actual session type.
        $breakdownHeading = strtoupper($typeLabel) . ' BREAKDOWN';

        $body = array_filter([
            "{$org}\nVentiq {$typeLabel} Report",
            "SESSION OVERVIEW\n\n" . implode("\n", $overviewLines),
            "{$breakdownHeading}\n\n{$presenterBlocks}",
            $this->section('Overall Themes', $overall['themes'] ?? null),
            $this->section('Overall Recommendations', $overall['recommendations'] ?? null),
            "---\nGenerated by Ventiq Assist\nOwner: {$owner}\nGenerated: {$date}",
        ], fn ($v) => $v !== '');

        return implode("\n\n", $body);
    }

    private function formatDuration(int $totalSeconds): string
    {
        $h = intdiv($totalSeconds, 3600);
        $m = intdiv($totalSeconds % 3600, 60);
        if ($h > 0) return "{$h}h {$m}m";
        return "{$m}m";
    }

    private function section(string $heading, ?string $content): string
    {
        $content = $this->nullifyEmpty($content);
        return $content === '' ? '' : "{$heading}\n\n{$content}";
    }

    private function nullifyEmpty(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'None identified.') === 0) {
            return '';
        }
        return $value;
    }

    private function markFailed(string $reason): void
    {
        AiGenerationResult::where('job_id', $this->jobId)->update([
            'status'  => 'failed',
            'payload' => json_encode(['error' => $reason]),
        ]);
    }
}