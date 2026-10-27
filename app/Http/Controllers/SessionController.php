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
use App\Support\SessionType;
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
            'people.*.title'        => 'nullable|string|max:255',
            'people.*.role'         => 'nullable|string|max:255',
            'people.*.presenting'   => 'nullable|boolean',
            // A meeting's chair, secretary and venue.
            'chair'                 => 'nullable|string|max:255',
            'secretary'             => 'nullable|string|max:255',
            'location'              => 'nullable|string|max:255',
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
                // A meeting: its parts are agenda items, and it's minuted.
                'agenda'       => \App\Support\SessionType::takesMinutes($session->type),
                'chair'        => $session->meta['chair'] ?? null,
                'secretary'    => $session->meta['secretary'] ?? null,
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
            'carried' => $this->carried($session),
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
        return \App\Support\Qr::make($url);
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

        if ($stopped = $this->begin($session)) {
            return response()->json(['status' => 'quota', 'message' => $stopped], 402);
        }

        return response()->json(['status' => 'ok', 'active_segment_id' => $session->segments()->where('status', 'active')->value('id')]);
    }

    /**
     * A quick catch-up: a meeting started now, with nothing to fill in.
     * The one starting it chairs it and leads its one item; more items can
     * be added in the console. Straight into the live notes.
     */
    public function quick(\App\Services\Sessions\SessionMaker $maker)
    {
        $user = Auth::user();
        $session = $maker->make($user->organization, $user, [
            'title'      => 'Quick catch-up',
            'type'       => 'meeting',
            'date'       => now()->toDateString(),
            'start_time' => now()->format('H:i'),
            'chair'      => $user->name,
            'people'     => [['title' => 'Catch-up', 'name' => $user->name]],
        ]);

        if ($stopped = $this->begin($session)) {
            return redirect()->route('sessions.show', $session)->with('error', $stopped);
        }

        return redirect()->route('sessions.show', $session);
    }

    /** Held: counted against the plan (once), started, and its first part live. Why not, when it can't be. */
    private function begin(Session $session): ?string
    {
        // A session counts against the plan when it's held, once.
        if (!$session->counted_at) {
            $organization = $session->organization;
            if (!$this->quota->hasSessionQuota($organization)) {
                return "You've used this month's sessions. Add more under Plan to start this one.";
            }
            $this->quota->consumeSession($organization);
            $session->update(['counted_at' => now()]);
            $billing = app(\App\Services\Sessions\CorporateBilling::class);
            if ($agreement = $billing->agreement($organization)) {
                $billing->charge($session, $agreement);
            }
        }

        $session->start();
        $session->segments()->orderBy('order')->first()?->start();

        return null;
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
                'findingActions' => ($session->meta['action_points'] ?? null) === 'finding',
                'takesMinutes'   => SessionType::takesMinutes($session->type),
                'previous' => $session->parent_session_id && Session::whereKey($session->parent_session_id)->where('status', 'reported')->exists() ? $session->parent_session_id : null,
                // The report is written in the background; this says if that failed.
                'failed'   => $session->status === 'completed' && $session->report_job_id
                    && AiGenerationResult::where('job_id', $session->report_job_id)->value('status') === 'failed',
            ],
            'segments' => $session->segments->map(fn ($s) => [
                'id'      => $s->id,
                // A meeting's agenda item by its item, and who led it.
                'name'    => SessionType::takesMinutes($session->type) ? ($s->title ?: $s->presenter_name) : $s->presenter_name,
                'role'    => SessionType::takesMinutes($session->type) ? ($s->lead() ? "Led by {$s->lead()}" : null) : $s->role,
                'summary' => $s->ai_summary['summary'] ?? null,
                'topic'   => $s->title,
                'logs'    => collect($s->raw_log ?? [])->map(fn ($l) => ['time' => $l['time'] ?? '', 'text' => $l['text'] ?? ''])->values(),
            ])->values(),
            // What was agreed here, and what came in still open from the
            // series' earlier meetings (and whether it was done here).
            'actionPoints' => $session->actionPoints()->whereIn('status', ['proposed', 'open', 'done'])->with(['ownerUser', 'ownerClient'])->orderBy('id')->get()->map->toCard(),
            'carried'      => $this->carried($session),
            'owners'       => \App\Http\Controllers\ActionPointsController::owners($session),
            // A meeting's report is its minutes.
            'minutes'      => SessionType::takesMinutes($session->type) && $session->status === 'reported'
                ? app(\App\Services\Sessions\MinutesBuilder::class)->build($session)
                : null,
            // The Summary and numbers tab.
            'numbers'      => $session->status === 'reported' ? app(\App\Services\Sessions\SessionNumbers::class)->for($session) : null,
            'meta' => ['title' => "{$session->resolved_title}: " . (SessionType::takesMinutes($session->type) ? 'minutes' : 'report') . ' | VENTIQ Sessions'],
        ])->withViewData(['noindex' => true]);
    }

    public function markReviewed(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->status === 'reported', 400);

        // For a meeting this confirms its minutes, signed off by whoever reviewed them.
        $session->update(['reviewed_at' => now(), 'meta' => array_merge($session->meta ?? [], ['reviewed_by' => Auth::user()->name])]);
        // Reviewing the report confirms the action points proposed for it.
        $session->actionPoints()->where('status', 'proposed')->update(['status' => 'open']);

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

    /**
     * A change to one part of a meeting's minutes: who was there, apologies,
     * the venue, any other business, the next meeting, or an item's
     * discussion and decisions. The rest stays as assembled.
     */
    public function updateMinutes(Request $request, Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless(SessionType::takesMinutes($session->type) && $session->status === 'reported', 409, 'Only a held meeting has minutes.');

        $data = $request->validate([
            'present'                => 'sometimes|nullable|string|max:10000',
            'apologies'              => 'sometimes|nullable|string|max:10000',
            'aob'                    => 'sometimes|nullable|string|max:20000',
            'next'                   => 'sometimes|nullable|string|max:255',
            'venue'                  => 'sometimes|nullable|string|max:255',
            'items'                  => 'sometimes|array',
            'items.*.discussion'     => 'sometimes|nullable|string|max:20000',
            'items.*.decisions'      => 'sometimes|nullable|string|max:20000',
        ]);

        $minutes = $session->minutes ?? [];
        foreach (\App\Services\Sessions\MinutesBuilder::FIELDS as $field) {
            if ($request->exists($field)) {
                $minutes[$field] = (string) ($data[$field] ?? '');
            }
        }
        $ids = $session->segments()->pluck('id')->all();
        foreach ($data['items'] ?? [] as $id => $item) {
            if (!in_array((int) $id, $ids, true)) {
                continue;
            }
            foreach (['discussion', 'decisions'] as $part) {
                if (array_key_exists($part, $item)) {
                    $minutes['items'][$id][$part] = (string) ($item[$part] ?? '');
                }
            }
        }

        $session->minutes = $minutes;
        if ($request->exists('venue')) {
            $session->location = trim($data['venue'] ?? '') ?: null;
        }
        $session->save();

        return back();
    }

    public function reportPdf(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        if (SessionType::takesMinutes($session->type) && $session->status === 'reported') {
            $minutes = app(\App\Services\Sessions\MinutesBuilder::class)->build($session);

            return \PDF::loadView('sessions.minutes-pdf', ['minutes' => $minutes, 'logo' => \App\Support\Logo::of($session->organization)])->setPaper('a4', 'portrait')
                ->download('minutes-' . \Illuminate\Support\Str::slug($session->resolved_title) . '-' . $session->date->format('Y-m-d') . '.pdf');
        }

        $pdf = \PDF::loadView('sessions.report-pdf', ['session' => $session, 'logo' => \App\Support\Logo::of($session->organization)])->setPaper('a4', 'portrait');

        return $pdf->download("ventiq-report-{$session->id}.pdf");
    }

    /**
     * The full report, in one PDF: a cover, the summary and numbers, the
     * report (a meeting's minutes), the notes it was written from, and
     * the attendance register. For a funder, a board or the file.
     */
    public function fullReport(Session $session, \App\Services\Sessions\SessionNumbers $numbers, \App\Services\Sessions\ParticipantList $lists)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->status === 'reported', 404);

        $session->load(['segments', 'organization', 'creator', 'event']);
        $minuted = SessionType::takesMinutes($session->type);
        $minutes = $minuted ? app(\App\Services\Sessions\MinutesBuilder::class)->build($session) : null;

        $pdf = \PDF::loadView('sessions.full-report-pdf', [
            'session'  => $session,
            'type'     => $session->type === 'custom' && !empty($session->meta['custom_type_label']) ? $session->meta['custom_type_label'] : SessionType::label($session->type),
            'numbers'  => $numbers->for($session),
            'minutes'  => $minutes,
            'summary'  => $minuted
                ? collect($minutes['items'])->map(fn ($i) => ['label' => $i['title'], 'text' => $i['discussion']])->all()
                : $session->segments->where('is_presenting', true)->map(fn ($s) => ['label' => $s->title ?: $s->presenter_name, 'text' => $s->ai_summary['summary'] ?? null])->values()->all(),
            'items'    => $session->segments->where('is_presenting', true)->sortBy('order')->values(),
            'register' => $session->event_id ? $lists->forSession($session, Auth::user()) : null,
            // Without check-in: who the minutes (or the session's people) say was there, to sign.
            'present'  => $session->event_id ? [] : collect($minuted ? $minutes['present'] : $session->segments->sortBy('order')->map(fn ($s) => $s->presenter_name . ($s->role ? ' (' . (SessionType::roles($session->type)[$s->role]['label'] ?? ucfirst($s->role)) . ')' : '')))
                ->unique()->map(fn ($p) => preg_match('/^(.*?)\s*\(([^)]+)\)$/', $p, $m) ? ['name' => $m[1], 'role' => $m[2]] : ['name' => $p, 'role' => null])->values()->all(),
            'logo'     => \App\Support\Logo::of($session->organization),
            'produced' => now()->format('j F Y, H:i'),
            'by'       => Auth::user()->name,
        ])->setPaper('a4', 'portrait');
        // Page numbers, once the pages are known.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(496, 818, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 6, [0.58, 0.64, 0.72]);

        return $pdf->download('full-report-' . \Illuminate\Support\Str::slug($session->resolved_title) . '-' . ($session->date ?? $session->created_at)->format('Y-m-d') . '.pdf');
    }

    /** In a series: the action points carried into this meeting (see ActionPoint::carriedInto()). */
    private function carried(Session $session): array
    {
        return \App\Models\ActionPoint::carriedInto($session)->map->toCard()->all();
    }
}
