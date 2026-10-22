<?php

namespace App\Services\Sessions;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Session;
use App\Models\User;
use App\Support\SessionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The participant list as officials need it (procurement, paying the
 * venue, reimbursement, per diem): the organization and session, date and
 * venue, and one row per person with their organization, position,
 * contacts and signature. Over several days (a programme) there's one
 * column per day. Every check-in says how it was recorded and when, so the
 * list can stand as evidence.
 */
class ParticipantList
{
    /** How a check-in was recorded, in the list's words. */
    public const HOW = [
        'self'           => 'Self',
        'walk_in'        => 'Desk',
        'manual'         => 'Desk',
        'scanner'        => 'Scanner',
        'pre_registered' => 'Registered',
    ];

    public function forSession(Session $session, ?User $by = null): array
    {
        $session->loadMissing('organization');

        return $this->build(
            organization: $session->organization?->name,
            title: $session->resolved_title,
            kind: $session->type === 'custom' && !empty($session->meta['custom_type_label']) ? $session->meta['custom_type_label'] : SessionType::label($session->type),
            venue: $session->location,
            days: collect([$session]),
            by: $by,
        );
    }

    public function forProgramme(Event $programme, ?User $by = null): array
    {
        $programme->loadMissing('organization');
        $days = Session::where('event_id', $programme->id)->orderBy('date')->orderBy('start_time')->get();

        return $this->build(
            organization: $programme->organization?->name,
            title: $programme->name,
            kind: 'Programme',
            venue: $programme->venue,
            days: $days,
            by: $by,
        );
    }

    /** @param Collection<int, Session> $days */
    private function build(?string $organization, string $title, string $kind, ?string $venue, Collection $days, ?User $by): array
    {
        $checkIns = Participant::with('client')
            ->whereIn('session_id', $days->pluck('id'))
            ->whereNotNull('attended_at')
            ->orderBy('attended_at')
            ->get();

        // One row per person, in the order they first arrived.
        $rows = $checkIns->groupBy('client_id')->map(function (Collection $theirs) use ($days) {
            $latest = $theirs->sortByDesc('attended_at')->first();
            $signed = $theirs->filter->isSigned()->sortByDesc('signed_at')->first();

            return [
                'first'        => $theirs->min('attended_at'),
                'name'         => $latest->client?->full_name ?? '—',
                'organization' => $theirs->pluck('institution')->filter()->last(),
                'position'     => $theirs->pluck('position')->filter()->last(),
                'district'     => $theirs->pluck('district')->filter()->map(fn ($d) => config("constants.workshop_districts.{$d}") ?? $d)->last(),
                'phone'        => $latest->client?->phone,
                'email'        => $latest->client?->email,
                // Each day: when they checked in, and how.
                'days'         => $days->mapWithKeys(fn (Session $d) => [$d->id => ($p = $theirs->firstWhere('session_id', $d->id)) ? [
                    'time' => $p->attended_at->format('H:i'),
                    'how'  => self::HOW[$p->source] ?? ucfirst(str_replace('_', ' ', (string) $p->source)),
                ] : null])->all(),
                'attended'     => $theirs->count(),
                'signature'    => $signed ? $this->image($signed->signature_path) : null,
            ];
        })->sortBy('first')->values();

        return [
            'organization' => $organization,
            'title'        => $title,
            'kind'         => $kind,
            'venue'        => $venue,
            'dates'        => $this->dates($days),
            'days'         => $days->values()->map(fn (Session $d, $i) => [
                'id'    => $d->id,
                'label' => $days->count() > 1 ? 'Day ' . ($i + 1) : 'In',
                'date'  => $d->date?->format('D j M'),
                'count' => $checkIns->where('session_id', $d->id)->count(),
            ])->all(),
            'rows'         => $rows->map(fn ($r) => collect($r)->except('first')->all())->all(),
            'districts'    => $rows->contains(fn ($r) => $r['district']),
            'how'          => $checkIns->countBy(fn ($p) => self::HOW[$p->source] ?? 'Other')->sortKeys()->all(),
            'generated'    => now()->format('j F Y, H:i'),
            'by'           => $by?->name,
        ];
    }

    private function dates(Collection $days): ?string
    {
        $dates = $days->pluck('date')->filter()->sort()->values();
        if ($dates->isEmpty()) {
            return null;
        }
        $first = $dates->first();
        $last = $dates->last();

        return $first->isSameDay($last)
            ? $first->format('l, j F Y')
            : $first->format('j M') . ' to ' . $last->format('j M Y') . ' (' . $dates->unique(fn ($d) => $d->toDateString())->count() . ' days)';
    }

    /** A signature drawn in the scanner app, inlined so the PDF needs no file access. */
    private function image(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($path));
    }
}
