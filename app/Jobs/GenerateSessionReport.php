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

        if (!$session) {
            $this->markFailed('Session no longer exists.');
            return;
        }

        // Pass 1 — one summary per segment that actually has notes.
        foreach ($session->segments as $segment) {
            $rawText = collect($segment->raw_log ?? [])->pluck('text')->implode("\n");
            if (trim($rawText) === '') continue;

            $prompt = (new SegmentSummaryPrompt)->with([
                'presenter' => $segment->presenter_name,
                'topic'     => $segment->title,
                'raw_notes' => $rawText,
            ]);

            $result = $ai->generate($prompt);
            if (!$result->success) continue;

            $sections = $ai->parseSections($result->content);

            $segment->update(['ai_summary' => [
                'summary'    => $sections['summary']    ?? 'None identified.',
                'key_points' => $sections['key_points']  ?? 'None identified.',
                'follow_ups' => $sections['follow_ups']  ?? 'None identified.',
                'questions'  => $sections['questions']  ?? 'None identified.',
            ]]);
        }

        $session->refresh();

        // Pass 2 — roll-up built from the segment summaries, not the raw text again.
        $summaryLines = $session->segments
            ->filter(fn ($s) => !empty($s->ai_summary['summary'] ?? null))
            ->map(fn ($s) => "{$s->presenter_name}: " . $s->ai_summary['summary'])
            ->implode("\n\n");

        $overall = [];
        if ($summaryLines !== '') {
            $sessionPrompt = (new SessionSummaryPrompt)->with([
                'title'             => $session->resolved_title,
                'segment_summaries' => $summaryLines,
            ]);

            $sessionResult = $ai->generate($sessionPrompt);
            if ($sessionResult->success) {
                $overall = $ai->parseSections($sessionResult->content);
            }
        }

        $totalSeconds = (int) $session->segments->sum(fn ($s) => $s->duration_seconds ?? 0);

        $metrics = [
            'presenterCount'     => $session->segments->count(),
            'participantCount'   => $session->event_id ? Participant::where('event_id', $session->event_id)->count() : null,
            'durationFormatted'  => sprintf('%02d:%02d:%02d', intdiv($totalSeconds, 3600), intdiv($totalSeconds % 3600, 60), $totalSeconds % 60),
        ];

        $session->update([
            'session_report' => $this->buildPresentationReport($session, $overall, $metrics),
            'status'         => 'reported',
        ]);

        AiGenerationResult::where('job_id', $this->jobId)->update(['status' => 'completed']);
    }

    // Named for the session type on purpose — buildMeetingReport(),
    // buildWorkshopReport() etc. will sit alongside this once more
    // session types are actually built. Not solved generically today,
    // deliberately, since Presentation is still the only real type.
    private function buildPresentationReport(Session $session, array $overall, array $metrics): string
    {
        $org   = $session->organization?->name ?? '';
        $title = $session->resolved_title;
        $date  = $session->date?->format('l, d F Y') ?? $session->created_at->format('l, d F Y');
        $owner = $session->creator?->name ?? '';

        $overviewLines = [
            "Organization: {$org}",
            "Session: {$title}",
            "Session Type: " . SessionType::label($session->type),
            "Date: {$date}",
            "Presenters: {$metrics['presenterCount']}",
        ];
        if ($metrics['participantCount'] !== null) {
            $overviewLines[] = "Participants: {$metrics['participantCount']}";
        }
        $overviewLines[] = "Duration: {$metrics['durationFormatted']}";

        $presenterBlocks = $session->segments->map(function ($s) {
            $sum = $s->ai_summary ?? [];

            $parts = array_filter([
                $s->presenter_name,
                $this->section('Summary', $sum['summary'] ?? null),
                $this->section('Key Points', $sum['key_points'] ?? null),
                $this->section('Follow-ups', $sum['follow_ups'] ?? null),
                $this->section('Questions', $sum['questions'] ?? null),
            ], fn ($v) => $v !== '');

            return implode("\n\n", $parts);
        })->implode("\n\n------------------\n\n");

        $body = array_filter([
            "{$org}\nVentiq Presentation Report",
            "SESSION OVERVIEW\n\n" . implode("\n", $overviewLines),
            "PRESENTATION BREAKDOWN\n\n{$presenterBlocks}",
            $this->section('Overall Themes', $overall['themes'] ?? null),
            $this->section('Overall Recommendations', $overall['recommendations'] ?? null),
            "---\nGenerated by Ventiq Assist\nOwner: {$owner}\nGenerated: {$date}",
        ], fn ($v) => $v !== '');

        return implode("\n\n", $body);
    }

    // Renders a heading + content, or nothing at all if the content is
    // blank or is the model's own "None identified." placeholder — the
    // reader should never see that string, even though the prompt still
    // asks the model for it as a reliable, parseable signal of "empty."
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