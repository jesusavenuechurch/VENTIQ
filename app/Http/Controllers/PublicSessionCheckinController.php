<?php

namespace App\Http\Controllers;

use App\Models\Session;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicSessionCheckinController extends Controller
{
    // The other door in besides the QR code — someone who can't scan types
    // the short code printed on the check-in pass instead. A match sends
    // them to the exact same form the QR code links to.
    public function join(Request $request)
    {
        $code = strtoupper(trim((string) $request->query('code')));

        if ($code !== '') {
            $session = Session::where('session_code', $code)->whereNotNull('public_token')->first();

            if ($session) {
                return redirect()->route('public.session-checkin.form', $session->public_token);
            }
        }

        return Inertia::render('Public/SessionJoin', [
            'code'  => $code,
            'error' => $code !== '' ? "No session has the code {$code}. Check the pass and try again." : null,
            'meta'  => ['title' => 'Join a session | VENTIQ'],
        ]);
    }

    // Checking in again with the same email updates the details already
    // given (Session::checkInAttendee), so there's no lookup here: a
    // search by phone or email would show anyone holding the link
    // another attendee's details.
    public function show(string $token)
    {
        $session = Session::where('public_token', $token)->firstOrFail();

        return Inertia::render('Public/SessionCheckin', [
            'session'   => ['title' => $session->resolved_title, 'organization' => $session->organization?->name, 'token' => $token],
            'checkedIn' => session('checkedIn'),
            'meta'      => ['title' => "Check in: {$session->resolved_title} | VENTIQ"],
        ])->withViewData(['noindex' => true]);
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
        ], [
            'photo.image' => 'The photo needs to be a picture (JPG or PNG).',
            'photo.max'   => 'That photo is too big. Use one under 5 MB.',
        ]);

        $participant = $session->checkInAttendee([
            'full_name'   => $validated['full_name'],
            'email'       => $validated['email'],
            'phone'       => $validated['phone'] ?? null,
            'institution' => $validated['institution'] ?? null,
            'position'    => $validated['position'] ?? null,
            // They checked themselves in (QR or code): the participant list says so.
            'source'      => 'self',
        ]);

        // Optional — skippable at check-in. A new upload replaces the old
        // one; skipping leaves whatever photo the client already has.
        if ($request->hasFile('photo')) {
            $participant->client->update([
                'photo_path' => $request->file('photo')->store('client-photos', 'public'),
            ]);
        }

        return redirect()->route('public.session-checkin.form', $token)
            ->with('checkedIn', $participant->client->full_name);
    }
}
