<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Participant;
use App\Models\Session;
use Illuminate\Http\Request;

class PublicSessionCheckinController extends Controller
{
 public function show(Request $request, string $token)
{
    $session = Session::where('public_token', $token)->firstOrFail();

    $existing = null;

    if ($request->filled('search')) {
        $search = trim($request->query('search'));

        $client = Client::where('organization_id', $session->organization_id)
            ->where(function ($q) use ($search) {
                $q->where('email', $search)
                  ->orWhere('phone', $search);
            })
            ->first();

        if ($client) {
            $existing = Participant::where('event_id', $session->event_id)
                ->where('client_id', $client->id)
                ->with('client')
                ->first();
        }
    }

    return view('public.session-checkin', ['session' => $session, 'existing' => $existing]);
}

public function store(Request $request, string $token)
{
    $session = Session::where('public_token', $token)->firstOrFail();
    abort_unless($session->event_id, 404);

    $validated = $request->validate([
        'full_name'   => 'required|string|max:255',
        'email'       => 'required|email|max:255',
        'phone'       => 'nullable|string|max:20',
        'institution' => 'nullable|string|max:255',
        'position'    => 'nullable|string|max:255',
    ]);

    $client = Client::firstOrCreate(
        ['email' => $validated['email'], 'organization_id' => $session->organization_id],
        ['full_name' => $validated['full_name'], 'phone' => $validated['phone'] ?? null, 'status' => 'active']
    );

    if (!$client->wasRecentlyCreated) {
        $client->update([
            'full_name' => $validated['full_name'],
            'phone'     => $validated['phone'] ?? $client->phone,
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
    $participant->institution     = $validated['institution'] ?? $participant->institution;
    $participant->position        = $validated['position']    ?? $participant->position;
    $participant->save();

    return view('public.session-checkin', [
        'session' => $session,
        'success' => true,
        'name'    => $client->full_name,
    ]);
}
}