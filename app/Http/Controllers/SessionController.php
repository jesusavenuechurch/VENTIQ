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

class SessionController extends Controller
{
    public function __construct(private SessionQuotaService $quota) {}

    public function reports(Request $request)
    {
        // Super admins have no organization_id — Sessions is an org-scoped
        // area, so send them to the admin panel instead of crashing.
        if (!Auth::user()->organization_id) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        $tab = $request->query('tab', 'needs_review');

        $base = Session::forOrganization(Auth::user()->organization_id)->where('status', 'reported');

        $reports = (clone $base)
            ->when($tab === 'reviewed', fn ($q) => $q->whereNotNull('reviewed_at'))
            ->when($tab !== 'reviewed', fn ($q) => $q->whereNull('reviewed_at'))
            ->orderByDesc('date')
            ->paginate(15);

        $needsReviewCount = (clone $base)->whereNull('reviewed_at')->count();

        return view('sessions.reports', compact('reports', 'tab', 'needsReviewCount'));
    }

    public function index(Request $request)
    {
        // Super admins have no organization_id — Sessions is an org-scoped
        // area, so send them to the admin panel instead of crashing.
        if (!Auth::user()->organization_id) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        $sessions = Session::forOrganization(Auth::user()->organization_id)
            ->with('segments')
            ->latest()
            ->get();

        $organization = Auth::user()->organization;
        $plan         = $organization ? $this->quota->currentPlan($organization) : null;

        // Badge shows the subscription's own "this month" numbers — the
        // natural customer mental model. Any PAYG top-up is what keeps
        // hasSessionQuota() true even once these hit their limit.
        $sessionsUsed      = $plan->sessions_used ?? 0;
        $sessionsIncluded  = $plan->sessions_included ?? 0;
        $sessionsRemaining = $organization ? $this->quota->remainingSessions($organization) : 0;

        $sessionIds = $sessions->pluck('id');

        $participantCounts = $sessionIds->isEmpty()
            ? collect()
            : Participant::whereIn('session_id', $sessionIds)
                ->selectRaw('session_id, count(*) as aggregate')
                ->groupBy('session_id')
                ->pluck('aggregate', 'session_id');

        $sessions->each(function ($session) use ($participantCounts) {
            $session->attendee_count  = (int) ($participantCounts[$session->id] ?? 0);
            $session->presenter_count = $session->segments->count();
        });

        $readyToReview = $sessions->filter(fn ($s) => $s->status === 'reported' && !$s->reviewed_at);
        $happeningNow  = $sessions->filter(fn ($s) => $s->status === 'active');
        $comingUp      = $sessions->filter(fn ($s) => $s->status === 'draft');
        // Narrowed on purpose: this used to be "everything left over,"
        // which silently mixed "finished, no report yet" with "reported
        // and already reviewed" — two different things that need
        // different actions. Reported sessions (either state) now live
        // only in the permanent Reports archive, not here.
        $recentSessions = $sessions->filter(fn ($s) => $s->status === 'completed');

        $needsReviewCount = $readyToReview->count();
        $memberCount = $organization?->members()->count() ?? 0;
        $pendingInviteCount = $organization?->invites()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->count() ?? 0;

        return view('sessions.index', compact(
            'sessions', 'sessionsUsed', 'sessionsIncluded', 'sessionsRemaining', 'readyToReview', 'happeningNow', 'comingUp', 'recentSessions',
            'organization', 'memberCount', 'pendingInviteCount', 'needsReviewCount'
        ) + ['filter' => $request->query('filter')]);
    }

    public function create(Request $request)
    {
        $programme = $request->query('event')
            ? Event::where('id', $request->query('event'))
                ->where('organization_id', Auth::user()->organization_id)
                ->where('is_programme', true)
                ->first()
            : null;

        return view('sessions.create', compact('programme'));
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
                'quota' => "You've used your included sessions. Add more sessions with PAYG or upgrade your plan.",
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

        $session->load('segments');

        $participantCount = Participant::where('session_id', $session->id)->count();

        return view('sessions.show', [
            'session'          => $session,
            'segments'         => $session->segments,
            'participantCount' => $participantCount,
        ]);
    }

    public function checkinQr(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        if (!$session->public_token) {
            abort(404, 'This session isn\'t tracking participants — there\'s no check-in link to generate a QR code for.');
        }

        $url = route('public.session-checkin.form', $session->public_token);

        $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(400)->margin(1)->generate($url);

        return response($qr, 200)->header('Content-Type', 'image/png');
    }

    public function checkinPass(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->public_token, 404);

        return view('sessions.checkin-pass', ['session' => $session]);
    }

    public function checkinPassPdf(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->public_token, 404);

        $checkinUrl = route('public.session-checkin.form', $session->public_token);

        $qrBase64 = base64_encode(
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(400)->margin(1)->generate($checkinUrl)
        );

        $pdf = \PDF::loadView('sessions.checkin-pass-pdf', [
            'session'    => $session,
            'qrBase64'   => $qrBase64,
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

        return view('sessions.report', ['session' => $session]);
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