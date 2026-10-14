<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Participant;
use App\Models\Session;
use Illuminate\Http\Request;

class PublicSessionCheckinController extends Controller
{
    // The other door in besides the QR code — someone who can't scan types
    // the short code printed on the check-in pass instead. A match sends
    // them to the exact same form the QR code links to.
    public function join(Request $request)
    {
        if (!$request->filled('code')) {
            return view('public.session-join');
        }

        $code = strtoupper(trim($request->query('code')));

        $session = Session::where('session_code', $code)->first();

        if (!$session) {
            return view('public.session-join', [
                'error' => "No session found for code \"{$code}\".",
                'code'  => $code,
            ]);
        }

        return redirect()->route('public.session-checkin.form', $session->public_token);
    }

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
                // Scoped to THIS session, not the whole event — someone who
                // already checked into Day 1 should still be prompted to
                // check into Day 2, not told they're "already checked in"
                // for a session they've never actually attended.
                $existing = Participant::where('session_id', $session->id)
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
            'photo'       => 'nullable|image|max:5120',
        ]);

        $participant = $session->checkInAttendee([
            'full_name'   => $validated['full_name'],
            'email'       => $validated['email'],
            'phone'       => $validated['phone'] ?? null,
            'institution' => $validated['institution'] ?? null,
            'position'    => $validated['position'] ?? null,
            'source'      => 'walk_in',
        ]);

        // Optional — skippable at check-in. A new upload replaces the old
        // one; skipping leaves whatever photo the client already has.
        if ($request->hasFile('photo')) {
            $participant->client->update([
                'photo_path' => $request->file('photo')->store('client-photos', 'public'),
            ]);
        }

        $client = $participant->client;

        return view('public.session-checkin', [
            'session' => $session,
            'success' => true,
            'name'    => $client->full_name,
        ]);
    }
}