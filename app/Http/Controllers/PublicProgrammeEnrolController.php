<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Sessions\ProgrammeProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** A programme's enrolment link: what it is, its days, and enrolling. */
class PublicProgrammeEnrolController extends Controller
{
    public function __construct(private ProgrammeProgress $progress) {}

    public function show(string $token)
    {
        $programme = Event::where('enrol_token', $token)->where('is_programme', true)->firstOrFail();
        $days = $this->progress->days($programme);
        $required = $this->progress->required($programme, $days);

        return Inertia::render('Public/ProgrammeEnrol', [
            'programme' => [
                'name'         => $programme->name,
                'organization' => $programme->organization?->name,
                'venue'        => $programme->venue,
                'open'         => (bool) $programme->enrolment_open,
                'days'         => $days->map(fn ($d) => $d->date->format('D j M') . ($d->start_time ? ' · ' . substr($d->start_time, 0, 5) : ''))->values(),
                'rule'         => $programme->certificates_enabled && $required
                    ? ($required >= $days->count() ? 'every day' : "at least {$required} of the {$days->count()} days")
                    : null,
                'token'        => $token,
            ],
            'enrolled' => session('enrolled'),
            'meta'     => ['title' => "Enrol: {$programme->name} | VENTIQ"],
        ])->withViewData(['noindex' => true]);
    }

    public function store(Request $request, string $token)
    {
        $programme = Event::where('enrol_token', $token)->where('is_programme', true)->firstOrFail();
        if (!$programme->enrolment_open) {
            return back()->withErrors(['full_name' => 'Enrolment for this programme is closed. Ask the organizer.']);
        }

        $data = $request->validate([
            'full_name'   => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:255',
            'position'    => 'nullable|string|max:255',
        ]);

        $this->progress->enrol($programme, $data, 'self');

        return redirect()->route('public.programme-enrol.form', $token)->with('enrolled', $data['full_name']);
    }
}
