<?php

namespace App\Services\Sessions;

use App\Models\ActionPoint;
use App\Models\Certificate;
use App\Models\Participant;
use App\Models\ProgrammeEnrolment;
use App\Models\Session;
use App\Models\SessionSegment;
use App\Support\SessionType;
use Illuminate\Support\Collection;

/**
 * A held session in numbers: who came (how, from where, when), how the
 * time went, what was decided and agreed, and for a programme's day, how
 * the programme stands. Shown on the report's Summary and numbers tab and
 * in the full report PDF.
 */
class SessionNumbers
{
    public const HOW = ['self' => 'Self (QR or code)', 'walk_in' => 'Desk', 'manual' => 'Desk', 'scanner' => 'Scanner', 'pre_registered' => 'Registered ahead'];

    public function __construct(private MinutesBuilder $minutes) {}

    public function for(Session $session): array
    {
        $session->loadMissing(['segments', 'event']);
        $items = $session->segments->where('is_presenting', true)->sortBy('order')->values();
        $minuted = SessionType::takesMinutes($session->type);

        return [
            'attendance' => $session->event_id ? $this->attendance($session) : null,
            'time'       => $this->time($session, $items),
            'notes'      => [
                'lines' => $items->sum(fn (SessionSegment $s) => count($s->raw_log ?? [])),
                'items' => $items->filter(fn (SessionSegment $s) => !empty($s->raw_log))->count(),
                'of'    => $items->count(),
            ],
            'outcomes'   => $this->outcomes($session, $minuted),
            'programme'  => $session->event?->is_programme ? $this->programme($session) : null,
        ];
    }

    private function attendance(Session $session): array
    {
        $all = Participant::where('session_id', $session->id)->get();
        $came = $all->whereNotNull('attended_at');
        $expected = $session->meta['expected_participants'] ?? null;
        $start = $session->start_time ? $session->date->copy()->setTimeFromTimeString($session->start_time) : null;

        return [
            'checkedIn' => $came->count(),
            'expected'  => $expected ? (int) $expected : null,
            'rate'      => $expected ? (int) round($came->count() / $expected * 100) : null,
            'noShows'   => $all->whereNull('attended_at')->count(),
            'signed'    => $came->whereNotNull('signature_path')->count(),
            'how'       => $this->counts($came->map(fn ($p) => self::HOW[$p->source] ?? 'Desk')),
            'from'      => $this->counts($came->map(fn ($p) => trim((string) $p->institution) ?: 'Not given'), 6),
            'districts' => $this->counts($came->map(fn ($p) => $p->district ? $p->district_label : 'Not given'), 6),
            // Arrivals in 15 minutes, against the start: before, on time, late.
            'arrivals'  => $start ? $this->arrivals($came->pluck('attended_at'), $start) : [],
            'late'      => $start ? $came->filter(fn ($p) => $p->attended_at->gte($start->copy()->addMinutes(15)))->count() : null,
        ];
    }

    /** @return array<int, array{label: string, count: int}> biggest first; the rest as "Others". */
    private function counts(Collection $values, int $top = 8): array
    {
        $counted = $values->countBy()->sortDesc();
        $shown = $counted->take($top);
        $rest = $counted->slice($top)->sum();

        return $shown->map(fn ($count, $label) => ['label' => (string) $label, 'count' => $count])->values()
            ->when($rest > 0, fn ($c) => $c->push(['label' => 'Others', 'count' => $rest]))
            ->all();
    }

    private function arrivals(Collection $times, $start): array
    {
        if ($times->isEmpty()) {
            return [];
        }
        // From the earliest 15 minutes anyone came in to the latest; an hour before and three after at most.
        $slots = $times->map(fn ($t) => max(-4, min(12, (int) floor($start->diffInMinutes($t, false) / 15))));
        $counts = $slots->countBy();

        return collect(range($slots->min(), $slots->max()))->map(fn ($n) => [
            'label' => $start->copy()->addMinutes($n * 15)->format('H:i'),
            'count' => $counts[$n] ?? 0,
            'late'  => $n >= 1,
        ])->all();
    }

