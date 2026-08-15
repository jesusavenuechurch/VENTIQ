<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Participant;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionParticipantController extends Controller
{
    public function index(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->event_id, 404);

        $participants = Participant::with('client')
            ->where('session_id', $session->id)
            ->latest('attended_at')
            ->get();

        return view('sessions.checkin', compact('session', 'participants'));
    }

    public function store(Request $request, Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->event_id, 404);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:255',
            'position'    => 'nullable|string|max:255',
        ]);

        if (!empty($validated['phone'])) {
            $client = Client::firstOrCreate(
                ['phone' => $validated['phone'], 'organization_id' => $session->organization_id],
                ['full_name' => $validated['full_name'], 'status' => 'active']
            );
            if (!$client->wasRecentlyCreated && $client->full_name !== $validated['full_name']) {
                $client->update(['full_name' => $validated['full_name']]);
            }
        } else {
            $client = Client::create([
                'organization_id' => $session->organization_id,
                'full_name'       => $validated['full_name'],
                'status'          => 'active',
            ]);
        }

        // FIX: was keyed on (event_id, client_id) — same corruption as
        // the public check-in flow. A staff member manually re-adding a
        // Day 1 attendee's info on Day 2 would silently overwrite Day 1's
        // record instead of creating Day 2's. Session-scoped now.
        $participant = Participant::firstOrNew([
            'session_id' => $session->id,
            'client_id'  => $client->id,
        ]);
        $participant->organization_id = $session->organization_id;
        $participant->event_id        = $session->event_id;
        $participant->role            = 'attendee';
        $participant->source          = 'walk_in';
        $participant->attended_at     = now();
        $participant->institution     = $validated['institution'] ?? null;
        $participant->position        = $validated['position'] ?? null;
        $participant->save();

        return redirect()->route('sessions.checkin', $session);
    }

    public function update(Request $request, Session $session, Participant $participant)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($participant->session_id === $session->id, 404);

        $validated = $request->validate([
            'full_name'   => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:255',
            'position'    => 'nullable|string|max:255',
        ]);

        $participant->client->update(['full_name' => $validated['full_name'], 'phone' => $validated['phone'] ?? null]);
        $participant->update([
            'institution' => $validated['institution'] ?? null,
            'position'    => $validated['position'] ?? null,
        ]);

        return redirect()->route('sessions.checkin', $session);
    }

    public function exportPdf(Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->event_id, 404);

        $participants = Participant::with('client')
            ->where('session_id', $session->id)
            ->orderBy('attended_at')
            ->get();

        $pdf = \PDF::loadView('sessions.participants-pdf', [
            'session'      => $session,
            'participants' => $participants,
            'orgName'      => $session->organization?->name ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->download("ventiq-participants-{$session->id}.pdf");
    }

    public function card(Session $session, Participant $participant)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($participant->session_id === $session->id, 404);

        return view('sessions.attendance-card', [
            'session'     => $session,
            'participant' => $participant,
            'orgName'     => $session->organization?->name ?? '',
        ]);
    }

    public function cardPdf(Session $session, Participant $participant)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($participant->session_id === $session->id, 404);

        $pdf = \PDF::loadView('sessions.attendance-card', [
            'session'     => $session,
            'participant' => $participant,
            'orgName'     => $session->organization?->name ?? '',
        ])->setPaper([0, 0, 595, 335], 'landscape');

        return $pdf->download("ventiq-attendance-{$participant->id}.pdf");
    }
}