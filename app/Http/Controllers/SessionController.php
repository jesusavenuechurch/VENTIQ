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

        $sessions = Session::forOrganization($organization->id)
            ->with('segments')
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
            ];
        };

        // Reported sessions live in Reports once reviewed; finished ones
        // without a report yet are "Recently finished".
        $columns = [
            'ready'    => $sessions->filter(fn ($s) => $s->status === 'reported' && !$s->reviewed_at),
            'live'     => $sessions->filter(fn ($s) => $s->status === 'active'),
            'upcoming' => $sessions->filter(fn ($s) => $s->status === 'draft')->sortBy(fn ($s) => $s->date?->toDateString() . ' ' . $s->start_time),
            'recent'   => $sessions->filter(fn ($s) => $s->status === 'completed'),
        ];

        return Inertia::render('Sessions/Index', [
            'columns'        => collect($columns)->map(fn ($items) => $items->map($tile)->values()),
            'filter'         => in_array($request->query('filter'), array_keys($columns), true) ? $request->query('filter') : null,
            'members'        => $organization->members()->count(),
            'pendingInvites' => $organization->invites()->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
            'meta'           => ['title' => 'Sessions | VENTIQ'],
        ]);
    }

    public function create(Request $request)
    {
        $programme = $request->query('event')
            ? Event::where('id', $request->query('event'))
                ->where('organization_id', Auth::user()->organization_id)
                ->where('is_programme', true)
                ->first()
            : null;

        return Inertia::render('Sessions/Create', [
            'types' => collect(\App\Support\SessionType::keys())->map(fn ($key) => [
                'key'   => $key,
                'label' => \App\Support\SessionType::label($key),
                'roles' => collect(\App\Support\SessionType::roles($key))
                    ->map(fn ($r, $value) => ['value' => $value, 'label' => $r['label'], 'presenting' => $r['presenting']])
                    ->values(),
            ]),
            'programme' => $programme ? ['id' => $programme->id, 'name' => $programme->name] : null,
            'today'     => now()->toDateString(),
            'meta'      => ['title' => 'Schedule a session | VENTIQ'],
        ]);
    }

    public function store(Request $request)
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
        ]);

        $organization = Auth::user()->organization;

        if (!$this->quota->hasSessionQuota($organization)) {
            return back()->withErrors([
                'quota' => "You've used this month's sessions. Add more under Plan, or move to a bigger plan.",
            ])->withInput();
        }

        $trackParticipants = $request->boolean('track_participants');

        $sessionDate = $validated['date'] ?? now()->toDateString();
        $sessionTime = $validated['start_time'] ?? now()->format('H:i');

        $people = collect($validated['people'] ?? [])
            ->map(fn ($p) => [
                'name'       => trim($p['name'] ?? ''),
                'role'       => $p['role'] ?? null,
                'presenting' => filter_var($p['presenting'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ])
            ->filter(fn ($p) => $p['name'] !== '')
            ->values();

        $session = Session::create([
            'organization_id' => Auth::user()->organization_id,
            'created_by'      => Auth::id(),
            'type'            => $validated['type'],
            'title'           => $validated['title'],
            'date'            => $sessionDate,
            'start_time'      => $sessionTime,
            'status'          => 'draft',
            'meta'            => [
                'expected_duration_minutes' => $validated['expected_duration'] ?? null,
                'expected_participants'     => $trackParticipants ? ($validated['expected_participants'] ?? null) : null,
                'custom_type_label'         => $validated['type'] === 'custom' ? ($validated['custom_type_label'] ?? null) : null,
            ],
        ]);

        $this->quota->consumeSession($organization);

        if (!empty($validated['programme_id'])) {
            // Attaching to an existing Programme — verify ownership,
            // never trust the posted id blindly. No new Event, no new
            // public_token logic here: check-in still happens per
            // Session (see the open Participant/event_id question),
            // this just links the session into the Programme's Event.
            $programme = Event::where('id', $validated['programme_id'])
                ->where('organization_id', Auth::user()->organization_id)
                ->where('is_programme', true)
                ->first();

            if ($programme) {
                $session->update(['event_id' => $programme->id]);
                if ($trackParticipants) {
                    $session->update(['public_token' => Str::random(32), 'session_code' => Session::generateSessionCode()]);
                }
            }
        } elseif ($trackParticipants) {
            $event = Event::create([
                'organization_id' => Auth::user()->organization_id,
                'kind'            => Event::KIND_SESSION,
                'name'            => $validated['title'],
                'event_date'      => "{$sessionDate} {$sessionTime}",
                'is_public'       => false,
            ]);
            $session->update(['event_id' => $event->id, 'public_token' => Str::random(32), 'session_code' => Session::generateSessionCode()]);
        }

        foreach ($people as $i => $p) {
            $session->segments()->create([
                'presenter_name' => $p['name'],
                'role'           => $p['role'],
                'is_presenting'  => $p['presenting'],
                'order'          => $i,
                'status'         => 'upcoming',
            ]);
        }

        // Scheduled for today (or left blank, which defaults to today) —
        // the organizer almost certainly means "start now," so drop them
        // straight into capture. Scheduled for a future date — there's
        // nothing to capture yet, so send them back to the desk where
        // it'll show up under "Coming Up" instead.
        if ($sessionDate === now()->toDateString()) {
            return redirect()->route('sessions.show', $session);
        }

        return redirect()->route('sessions.index')
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