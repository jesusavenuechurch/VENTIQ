<?php

namespace App\Services\Sessions;

use App\Models\Organization;
use App\Models\Session;
use App\Models\SessionSeries;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Keeps each series' next meetings on the desk: AHEAD of them, made from
 * the series (name, type, time and people), each linked to the meeting
 * before it so it can pick up what was left open. A meeting that was
 * skipped isn't made again.
 */
class SeriesScheduler
{
    public const AHEAD = 4;

    public function __construct(private SessionMaker $maker) {}

    public function create(Organization $organization, User $by, array $data): SessionSeries
    {
        $series = SessionSeries::create([
            'organization_id'       => $organization->id,
            'created_by'            => $by->id,
            'title'                 => $data['title'],
            'type'                  => $data['type'],
            'custom_type_label'     => $data['type'] === 'custom' ? ($data['custom_type_label'] ?? null) : null,
            'start_time'            => $data['start_time'] ?? null,
            'expected_duration'     => $data['expected_duration'] ?? null,
            'track_participants'    => (bool) ($data['track_participants'] ?? false),
            'expected_participants' => $data['expected_participants'] ?? null,
            'people'                => SessionMaker::cleanPeople($data['people'] ?? []),
            'frequency'             => $data['frequency'],
            'starts_on'             => $data['date'],
            'ends_on'               => $data['ends_on'] ?? null,
        ]);

        $this->topUp($series, $by);

        return $series->fresh();
    }

    /** Make meetings until AHEAD are coming up (today or later). Returns how many were made. */
    public function topUp(SessionSeries $series, ?User $by = null): int
    {
        if ($series->isEnded()) {
            return 0;
        }

        return DB::transaction(function () use ($series, $by) {
            $series = SessionSeries::lockForUpdate()->find($series->id);
            $comingUp = $series->sessions()->whereDate('date', '>=', today())->count();
            $wanted = self::AHEAD - $comingUp;
            if ($wanted <= 0) {
                return 0;
            }

            // Never in the past: a series started last month begins today.
            $after = $series->scheduled_until ?? today()->subDay();
            if ($after->lt(today()->subDay())) {
                $after = today()->subDay();
            }

            $made = 0;
            foreach ($series->datesAfter($after, $wanted) as $date) {
                $previous = $series->sessions()->whereDate('date', '<', $date)->reorder()->orderByDesc('date')->first();
                $this->maker->make($series->organization, $by, [
                    'title'                 => $series->title,
                    'type'                  => $series->type,
                    'custom_type_label'     => $series->custom_type_label,
                    'date'                  => $date->toDateString(),
                    'start_time'            => $series->start_time ? substr($series->start_time, 0, 5) : null,
                    'expected_duration'     => $series->expected_duration,
                    'track_participants'    => $series->track_participants,
                    'expected_participants' => $series->expected_participants,
                    'people'                => $series->people ?? [],
                    'series_id'             => $series->id,
                    'parent_session_id'     => $previous?->id,
                ]);
                $series->scheduled_until = $date;
                $made++;
            }
            $series->save();

            return $made;
        });
    }

    /**
     * New details for the series, and for its meetings not held yet. A
     * meeting already started or held keeps what it had.
     */
    public function update(SessionSeries $series, array $data): void
    {
        DB::transaction(function () use ($series, $data) {
            $series->update([
                'title'                 => $data['title'],
                'type'                  => $data['type'],
                'custom_type_label'     => $data['type'] === 'custom' ? ($data['custom_type_label'] ?? null) : null,
                'start_time'            => $data['start_time'] ?? null,
                'expected_duration'     => $data['expected_duration'] ?? null,
                'expected_participants' => $data['expected_participants'] ?? null,
                'people'                => SessionMaker::cleanPeople($data['people'] ?? []),
                'ends_on'               => $data['ends_on'] ?? null,
            ]);

            foreach ($this->notYetHeld($series) as $session) {
                if ($series->ends_on && $session->date->gt($series->ends_on)) {
                    $session->delete();
                    continue;
                }
                $session->update([
                    'title'      => $series->title,
                    'type'       => $series->type,
                    'start_time' => $series->start_time,
                    'meta'       => array_merge($session->meta ?? [], [
                        'expected_duration_minutes' => $series->expected_duration,
                        'expected_participants'     => $series->track_participants ? $series->expected_participants : null,
                        'custom_type_label'         => $series->custom_type_label,
                    ]),
                ]);
                $this->maker->setPeople($session, $series->people ?? []);
            }
        });

        $this->topUp($series->fresh());
    }

    /** Leave one meeting out; the series carries on. */
    public function skip(Session $session): void
    {
        abort_unless($session->series_id && $session->status === 'draft', 409, 'Only a meeting that hasn\'t started can be skipped.');
        $series = $session->series;
        $session->delete();
        $this->topUp($series);
    }

    /** No more meetings: the ones not yet held go; past ones stay. */
    public function end(SessionSeries $series): void
    {
        DB::transaction(function () use ($series) {
            $this->notYetHeld($series)->each->delete();
            $series->update(['ended_at' => now()]);
        });
    }

    /** @return \Illuminate\Support\Collection<int, Session> */
    private function notYetHeld(SessionSeries $series)
    {
        return $series->sessions()->where('status', 'draft')->whereDate('date', '>=', today())->get();
    }
}
