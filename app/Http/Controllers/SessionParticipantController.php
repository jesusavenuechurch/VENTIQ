<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Participant;
use App\Models\Session;
use App\Services\AttendanceCardImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

        return \Inertia\Inertia::render('Sessions/Checkin', [
            'session'      => ['id' => $session->id, 'title' => $session->resolved_title],
            'participants' => $participants->map(fn (Participant $p) => [
                'id'          => $p->id,
                'name'        => $p->client?->full_name,
                'phone'       => $p->client?->phone,
                'email'       => $p->client?->email,
                'photo'       => $p->client?->photo_path ? Storage::url($p->client->photo_path) : null,
                'time'        => $p->attended_at?->format('g:i A'),
                'institution' => $p->institution,
                'position'    => $p->position,
                'notified'    => (bool) $p->notified_at,
            ]),
            'meta' => ['title' => "Check-in: {$session->resolved_title} | VENTIQ"],
        ])->withViewData(['noindex' => true]);
    }

    public function store(Request $request, Session $session)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($session->event_id, 404);

        $validated = $request->validate([
            'full_name'   => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:255',
            'institution' => 'nullable|string|max:255',
            'position'    => 'nullable|string|max:255',
        ]);

        // With an email, the same person as the self check-in form; the
        // email is how they get their attendance card afterwards.
        if (!empty($validated['email'])) {
            $session->checkInAttendee($validated + ['source' => 'walk_in']);

            return redirect()->route('sessions.checkin', $session)->with('status', "{$validated['full_name']} is checked in.");
        }

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

        return redirect()->route('sessions.checkin', $session)->with('status', "{$validated['full_name']} is checked in.");
    }

    public function update(Request $request, Session $session, Participant $participant)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($participant->session_id === $session->id, 404);

        $validated = $request->validate([
            'full_name'   => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:255',
            'institution' => 'nullable|string|max:255',
            'position'    => 'nullable|string|max:255',
        ]);

        $participant->client->update(['full_name' => $validated['full_name'], 'phone' => $validated['phone'] ?? null, 'email' => $validated['email'] ?? $participant->client->email]);
        $participant->update([
            'institution' => $validated['institution'] ?? null,
            'position'    => $validated['position'] ?? null,
        ]);

        return redirect()->route('sessions.checkin', $session)->with('status', 'Saved.');
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

    // The one card design — same PNG generated for the auto-sent thank-you
    // email (AttendanceCardImageService), viewable/shareable on demand here
    // instead of a separate, older dompdf card that never had a photo field.
    public function card(Session $session, Participant $participant, AttendanceCardImageService $cardService)
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 403);
        abort_unless($participant->session_id === $session->id, 404);

        $path = $cardService->generate($participant);

        return response(Storage::disk('public')->get($path))
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'inline; filename="attendance-card-' . $participant->id . '.png"');
    }
}