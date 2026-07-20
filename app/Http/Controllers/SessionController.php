<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\Event;
use App\Models\Participant;
use App\Models\AiGenerationResult;
use App\Jobs\GenerateSessionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function index()
    {
        // Eager-load segments once here — the sticky notes need a
        // presenter count and (for live sessions) which segment is
        // active. Without this, each sticky would trigger its own
        // query when it touches $session->segments.
        $sessions = Session::forOrganization(Auth::user()->organization_id)
            ->with('segments')
            ->latest()
            ->get();

        $hasFeature = Auth::user()->organization?->activePackages()
            ->get()
            ->contains(fn ($p) => $p->hasFeature('organizational_records')) ?? false;

        // Attendee counts, batched in one query across every session
        // that tracks participants — instead of a Participant::count()
        // per sticky, which would be one query per session on this page.
        $eventIds = $sessions->pluck('event_id')->filter()->unique()->values();

        $participantCounts = $eventIds->isEmpty()
            ? collect()
            : Participant::whereIn('event_id', $eventIds)
                ->selectRaw('event_id, count(*) as aggregate')
                ->groupBy('event_id')
                ->pluck('aggregate', 'event_id');

        // Attach both as plain attributes so the view/partials can just
        // read $session->attendee_count / $session->presenter_count —
        // no guessing at relation names inside the blade files.
        $sessions->each(function ($session) use ($participantCounts) {
            $session->attendee_count  = $session->event_id ? (int) ($participantCounts[$session->event_id] ?? 0) : null;
            $session->presenter_count = $session->segments->count();
        });

        $readyToReview = $sessions->filter(fn ($s) => $s->status === 'reported' && !$s->report_last_opened_at);
        $happeningNow  = $sessions->filter(fn ($s) => $s->status === 'active');
        $comingUp      = $sessions->filter(fn ($s) => $s->status === 'draft');
        $recentSessions = $sessions->diff($readyToReview)->diff($happeningNow)->diff($comingUp);

        return view('sessions.index', compact('sessions', 'hasFeature', 'readyToReview', 'happeningNow', 'comingUp', 'recentSessions'));
    }

    public function create()
    {
        // Presentation only for now — other types render disabled in the
        // picker until they're actually built.
        return view('sessions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'                 => 'required|string|max:255',
            'presenters'            => 'nullable|array',
            'presenters.*'          => 'nullable|string|max:255',
            'expected_duration'     => 'nullable|integer|min:1',
            'judges'                => 'nullable|array',
            'judges.*'              => 'nullable|string|max:255',
            'track_participants'    => 'nullable|boolean',
            'expected_participants' => 'nullable|integer|min:1',
        ]);

        $trackParticipants = $request->boolean('track_participants');

        $presenterNames = collect($validated['presenters'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();

        $session = Session::create([
            'organization_id' => Auth::user()->organization_id,
            'created_by'      => Auth::id(),
            'type'            => 'presentation',
            'title'           => $validated['title'],
            // Created for later, on purpose — status stays 'draft' until
            // the workspace's own "Begin Capturing" action starts it.
            'status'          => 'draft',
            'meta'            => [
                'expected_duration_minutes' => $validated['expected_duration'] ?? null,
                'judges'                    => array_values(array_filter($validated['judges'] ?? [])),
                'expected_participants'     => $trackParticipants ? ($validated['expected_participants'] ?? null) : null,
            ],
        ]);

        if ($trackParticipants) {
            $event = Event::create([
                'organization_id' => Auth::user()->organization_id,
                'name'            => $validated['title'],
                'event_date'      => now(),
                'is_public'       => false,
            ]);

            $session->update([
                'event_id'     => $event->id,
                'public_token' => Str::random(32),
            ]);
        }

        foreach ($presenterNames as $i => $name) {
            $session->segments()->create([
                'presenter_name' => $name,
                'order'          => $i,
                'status'         => 'upcoming',
            ]);
        }

        // Straight into the workspace, no interstitial screen — QR and
        // the copy link live in the sidebar itself when tracking is on.
        return redirect()->route('sessions.show', $session);
    }

    public function show(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        $session->load('segments');

        $participantCount = $session->event_id
            ? Participant::where('event_id', $session->event_id)->count()
            : 0;

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

        $count = $session->event_id
            ? Participant::where('event_id', $session->event_id)->count()
            : 0;

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

        // First view only — this is the entire "has anyone seen this"
        // mechanism for now. Opening it is what clears it from
        // "Ready to Review"; nothing else changes.
        if ($session->status === 'reported' && !$session->report_last_opened_at) {
            $session->update(['report_last_opened_at' => now()]);
        }

        $session->load('segments');

        return view('sessions.report', ['session' => $session]);
    }

    public function reportStatus(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);

        return response()->json([
            'status' => $session->status,
            'ready'  => $session->status === 'reported',
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
