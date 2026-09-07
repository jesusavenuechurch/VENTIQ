<?php
// app/Http/Controllers/Api/ProgrammeScannerController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Programme equivalent of TicketScanController + WorkshopController,
 * for the scanner app's "Programme Check-in" mode. Programme attendees
 * have no personal ticket/QR code — identification at the door is by
 * searching the organization's clients by name/email/phone, not scanning
 * a code. See Session::checkInAttendee() for the shared find-or-create
 * logic this also uses on the public self-service side.
 */
class ProgrammeScannerController extends Controller
{
    /**
     * Programmes (Events flagged is_programme) with their sessions, for the
     * authenticated user's organization.
     *
     * GET /api/programme/sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Event::where('is_programme', true);

        if (!$user->hasRole('super_admin')) {
            $query->where('organization_id', $user->organization_id);
        }

        $programmes = $query->with(['sessions' => function ($q) {
                $q->orderBy('date')->orderBy('start_time');
            }])
            ->orderByDesc('event_date')
            ->get()
            ->map(function (Event $programme) {
                return [
                    'id'                         => $programme->id,
                    'name'                       => $programme->name,
                    'venue'                      => $programme->venue,
                    'event_date'                 => $programme->event_date,
                    'event_date_formatted'       => $programme->event_date ? Carbon::parse($programme->event_date)->format('d M Y') : null,
                    'signature_capture_enabled'  => (bool) $programme->signature_capture_enabled,
                    'sessions'                   => $programme->sessions->map(function (Session $session) {
                        $total     = Participant::where('session_id', $session->id)->count();
                        $checkedIn = Participant::where('session_id', $session->id)->whereNotNull('attended_at')->count();

                        return [
                            'id'                => $session->id,
                            'title'             => $session->resolved_title,
                            'date'              => $session->date,
                            'date_formatted'    => $session->date?->format('d M Y'),
                            'location'          => $session->location,
                            'session_code'      => $session->session_code,
                            'participant_count' => $total,
                            'checked_in_count'  => $checkedIn,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success'    => true,
            'programmes' => $programmes,
        ]);
    }

    /**
     * Search the organization's clients by name/email/phone, annotated with
     * whether they already have a participant record for this session.
     *
     * GET /api/programme/session/{sessionId}/search?q=
     */
    public function search(Request $request, int $sessionId): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $session = $this->authorizedSession($request, $sessionId);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $q = trim($request->query('q'));

