<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\Event;
use App\Models\Participant;
use App\Models\AiGenerationResult;
use App\Jobs\GenerateSessionReport;
use App\Services\SessionQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SessionController extends Controller
{
    public function __construct(private SessionQuotaService $quota) {}

    public function reports(Request $request)
    {
        $tab = $request->query('tab') === 'reviewed' ? 'reviewed' : 'needs_review';

        $reports = Session::forOrganization(Auth::user()->organization_id)
            ->where('status', 'reported')
            ->when($tab === 'reviewed', fn ($q) => $q->whereNotNull('reviewed_at'), fn ($q) => $q->whereNull('reviewed_at'))
            ->orderByDesc('date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Sessions/Reports', [
            'tab'     => $tab,
            'reports' => $reports->through(fn (Session $s) => [
                'id'       => $s->id,
                'title'    => $s->resolved_title,
                'date'     => $s->date?->format('d M Y'),
                'reviewed' => $s->reviewed_at?->format('d M Y'),
            ]),
            'meta'    => ['title' => 'Reports | VENTIQ Sessions'],
        ]);
    }

    public function index(Request $request)
    {
        $organization = Auth::user()->organization;

        // Each running series keeps its next meetings made (the daily
        // top-up does it too; this covers a desk opened first).
        $series = \App\Models\SessionSeries::where('organization_id', $organization->id)->whereNull('ended_at')->get();
        $series->each(fn ($s) => app(\App\Services\Sessions\SeriesScheduler::class)->topUp($s, Auth::user()));

        $sessions = Session::forOrganization($organization->id)
            ->with(['segments', 'series'])
            ->latest()
            ->get();

        $participantCounts = $sessions->isEmpty()
            ? collect()
            : Participant::whereIn('session_id', $sessions->pluck('id'))
                ->selectRaw('session_id, count(*) as aggregate')
                ->groupBy('session_id')
                ->pluck('aggregate', 'session_id');

        $tile = function (Session $s) use ($participantCounts) {
            $activeIndex = $s->segments->search(fn ($seg) => $seg->status === 'active');
            $starts = $s->date
                ? $s->date->copy()->setTimeFromTimeString($s->start_time ?: '00:00')
                : null;

            return [
                'id'         => $s->id,
                'title'      => $s->resolved_title,
                'type'       => $s->meta['custom_type_label'] ?? \App\Support\SessionType::label($s->type),
                'status'     => $s->status,
                'attendees'  => (int) ($participantCounts[$s->id] ?? 0),
                'expected'   => $s->meta['expected_participants'] ?? null,
                'presenters' => $s->segments->count(),
                'speaker'    => $activeIndex === false ? null : $activeIndex + 1,
                'starts'     => $starts ? ($s->start_time ? $starts->format('D j M · H:i') : $starts->format('D j M')) : null,
                'updated'    => $s->updated_at->diffForHumans(),
                'tracking'   => (bool) $s->public_token,
                'series'     => $s->series ? ['id' => $s->series->id, 'rule' => $s->series->describe()] : null,
            ];
        };

        // Reported sessions live in Reports once reviewed; finished ones
        // without a report yet are "Recently finished".
        $columns = [
            'ready'    => $sessions->filter(fn ($s) => $s->status === 'reported' && !$s->reviewed_at),
            'live'     => $sessions->filter(fn ($s) => $s->status === 'active'),
            // A series shows only its next meeting here; the rest are on its page.
            'upcoming' => $sessions->filter(fn ($s) => $s->status === 'draft')->sortBy(fn ($s) => $s->date?->toDateString() . ' ' . $s->start_time)
                ->unique(fn ($s) => $s->series_id ? "series-{$s->series_id}" : "session-{$s->id}"),
            'recent'   => $sessions->filter(fn ($s) => $s->status === 'completed'),
        ];

        return Inertia::render('Sessions/Index', [
            'columns'        => collect($columns)->map(fn ($items) => $items->map($tile)->values()),
            'filter'         => in_array($request->query('filter'), array_keys($columns), true) ? $request->query('filter') : null,
            'members'        => $organization->members()->count(),
            'pendingInvites' => $organization->invites()->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
            'series'         => $series->map(fn ($s) => [
                'id'    => $s->id,
                'title' => $s->title,
                'rule'  => $s->describe(),
                'next'  => $s->sessions()->where('status', 'draft')->whereDate('date', '>=', today())->first()?->date?->format('D j M'),
            ])->values(),
            'types'          => \App\Support\SessionType::forForm(),
            'today'          => now()->toDateString(),
            'openSchedule'   => (bool) session('schedule'),
            'meta'           => ['title' => 'Sessions | VENTIQ'],
        ]);
    }

    // Scheduling is a dialog over the desk, or over the programme it's for.
    public function create(Request $request)
    {
        $programme = $request->query('event')
            ? Event::where('id', $request->query('event'))
                ->where('organization_id', Auth::user()->organization_id)
                ->where('is_programme', true)
                ->first()
            : null;

        return ($programme
            ? redirect()->route('programmes.show', $programme)
            : redirect()->route('sessions.index'))->with('schedule', true);
    }

    public function store(Request $request, \App\Services\Sessions\SessionMaker $maker, \App\Services\Sessions\SeriesScheduler $scheduler)
    {
        $validated = $request->validate([
            'title'                 => 'required|string|max:255',
            'type'                  => 'required|string|in:' . implode(',', \App\Support\SessionType::keys()),
            'custom_type_label'     => 'nullable|string|max:255',
            'date'                  => 'nullable|date',
            'start_time'            => 'nullable|date_format:H:i',
            'programme_id'          => 'nullable|integer|exists:events,id',
            'people'                => 'nullable|array',
            'people.*.name'         => 'nullable|string|max:255',
            'people.*.role'         => 'nullable|string|max:255',
            'people.*.presenting'   => 'nullable|boolean',
            'expected_duration'     => 'nullable|integer|min:1',
            'track_participants'    => 'nullable|boolean',
            'expected_participants' => 'nullable|integer|min:1',
            // Repeating: a series instead of one session (not inside a programme).
            'repeat'                => 'nullable|in:' . implode(',', \App\Models\SessionSeries::FREQUENCIES),
            'ends_on'               => 'nullable|date|after_or_equal:date',
        ], ['ends_on.after_or_equal' => 'The series has to end on or after its first meeting.']);

        $organization = Auth::user()->organization;
        $validated['date'] ??= now()->toDateString();
        $validated['track_participants'] = $request->boolean('track_participants');

        if (!empty($validated['repeat']) && empty($validated['programme_id'])) {
            $series = $scheduler->create($organization, Auth::user(), $validated + ['frequency' => $validated['repeat']]);
            $first = $series->sessions()->first();

            return redirect()->route('sessions.series.show', $series)->with('status', $first
                ? "\"{$series->title}\" repeats " . lcfirst($series->describe()) . ". The next " . $series->sessions()->whereDate('date', '>=', today())->count() . ' meetings are on the desk, from ' . $first->date->format('D j M') . '.'
                : "\"{$series->title}\" is set up.");
        }

        // A one-off: today means "start now", so straight into the console.
        $validated['start_time'] ??= $validated['date'] === now()->toDateString() ? now()->format('H:i') : null;
        $session = $maker->make($organization, Auth::user(), $validated);

        if ($session->date->isToday()) {
            return redirect()->route('sessions.show', $session);
        }

        return redirect()->route($session->event?->is_programme ? 'programmes.show' : 'sessions.index', $session->event?->is_programme ? $session->event : [])
            ->with('status', "\"{$session->title}\" scheduled for {$session->date->format('d M Y')}.");
    }

    public function show(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        $session->load('segments.insights');

        return Inertia::render('Sessions/Show', [
            'session' => [
                'id'           => $session->id,
                'title'        => $session->resolved_title,
                'status'       => $session->status,
                'tracking'     => (bool) $session->event_id,
                'checkinUrl'   => $session->public_token ? route('public.session-checkin.form', $session->public_token) : null,
                'expected'     => $session->meta['expected_participants'] ?? null,
                'participants' => Participant::where('session_id', $session->id)->count(),
            ],
            'segments' => $session->segments->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->presenter_name,
                'role'       => $s->role,
                'presenting' => (bool) $s->is_presenting,
                'topic'      => $s->title ?? '',
                'status'     => $s->status,
                'paused'     => $s->status === 'active' && $s->paused_at !== null,
                'duration'   => (int) ($s->duration_seconds ?? 0),
                'logs'       => collect($s->raw_log ?? [])->map(fn ($l) => ['time' => $l['time'] ?? '', 'text' => $l['text'] ?? ''])->values(),
                'insights'   => $s->insights->map(fn ($i) => ['id' => $i->id, 'type' => $i->category, 'text' => $i->content])->values(),
            ])->values(),
            'roles' => collect(\App\Support\SessionType::roles($session->type))
                ->map(fn ($r, $value) => ['value' => $value, 'label' => $r['label'], 'presenting' => $r['presenting']])
                ->values(),
            'meta' => ['title' => "{$session->resolved_title} | VENTIQ Sessions"],
        ])->withViewData(['noindex' => true]);
    }

    public function checkinQr(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        if (!$session->public_token) {
            abort(404, 'This session isn\'t tracking participants — there\'s no check-in link to generate a QR code for.');
        }

        $url = route('public.session-checkin.form', $session->public_token);

        [$qr, $type] = $this->qr($url);

        return response($qr, 200)->header('Content-Type', $type);
    }

    /**
     * The check-in QR code and its type: a PNG needs the imagick
     * extension, so without it an SVG (as for tickets).
     */
    private function qr(string $url): array
    {
        $make = fn (string $format) => (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format($format)->size(400)->margin(1)->generate($url);

        if (extension_loaded('imagick')) {
            try {
                return [$make('png'), 'image/png'];
            } catch (\Throwable) {
                // Falls through to the SVG.
            }
        }

        return [$make('svg'), 'image/svg+xml'];
    }

    public function checkinPass(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->public_token, 404);

        return Inertia::render('Sessions/CheckinPass', [
            'pass' => [
                'id'           => $session->id,
                'title'        => $session->resolved_title,
                'type'         => $session->meta['custom_type_label'] ?? \App\Support\SessionType::label($session->type),
                'date'         => ($session->date ?? $session->created_at)->format('d M Y'),
                'expected'     => $session->meta['expected_participants'] ?? null,
                'location'     => $session->location,
                'organization' => $session->organization->name,
                'code'         => $session->session_code,
                'joinUrl'      => route('public.session-join'),
            ],
            'meta' => ['title' => "Check-in pass: {$session->resolved_title} | VENTIQ"],
        ])->withViewData(['noindex' => true]);
    }

    public function checkinPassPdf(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->public_token, 404);

        $checkinUrl = route('public.session-checkin.form', $session->public_token);

        [$qr, $qrType] = $this->qr($checkinUrl);

        $pdf = \PDF::loadView('sessions.checkin-pass-pdf', [
            'session'    => $session,
            'qrBase64'   => base64_encode($qr),
            'qrType'     => $qrType,
            'checkinUrl' => $checkinUrl,
        ])->setPaper('a5', 'landscape');

        return $pdf->download("ventiq-checkin-{$session->id}.pdf");
    }

    public function participantsCount(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        $count = Participant::where('session_id', $session->id)->count();

        return response()->json(['count' => $count]);
    }

    public function start(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        // A session counts against the plan when it's held, once.
        if (!$session->counted_at) {
            $organization = $session->organization;
            if (!$this->quota->hasSessionQuota($organization)) {
                return response()->json([
                    'status'  => 'quota',
                    'message' => "You've used this month's sessions. Add more under Plan to start this one.",
                ], 402);
            }
            $this->quota->consumeSession($organization);
            $session->update(['counted_at' => now()]);
        }

        $session->start();

        $first = $session->segments()->orderBy('order')->first();
        $first?->start();

        return response()->json(['status' => 'ok', 'active_segment_id' => $first?->id]);
    }

    public function generateReport(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        $session->queueReportGeneration();

        return redirect()->route('sessions.report', $session)
            ->with('status', 'Generating your report — this takes a few seconds.');
    }

    public function report(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        // Last-opened updates every time, unconditionally — this is now
        // just a "have I looked at this recently" signal for a future
        // "Continue reviewing" surface, not a gate on review status.
        if ($session->status === 'reported') {
            $session->update(['report_last_opened_at' => now()]);
        }

        $session->load('segments');

        return Inertia::render('Sessions/Report', [
            'session' => [
                'id'       => $session->id,
                'title'    => $session->resolved_title,
                'date'     => ($session->date ?? $session->created_at)->format('d M Y'),
                'status'   => $session->status,
                'report'   => $session->session_report,
                'reviewed' => $session->reviewed_at?->format('d M Y, g:i A'),
                'tracking' => (bool) $session->event_id,
                'series'   => $session->series ? ['id' => $session->series->id, 'title' => $session->series->title] : null,
                'previous' => $session->parent_session_id && Session::whereKey($session->parent_session_id)->where('status', 'reported')->exists() ? $session->parent_session_id : null,
                // The report is written in the background; this says if that failed.
                'failed'   => $session->status === 'completed' && $session->report_job_id
                    && AiGenerationResult::where('job_id', $session->report_job_id)->value('status') === 'failed',
            ],
            'segments' => $session->segments->map(fn ($s) => [
                'id'      => $s->id,
                'name'    => $s->presenter_name,
                'role'    => $s->role,
                'summary' => $s->ai_summary['summary'] ?? null,
                'logs'    => collect($s->raw_log ?? [])->map(fn ($l) => ['time' => $l['time'] ?? '', 'text' => $l['text'] ?? ''])->values(),
            ])->values(),
            'meta' => ['title' => "{$session->resolved_title}: report | VENTIQ Sessions"],
        ])->withViewData(['noindex' => true]);
    }

    public function markReviewed(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->status === 'reported', 400);

        $session->update(['reviewed_at' => now()]);

        $organization = $session->organization;

        // Standalone from the thank-you+card, which already went out when
        // the session ended (see SessionSegmentController::finish()). This
        // is just "the notes are ready" — its own message, its own
        // idempotency column, so it never gets confused with a re-send of
        // the first one.
        $participants = \App\Models\Participant::where('session_id', $session->id)
            ->whereNull('report_notified_at')
            ->with('client')
            ->get();

        foreach ($participants as $participant) {
            // Email is a base feature on every tier, never quota-gated.
            if ($participant->client?->email) {
                \Illuminate\Support\Facades\Mail::to($participant->client->email)
                    ->send(new \App\Mail\SessionReportReadyMail($participant));
            }

            // No WhatsApp provider configured yet — simulate on the
            // frontend/logs so the flow is visible and testable now,
            // swap this line for a real provider call later without
            // touching anything else in this method.
            if ($participant->client?->phone) {
                if ($this->quota->hasWhatsappQuota($organization)) {
                    \Illuminate\Support\Facades\Log::info("[SIMULATED WHATSAPP] To {$participant->client->phone}: Notes for {$session->resolved_title} are ready.");
                    $this->quota->consumeWhatsapp($organization);
                } else {
                    \Illuminate\Support\Facades\Log::info("[SIMULATED WHATSAPP SKIPPED — quota exhausted] Would have notified {$participant->client->phone}");
                }
            }

            $participant->update(['report_notified_at' => now()]);
        }

        return back()->with('status', $participants->isNotEmpty()
            ? "Marked as reviewed — {$participants->count()} attendee(s) notified."
            : 'Marked as reviewed.');
    }

    public function reportStatus(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        $failed = $session->report_job_id
            ? \App\Models\AiGenerationResult::where('job_id', $session->report_job_id)->value('status') === 'failed'
            : false;

        return response()->json([
            'status' => $session->status,
            'ready'  => $session->status === 'reported',
            'failed' => $failed,
        ]);
    }

    public function updateReport(Request $request, Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        $validated = $request->validate(['session_report' => 'required|string']);
        $session->update(['session_report' => $validated['session_report']]);

        return response()->json(['status' => 'ok']);
    }

    public function reportPdf(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        $pdf = \PDF::loadView('sessions.report-pdf', ['session' => $session])->setPaper('a4', 'portrait');

        return $pdf->download("ventiq-report-{$session->id}.pdf");
    }
}