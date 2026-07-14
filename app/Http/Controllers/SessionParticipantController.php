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
            ->where('event_id', $session->event_id)
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
        ]);

        // Same dedupe convention as RegistrationController — key on phone
        // when we have one, so a repeat attendee collapses into their
        // existing Client instead of creating a duplicate.
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

        $participant = Participant::firstOrNew([
            'event_id'  => $session->event_id,
            'client_id' => $client->id,
        ]);
        $participant->organization_id = $session->organization_id;
        $participant->role            = 'attendee';
        $participant->source          = 'walk_in';
        $participant->attended_at     = now();
        $participant->save();

        return redirect()->route('sessions.checkin', $session);
    }
}