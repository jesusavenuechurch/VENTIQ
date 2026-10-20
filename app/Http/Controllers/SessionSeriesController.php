<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\SessionSeries;
use App\Services\Sessions\SeriesScheduler;
use App\Support\SessionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/** A repeating series: its meetings ahead and behind, and changing or ending it. */
class SessionSeriesController extends Controller
{
    public function __construct(private SeriesScheduler $scheduler) {}

    public function show(SessionSeries $series)
    {
        $this->authorizeSeries($series);
        $this->scheduler->topUp($series);
        $sessions = $series->sessions()->withCount('segments')->get();
        $row = fn (Session $s) => [
            'id'       => $s->id,
            'date'     => $s->date->format('D j M Y') . ($s->start_time ? ' · ' . substr($s->start_time, 0, 5) : ''),
            'status'   => $s->status,
            'reviewed' => (bool) $s->reviewed_at,
        ];

        return Inertia::render('Sessions/Series', [
            'series' => [
                'id'                    => $series->id,
                'title'                 => $series->title,
                'type'                  => $series->type,
                'typeLabel'             => $series->custom_type_label ?: SessionType::label($series->type),
                'custom_type_label'     => $series->custom_type_label,
                'rule'                  => $series->describe(),
                'start_time'            => $series->start_time ? substr($series->start_time, 0, 5) : '',
                'expected_duration'     => $series->expected_duration,
                'expected_participants' => $series->expected_participants,
                'tracking'              => $series->track_participants,
                'ends_on'               => $series->ends_on?->toDateString(),
                'endsLabel'             => $series->ends_on?->format('j M Y'),
                'ended'                 => $series->isEnded(),
                'people'                => $series->people ?? [],
            ],
            // Ahead: not started, today or later. Everything else has happened (or was missed).
            'upcoming' => $sessions->filter($ahead = fn ($s) => $s->status === 'draft' && $s->date->gte(today()))->values()->map($row),
            'past'     => $sessions->reject($ahead)->sortByDesc('date')->values()->map($row),
            'types'    => SessionType::forForm(),
            'meta'     => ['title' => "{$series->title} | VENTIQ Sessions"],
        ]);
    }

    public function update(Request $request, SessionSeries $series)
    {
        $this->authorizeSeries($series);
        abort_if($series->isEnded(), 409, 'This series has ended.');

        $data = $request->validate([
            'title'                 => 'required|string|max:255',
            'type'                  => 'required|string|in:' . implode(',', SessionType::keys()),
            'custom_type_label'     => 'nullable|string|max:255',
            'start_time'            => 'nullable|date_format:H:i',
            'expected_duration'     => 'nullable|integer|min:1',
            'expected_participants' => 'nullable|integer|min:1',
            'people'                => 'nullable|array',
            'people.*.name'         => 'nullable|string|max:255',
            'people.*.role'         => 'nullable|string|max:255',
            'people.*.presenting'   => 'nullable|boolean',
            'ends_on'               => 'nullable|date|after_or_equal:today',
        ], ['ends_on.after_or_equal' => "The end date can't be in the past."]);

        $this->scheduler->update($series, $data);

        return back()->with('status', 'Saved. The meetings ahead have the changes; the ones already held keep theirs.');
    }

    public function end(SessionSeries $series)
    {
        $this->authorizeSeries($series);
        $this->scheduler->end($series);

        return back()->with('status', "\"{$series->title}\" has ended. Meetings already held stay, with their reports.");
    }

    public function skip(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id && $session->series_id, 404);
        $date = $session->date->format('D j M');
        $this->scheduler->skip($session);

        return back()->with('status', "The meeting on {$date} is skipped. The series carries on.");
    }

    private function authorizeSeries(SessionSeries $series): void
    {
        abort_unless($series->organization_id === Auth::user()->organization_id, 404);
    }
}
