<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Session;
use App\Services\SessionQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgrammeController extends Controller
{
    public function __construct(private SessionQuotaService $quota) {}

    /**
     * A "Programme" isn't a new table — it's any Event with is_programme
     * true. Ticketed events keep using the Filament wizard untouched; this
     * is purely the lightweight, internal grouping layer.
     */
    public function index()
    {
        $programmes = Event::where('organization_id', Auth::user()->organization_id)
            ->where('is_programme', true)
            ->withCount('sessions')
            ->latest('event_date')
            ->get();

        return view('programmes.index', compact('programmes'));
    }

    public function create()
    {
        return view('programmes.create');
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

        $issuedCertificates = $programme->certificates_enabled
            ? $programme->certificates()->with('client')->get()
            : collect();

        // Eligible = checked into at least one session in this Programme,
        // dedup'd by client. "At least one" is the default rule for now —
        // an explicit call, not a hidden assumption: revisit if you want
        // an attendance threshold instead (e.g. all sessions, or a %).
        $eligibleCount = \App\Models\Participant::whereIn('session_id', $sessions->pluck('id'))
            ->whereNotNull('attended_at')
            ->distinct('client_id')
            ->count('client_id');

        return view('programmes.show', compact('programme', 'sessions', 'issuedCertificates', 'eligibleCount'));
    }

    public function issueCertificates(Event $programme)
    {
        abort_unless($programme->organization_id === Auth::user()->organization_id, 403);
        abort_unless($programme->certificates_enabled, 400);

        $sessionIds = Session::where('event_id', $programme->id)->pluck('id');

        $clientIds = \App\Models\Participant::whereIn('session_id', $sessionIds)
            ->whereNotNull('attended_at')
            ->distinct()
            ->pluck('client_id');

        $issued = 0;
        foreach ($clientIds as $clientId) {
            $cert = \App\Models\Certificate::firstOrNew([
                'event_id'  => $programme->id,
                'client_id' => $clientId,
            ]);
            if (!$cert->exists) {
                $cert->organization_id = $programme->organization_id;
                $cert->issued_at = now();
                $cert->save();
                $issued++;

                if ($cert->client?->email) {
                    \Illuminate\Support\Facades\Mail::to($cert->client->email)
                        ->send(new \App\Mail\CertificateIssuedMail($cert));
                }
            }
        }

        return back()->with('status', $issued > 0
            ? "{$issued} certificate(s) issued."
            : 'Everyone eligible already has a certificate.');
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

        return view('programmes.report', compact('programme', 'sessions', 'reportedCount'));
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