<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Participant;
use App\Models\Session;
use Illuminate\Http\Request;

class PublicSessionCheckinController extends Controller
{
    public function show(string $token)
    {
        $session = Session::where('public_token', $token)->firstOrFail();

        return view('public.session-checkin', ['session' => $session]);
    }

    public function store(Request $request, string $token)
    {
        $session = Session::where('public_token', $token)->firstOrFail();
        abort_unless($session->event_id, 404);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:255',
            'institution' => 'nullable|string|max:255',
            'position'    => 'nullable|string|max:255'
        ]);

        if (!empty($validated['phone'])) {
            $client = Client::firstOrCreate(
                ['phone' => $validated['phone'], 'organization_id' => $session->organization_id],
                ['full_name' => $validated['full_name'], 'email' => $validated['email'] ?? null, 'status' => 'active']
            );
            if (!$client->wasRecentlyCreated && $client->full_name !== $validated['full_name']) {
                $client->update(['full_name' => $validated['full_name']]);
            }
        } else {
            $client = Client::create([
                'organization_id' => $session->organization_id,
                'full_name'       => $validated['full_name'],
                'email'           => $validated['email'] ?? null,
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
        $participant->institution = $validated['institution'] ?? null;
        $participant->position    = $validated['position']    ?? null;
        $participant->save();

        return view('public.session-checkin', [
            'session' => $session,
            'success' => true,
            'name'    => $client->full_name,
        ]);
    }
}