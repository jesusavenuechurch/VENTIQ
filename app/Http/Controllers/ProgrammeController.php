<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Session;
use App\Services\SessionQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProgrammeController extends Controller
{
    public function __construct(private SessionQuotaService $quota) {}

    /**
     * A "Programme" isn't a new table — it's any Event with is_programme
     * true. Ticketed events have their own form in the organizer area; this
     * is purely the lightweight, internal grouping layer.
     */
    public function index()
    {
        $programmes = Event::where('organization_id', Auth::user()->organization_id)
            ->where('is_programme', true)
            ->withCount('sessions')
            ->latest('event_date')
            ->get();

        return Inertia::render('Programmes/Index', [
            'programmes' => $programmes->map(fn (Event $p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'dates'    => $this->dates($p),
                'sessions' => $p->sessions_count,
            ]),
            'canCreate' => $this->quota->meetsMinimumTier(Auth::user()->organization, 'business'),
            'meta'      => ['title' => 'Programmes | VENTIQ Sessions'],
        ]);
    }

    public function create()
    {
        // Gate before they ever see the form — filling out a whole
        // Programme only to be told on submit that their plan doesn't
        // allow it is the wrong place to find that out.
        if (!$this->quota->meetsMinimumTier(Auth::user()->organization, 'business')) {
            return redirect()->route('programmes.index')->withErrors([
                'plan' => 'Programmes require the Business plan or above. Upgrade to unlock this feature.',
            ]);
        }

        return Inertia::render('Programmes/Create', [
            'today' => now()->toDateString(),
            'meta'  => ['title' => 'New programme | VENTIQ Sessions'],
        ]);
    }

    public function store(Request $request)
    {
        if (!$this->quota->meetsMinimumTier(Auth::user()->organization, 'business')) {
            return back()->withErrors([
                'plan' => 'Programmes require the Business plan or above. Upgrade to unlock this feature.',
            ]);
        }

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'start_date'            => 'required|date',
            'end_date'              => 'nullable|date|after_or_equal:start_date',
            'venue'                 => 'nullable|string|max:255',
            'certificates_enabled'  => 'nullable|boolean',
            'signature_capture_enabled' => 'nullable|boolean',
        ]);

        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end = !empty($validated['end_date']) ? \Carbon\Carbon::parse($validated['end_date']) : $start;
        $durationDays = $start->diffInDays($end) + 1;

        $programme = Event::create([
            'organization_id'      => Auth::user()->organization_id,
            'name'                 => $validated['name'],
            'event_date'           => $start,
            'duration_days'        => $durationDays,
            'venue'                => $validated['venue'] ?? null,
            'is_public'            => false,
            'is_programme'         => true,
            'certificates_enabled' => $request->boolean('certificates_enabled'),
            'signature_capture_enabled' => $request->boolean('signature_capture_enabled'),
        ]);

        return redirect()->route('programmes.show', $programme)
            ->with('status', "\"{$programme->name}\" is set up — add your first session below.");
    }

    public function show(Event $programme)
    {
        abort_unless($programme->organization_id === Auth::user()->organization_id, 403);
        abort_unless($programme->is_programme, 404); // not a Programme — it's a ticketed event, wrong controller

        $sessions = Session::where('event_id', $programme->id)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $progress = app(\App\Services\Sessions\ProgrammeProgress::class);
        $board = $progress->board($programme);

        return Inertia::render('Programmes/Show', [
            'programme' => [
                'id'           => $programme->id,
                'name'         => $programme->name,
                'dates'        => $this->dates($programme),
                'venue'        => $programme->venue,
                'certificates' => (bool) $programme->certificates_enabled,
                'minDays'      => $programme->certificate_min_days,
                'enrolOpen'    => (bool) $programme->enrolment_open,
                'enrolUrl'     => $progress->link($programme),
            ],
            'sessions' => $sessions->map(fn (Session $s) => [
                'id'     => $s->id,
                'title'  => $s->resolved_title,
                'when'   => trim(($s->date?->format('d M Y') ?? '') . ($s->start_time ? ' · ' . \Carbon\Carbon::parse($s->start_time)->format('g:i A') : '')),
                'status' => $s->status,
            ]),
            // Who's on it, the days each came to, and their certificate.
            'board'    => $board,
            'types'    => \App\Support\SessionType::forForm(),
            'today'    => now()->toDateString(),
            'openSchedule' => (bool) session('schedule'),
            'meta'     => ['title' => "{$programme->name} | VENTIQ Sessions"],
        ]);
    }

    /** Every day of the programme on one participant list, a column per day. */
    public function participantList(Request $request, Event $programme, \App\Services\Sessions\ParticipantList $lists)
    {
        abort_unless($programme->organization_id === Auth::user()->organization_id, 403);
        abort_unless($programme->is_programme, 404);

        return \PDF::loadView('sessions.participant-list-pdf', [
            'list'  => $lists->forProgramme($programme, Auth::user()),
            'blank' => SessionParticipantController::blankRows($request),
        ])->setPaper('a4', 'landscape')->download('participant-list-' . \Illuminate\Support\Str::slug($programme->name) . '.pdf');
    }

    /** Issue now, rather than waiting for the evening: to everyone who meets the rule. */
    public function issueCertificates(Event $programme)
    {
        abort_unless($programme->organization_id === Auth::user()->organization_id, 403);
        abort_unless($programme->certificates_enabled, 400);

        $issued = app(\App\Services\Sessions\ProgrammeProgress::class)->issueDue($programme)->count();

        return back()->with('status', $issued > 0
            ? "{$issued} " . ($issued === 1 ? 'certificate' : 'certificates') . ' issued and emailed.'
            : 'Everyone who meets the rule already has their certificate.');
    }

    public function generateReport(Event $programme)
    {
        abort_unless($programme->organization_id === Auth::user()->organization_id, 403);

        $reportedCount = Session::where('event_id', $programme->id)->where('status', 'reported')->count();
        abort_if($reportedCount === 0, 400, 'No sessions in this Programme have a completed report yet.');

        $jobId = (string) \Illuminate\Support\Str::uuid();

        \App\Models\AiGenerationResult::create([
            'job_id'  => $jobId,
            'user_id' => Auth::id(),
            'type'    => 'programme_report',
            'status'  => 'pending',
            'payload' => json_encode(['programme_id' => $programme->id]),
        ]);

        $programme->update(['programme_report_job_id' => $jobId]);

        \App\Jobs\GenerateProgrammeReport::dispatch($jobId, $programme->id);

        return redirect()->route('programmes.report', $programme)
            ->with('status', 'Generating the Programme report — this takes a few seconds.');
    }

    public function report(Event $programme)
    {
        abort_unless($programme->organization_id === Auth::user()->organization_id, 403);

        $sessions = Session::where('event_id', $programme->id)->orderBy('date')->orderBy('start_time')->get();
        $reportedCount = $sessions->where('status', 'reported')->count();
        $job = $programme->programme_report_job_id
            ? \App\Models\AiGenerationResult::where('job_id', $programme->programme_report_job_id)->value('status')
            : null;

        return Inertia::render('Programmes/Report', [
            'programme' => [
                'id'         => $programme->id,
                'name'       => $programme->name,
                'report'     => $programme->programme_report,
                'generating' => in_array($job, ['pending', 'processing'], true),
                'failed'     => $job === 'failed',
            ],
            'reported' => $reportedCount,
            'total'    => $sessions->count(),
            'meta'     => ['title' => "{$programme->name}: report | VENTIQ Sessions"],
        ])->withViewData(['noindex' => true]);
    }

    /** "12 Mar" or "12 – 14 Mar 2026". */
    private function dates(Event $programme): ?string
    {
        if (!$programme->event_date) {
            return null;
        }

        return $programme->duration_days > 1
            ? $programme->event_date->format('d M') . ' – ' . $programme->event_date->copy()->addDays($programme->duration_days - 1)->format('d M Y')
            : $programme->event_date->format('d M Y');
    }

    public function reportStatus(Event $programme)
    {
        abort_unless($programme->organization_id === Auth::user()->organization_id, 403);

        $failed = $programme->programme_report_job_id
            ? \App\Models\AiGenerationResult::where('job_id', $programme->programme_report_job_id)->value('status') === 'failed'
            : false;

        return response()->json([
            'ready'  => (bool) $programme->programme_report_generated_at
                && \App\Models\AiGenerationResult::where('job_id', $programme->programme_report_job_id)->value('status') === 'completed',
            'failed' => $failed,
        ]);
    }
}