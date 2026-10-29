<?php

namespace App\Services\Sessions;

use App\Models\ActionPoint;
use App\Models\Participant;
use App\Models\Session;
use App\Models\SessionSegment;
use App\Support\SessionType;
use Illuminate\Support\Collection;

/**
 * A meeting's minutes, in the order minutes are kept: heading, present
 * and apologies, the previous minutes, matters arising, each agenda item
 * (discussion and decisions), action points, any other business, the
 * next meeting, and who confirmed them.
 *
 * Assembled from what's already there (the notes Ventiq Assist wrote up
 * per item, the action points, who checked in, the series); anything
 * changed by hand is kept on the session's `minutes` and wins.
 */
class MinutesBuilder
{
    /** What can be changed by hand, besides each item's discussion and decisions. */
    public const FIELDS = ['present', 'apologies', 'aob', 'next'];

    public function build(Session $session): array
    {
        $session->loadMissing(['segments', 'organization', 'series']);
        $edits = $session->minutes ?? [];
        $meta = $session->meta ?? [];
        $chair = $meta['chair'] ?? null;
        $secretary = $meta['secretary'] ?? null;
        $items = $session->segments->where('is_presenting', true)->values();

        return [
            'heading' => [
                'organization' => $session->organization?->name,
                'title'        => $session->resolved_title,
                'type'         => SessionType::label($session->type),
                'date'         => ($session->date ?? $session->created_at)->format('l, j F Y'),
                'time'         => $session->start_time ? substr($session->start_time, 0, 5) : null,
                'venue'        => $session->location,
                'chair'        => $chair,
                'secretary'    => $secretary,
            ],
            'present'   => $this->lines($edits['present'] ?? null) ?? $this->present($session, $items, $chair, $secretary),
            'apologies' => $this->lines($edits['apologies'] ?? null) ?? [],
            'previous'  => $this->previous($session),
            'arising'   => ActionPoint::carriedInto($session)->map->toCard()->values()->all(),
            'items'     => $items->map(fn (SessionSegment $s) => $this->item($s, $edits['items'][$s->id] ?? []))->all(),
            'actions'   => $session->actionPoints()->whereIn('status', ['proposed', 'open', 'done'])->with(['session', 'ownerUser', 'ownerClient'])->orderBy('id')->get()->map->toCard()->all(),
            'aob'       => trim($edits['aob'] ?? '') ?: null,
            'next'      => array_key_exists('next', $edits) ? (trim($edits['next'] ?? '') ?: null) : $this->next($session),
            'confirmed' => $session->reviewed_at ? [
                'by'   => $meta['reviewed_by'] ?? null,
                'date' => $session->reviewed_at->format('j F Y'),
            ] : null,
        ];
    }

    /**
     * Who was there: those who checked in, when the meeting tracked it,
     * and the chair, secretary and whoever led an item.
     */
    private function present(Session $session, Collection $items, ?string $chair, ?string $secretary): array
    {
        $checkedIn = $session->event_id
            ? Participant::where('session_id', $session->id)->whereNotNull('attended_at')->with('client')->orderBy('attended_at')->get()
                ->map(fn ($p) => $p->client?->full_name)
            : collect();

        return collect([$chair ? "{$chair} (Chair)" : null, $secretary ? "{$secretary} (Secretary)" : null])
            ->merge($items->map->lead())
            ->merge($checkedIn)
            ->filter()
            ->map(fn ($n) => trim($n))
            // Each person once, the chair's and secretary's lines included.
            ->unique(fn ($n) => mb_strtolower(preg_replace('/ \((Chair|Secretary)\)$/', '', $n)))
            ->values()
            ->all();
    }

    private function item(SessionSegment $segment, array $edit): array
    {
        $summary = $segment->ai_summary ?? [];
        $failed = !empty($summary['_ai_failed']);

        return [
            'id'         => $segment->id,
            'title'      => $segment->title ?: $segment->presenter_name,
            'lead'       => $segment->lead(),
            'discussion' => array_key_exists('discussion', $edit)
                ? (trim($edit['discussion'] ?? '') ?: null)
                : ($failed ? null : $this->text($summary['summary'] ?? null)),
            'decisions'  => array_key_exists('decisions', $edit)
                ? ($this->lines($edit['decisions']) ?? [])
                : ($failed ? [] : ($this->lines($this->text($summary['decisions'] ?? null)) ?? [])),
            'noted'      => !empty($segment->raw_log) || trim((string) $segment->transcript) !== '',
        ];
    }

    /** The meeting before this one in its series: were its minutes confirmed? */
    private function previous(Session $session): ?array
    {
        $previous = $session->parent_session_id ? Session::find($session->parent_session_id) : null;
        if (!$previous || $previous->status !== 'reported') {
            return null;
        }

        return [
            'id'        => $previous->id,
            'date'      => $previous->date->format('j F Y'),
            'confirmed' => (bool) $previous->reviewed_at,
        ];
    }

    private function next(Session $session): ?string
    {
        if (!$session->series_id) {
            return null;
        }
        $next = Session::where('series_id', $session->series_id)
            ->whereDate('date', '>', $session->date)
            ->orderBy('date')
            ->first();

        return $next ? $next->date->format('l, j F Y') . ($next->start_time ? ' at ' . substr($next->start_time, 0, 5) : '') : null;
    }

    /** Text from the notes write-up, or null for "None identified." */
    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || preg_match('/^none( identified)?\.?$/i', $value) ? null : $value;
    }

    /** One entry per line, without bullets; null when nothing was given. */
    private function lines(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return collect(preg_split('/\R/', $value))
            ->map(fn ($l) => trim(preg_replace('/^\s*(?:[•\-*]|\d+[.)])\s*/u', '', $l)))
            ->filter(fn ($l) => $l !== '' && !preg_match('/^none( identified)?\.?$/i', $l))
            ->values()
            ->all();
    }
}