        $clients = Client::where('organization_id', $session->organization_id)
            ->where(function ($query) use ($q) {
                $query->where('full_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get();

        $participants = Participant::where('session_id', $session->id)
            ->whereIn('client_id', $clients->pluck('id'))
            ->get()
            ->keyBy('client_id');

        $results = $clients->map(function (Client $client) use ($participants) {
            $participant = $participants->get($client->id);

            return [
                'client_id'     => $client->id,
                'full_name'     => $client->full_name,
                'email'         => $client->email,
                'phone'         => $client->phone,
                'participant'   => $participant ? [
                    'id'             => $participant->id,
                    'is_checked_in'  => $participant->isCheckedIn(),
                    'attended_at'    => $participant->attended_at,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Check an attendee into a session — either an existing client
     * (`client_id`, picked from search results) or a brand-new walk-in
     * (`full_name` + `email`).
     *
     * POST /api/programme/session/{sessionId}/checkin
     */
    public function checkin(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->authorizedSession($request, $sessionId);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $validated = $request->validate([
            'client_id'   => 'nullable|integer|required_without:email',
            'full_name'   => 'required_with:email|nullable|string|max:255',
            'email'       => 'required_without:client_id|nullable|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:255',
            'position'    => 'nullable|string|max:255',
        ]);

        $participant = $session->checkInAttendee($validated + ['source' => 'scanner']);

        Log::info("Programme check-in: participant {$participant->id} (session {$session->id})");

        return response()->json([
            'success'             => true,
            'already_checked_in'  => $participant->wasAlreadyCheckedIn ?? false,
            'participant'         => $this->participantPayload($participant, $session),
        ]);
    }

    /**
     * Attendance summary for a session — same shape as
     * WorkshopController::eventSummary, session-scoped instead of
     * event-scoped.
     *
     * GET /api/programme/session/{sessionId}/summary
     */
    public function summary(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->authorizedSession($request, $sessionId);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $participants = Participant::where('session_id', $session->id)->get();
        $checkedIn = $participants->whereNotNull('attended_at');

        return response()->json([
            'total'               => $participants->count(),
            'checked_in'          => $checkedIn->count(),
            'signed'              => $checkedIn->filter(fn ($p) => $p->isSigned())->count(),
            'awaiting_signature'  => $checkedIn->filter(fn ($p) => $p->isPending())->count(),
            'declined'            => $checkedIn->filter(fn ($p) => $p->signature_status === 'declined')->count(),
            'skipped'             => $checkedIn->filter(fn ($p) => $p->signature_status === 'skipped')->count(),
        ]);
    }

    /**
     * Save signature after check-in.
     *
     * POST /api/programme/participant/{participantId}/sign
     * Body: { "signature": "data:image/png;base64,...", "device_info": "..." }
     */
    public function saveSignature(Request $request, int $participantId): JsonResponse
    {
        $request->validate([
            'signature'   => 'required|string',
            'device_info' => 'nullable|string|max:255',
        ]);

        $participant = Participant::with(['session.event'])->find($participantId);

        if (!$participant || !$this->userCanAccess($request, $participant)) {
            return response()->json(['success' => false, 'message' => 'Participant not found.'], 404);
        }

        if (!$participant->session?->event?->signature_capture_enabled) {
            return response()->json(['success' => false, 'message' => 'Signature capture is not enabled for this programme.'], 400);
        }

        if (!$participant->isCheckedIn()) {
            return response()->json(['success' => false, 'message' => 'Attendee must be checked in before signing.'], 400);
        }

        if ($participant->isSigned()) {
            return response()->json([
                'success'   => false,
                'message'   => 'Signature already captured for this attendee.',
                'signed_at' => $participant->signed_at,
            ], 409);
        }

        $success = $participant->storeSignature(
            base64Image: $request->signature,
            signedBy:    auth()->id(),
            deviceInfo:  $request->device_info,
        );

        if (!$success) {
            return response()->json(['success' => false, 'message' => 'Failed to save signature. Please try again.'], 500);
        }

        Log::info("Programme signature saved: participant {$participant->id}");

        return response()->json([
            'success'       => true,
            'message'       => 'Signature saved successfully.',
            'signed_at'     => $participant->fresh()->signed_at,
            'signature_url' => $participant->fresh()->signature_url,
        ]);
    }

    /**
     * Update position/institution/district.
     *
     * PATCH /api/programme/participant/{participantId}/details
     */
    public function updateDetails(Request $request, int $participantId): JsonResponse
    {
        $request->validate([
            'position'    => 'nullable|string|max:100',
            'institution' => 'nullable|string|max:150',
            'district'    => 'nullable|string|max:30',
        ]);

        $participant = Participant::with(['session.event'])->find($participantId);

        if (!$participant || !$this->userCanAccess($request, $participant) || !$participant->session?->event?->signature_capture_enabled) {
            return response()->json(['success' => false, 'message' => 'Participant not found.'], 404);
        }

        $participant->update($request->only(['position', 'institution', 'district']));

        return response()->json([
            'success' => true,
            'message' => 'Details updated.',
            'detail'  => [
                'position'       => $participant->position,
                'institution'    => $participant->institution,
                'district'       => $participant->district,
                'district_label' => $participant->district_label,
            ],
        ]);
    }

    /**
     * Mark signature as declined or skipped.
     *
     * POST /api/programme/participant/{participantId}/signature-status
     * Body: { "status": "declined" | "skipped" }
     */
    public function updateSignatureStatus(Request $request, int $participantId): JsonResponse
    {
        $request->validate(['status' => 'required|in:declined,skipped']);

        $participant = Participant::with(['session.event'])->find($participantId);

        if (!$participant || !$this->userCanAccess($request, $participant) || !$participant->session?->event?->signature_capture_enabled) {
            return response()->json(['success' => false, 'message' => 'Participant not found.'], 404);
        }

        if ($request->status === 'declined') {
            $participant->markDeclined(auth()->id());
        } else {
            $participant->markSkipped(auth()->id());
        }

        return response()->json([
            'success' => true,
            'message' => 'Status updated to ' . $request->status . '.',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Fetch a session and check it belongs to the caller's organization
     * (or the caller is super_admin). Returns a JsonResponse (404/403) in
     * place of the session when access should be denied.
     */
    private function authorizedSession(Request $request, int $sessionId): Session|JsonResponse
    {
        $session = Session::with('event')->find($sessionId);

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found.'], 404);
        }

        $user = $request->user();
        if (!$user->hasRole('super_admin') && $session->organization_id !== $user->organization_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return $session;
    }

    private function userCanAccess(Request $request, Participant $participant): bool
    {
        $user = $request->user();
        return $user->hasRole('super_admin') || $participant->organization_id === $user->organization_id;
    }

    private function participantPayload(Participant $participant, Session $session): array
    {
        $participant->loadMissing('client');
        $programme = $session->event;

        return [
            'id'            => $participant->id,
            'is_checked_in' => $participant->isCheckedIn(),
            'attended_at'   => $participant->attended_at,
            'client'        => [
                'id'        => $participant->client->id,
                'full_name' => $participant->client->full_name,
                'phone'     => $participant->client->phone,
                'email'     => $participant->client->email,
            ],
            'session' => [
                'id'       => $session->id,
                'title'    => $session->resolved_title,
                'date'     => $session->date,
                'location' => $session->location,
            ],
            'programme' => [
                'id'         => $programme?->id,
                'name'       => $programme?->name,
                'event_date' => $programme?->event_date,
                'venue'      => $programme?->venue,
            ],
            'signature_capture_enabled' => (bool) $programme?->signature_capture_enabled,
            'detail' => [
                'position'         => $participant->position,
                'institution'      => $participant->institution,
                'district'         => $participant->district,
                'district_label'   => $participant->district_label,
                'signature_status' => $participant->signature_status,
                'status_label'     => $participant->status_label,
                'is_signed'        => $participant->isSigned(),
                'signed_at'        => $participant->signed_at,
                'signature_url'    => $participant->signature_url,
            ],
        ];
    }
}