    private function time(Session $session, Collection $items): array
    {
        $rows = $items->map(fn (SessionSegment $s) => [
            'label'   => $s->title ?: $s->presenter_name,
            'lead'    => $s->title && $s->presenter_name !== $s->title ? $s->presenter_name : null,
            'minutes' => (int) round(($s->duration_seconds ?? 0) / 60),
        ])->values();
        $started = $items->pluck('started_at')->filter()->min();
        $ended = $items->pluck('ended_at')->filter()->max();

        return [
            'items'   => $rows->all(),
            'total'   => $started && $ended ? (int) round($started->diffInMinutes($ended)) : $rows->sum('minutes'),
            'planned' => ($session->meta['expected_duration_minutes'] ?? null) ? (int) $session->meta['expected_duration_minutes'] : null,
            'started' => $started?->format('H:i'),
            'ended'   => $ended?->format('H:i'),
        ];
    }

    private function outcomes(Session $session, bool $minuted): array
    {
        $agreed = $session->actionPoints()->whereIn('status', ['proposed', 'open', 'done'])->with(['ownerUser', 'ownerClient'])->get();
        $carried = ActionPoint::carriedInto($session);
        $decisions = $minuted && $session->status === 'reported'
            ? collect($this->minutes->build($session)['items'])->sum(fn ($i) => count($i['decisions']))
            : $session->segments->sum(fn (SessionSegment $s) => preg_match_all('/^\s*•/mu', (string) ($s->ai_summary['decisions'] ?? '')));

        return [
            'decisions' => $decisions,
            'agreed'    => [
                'total'    => $agreed->count(),
                'proposed' => $agreed->where('status', 'proposed')->count(),
                'done'     => $agreed->where('status', 'done')->count(),
                'overdue'  => $agreed->filter->isOverdue()->count(),
            ],
            'carried'   => [
                'total'      => $carried->count(),
                'doneHere'   => $carried->where('done_in_session_id', $session->id)->count(),
                'open'       => $carried->where('status', 'open')->count(),
                'overdue'    => $carried->filter->isOverdue()->count(),
            ],
            'owners'    => $agreed->groupBy(fn (ActionPoint $p) => $p->ownerLabel() ?? 'No one yet')
                ->map(fn ($points, $owner) => ['label' => $owner, 'count' => $points->count()])->sortByDesc('count')->values()->all(),
        ];
    }

    /** @return array<int, int> days attended => people, most days first; those enrolled who came to none as 0 */
    private function daysEach(\App\Models\Event $programme, Collection $days): array
    {
        $each = Participant::whereIn('session_id', $days->pluck('id'))->whereNotNull('attended_at')->get(['client_id', 'session_id'])
            ->groupBy('client_id')->map(fn ($rows) => $rows->pluck('session_id')->unique()->count());
        $none = ProgrammeEnrolment::where('event_id', $programme->id)->whereNull('withdrawn_at')->whereNotIn('client_id', $each->keys())->count();

        return $each->countBy()->when($none > 0, fn ($c) => $c->put(0, $none))->sortKeysDesc()->all();
    }

    private function programme(Session $session): array
    {
        $programme = $session->event;
        $progress = app(ProgrammeProgress::class);
        $days = $progress->days($programme);

        return [
            'name'         => $programme->name,
            'day'          => $days->search(fn ($d) => $d->id === $session->id) + 1,
            'days'         => $days->values()->map(fn (Session $d, $i) => [
                'label' => 'Day ' . ($i + 1),
                'date'  => $d->date?->format('j M'),
                'count' => Participant::where('session_id', $d->id)->whereNotNull('attended_at')->count(),
                'this'  => $d->id === $session->id,
            ])->all(),
            'enrolled'     => ProgrammeEnrolment::where('event_id', $programme->id)->whereNull('withdrawn_at')->count(),
            // How many days each person came to, so "21 each day" isn't read as "the same 21 every day".
            'attendedDays' => $this->daysEach($programme, $days),
            'required'     => $progress->required($programme, $days),
            'certificates' => $programme->certificates_enabled ? Certificate::where('event_id', $programme->id)->count() : null,
        ];
    }
}
