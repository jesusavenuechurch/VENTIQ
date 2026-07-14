<?php
// app/Http/Controllers/Api/WorkshopController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\WorkshopTicketDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkshopController extends Controller
{
    /**
     * Get workshop ticket details after QR/voucher scan.
     * Called by scanner app immediately after confirming check-in
     * to show the attendee's workshop details + prompt for signature.
     *
     * GET /api/workshop/ticket/{qrCode}
     */
    public function ticketDetails(string $qrCode): JsonResponse
    {
        $ticket = Ticket::where('qr_code', $qrCode)
            ->orWhere('voucher_code', $qrCode)
            ->with(['client', 'event', 'tier', 'workshopDetail'])
            ->first();

        if (!$ticket) {
            return response()->json([
                'found'   => false,
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (!$ticket->event->isWorkshop()) {
            return response()->json([
                'found'      => true,
                'is_workshop'=> false,
                'message'    => 'This is not a workshop event.',
            ]);
        }

        $detail = $ticket->workshopDetail;

        return response()->json([
            'found'       => true,
            'is_workshop' => true,
            'ticket'      => [
                'id'             => $ticket->id,
                'ticket_number'  => $ticket->ticket_number,
                'voucher_code'   => $ticket->voucher_code,
                'status'         => $ticket->status,
                'is_checked_in'  => $ticket->isCheckedIn(),
                'checked_in_at'  => $ticket->checked_in_at,
            ],
            'client' => [
                'full_name'   => $ticket->client->full_name,
                'phone'       => $ticket->client->phone,
                'email'       => $ticket->client->email,
            ],
            'event' => [
                'name'       => $ticket->event->name,
                'event_date' => $ticket->event->event_date?->format('d M Y'),
                'venue'      => $ticket->event->venue,
                'event_type' => $ticket->event->event_type,
            ],
            'workshop_detail' => $detail ? [
                'position'         => $detail->position,
                'institution'      => $detail->institution,
                'district'         => $detail->district,
                'district_label'   => $detail->district_label,
                'signature_status' => $detail->signature_status,
                'status_label'     => $detail->status_label,
                'is_signed'        => $detail->isSigned(),
                'signed_at'        => $detail->signed_at,
                'signature_url'    => $detail->signature_url,
            ] : null,
        ]);
    }

    /**
     * Save signature after check-in.
     * Called by scanner app after attendee signs on the tablet.
     *
     * POST /api/workshop/ticket/{ticketId}/sign
     * Body: {
     *   "signature": "data:image/png;base64,...",
     *   "device_info": "iPad Safari 16.0"   // optional
     * }
     */
    public function saveSignature(Request $request, int $ticketId): JsonResponse
    {
        $request->validate([
            'signature'   => 'required|string', // base64 image
            'device_info' => 'nullable|string|max:255',
        ]);

        $ticket = Ticket::with(['event', 'workshopDetail'])->find($ticketId);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        if (!$ticket->event->isWorkshop()) {
            return response()->json(['success' => false, 'message' => 'Not a workshop ticket.'], 400);
        }

        if (!$ticket->isCheckedIn()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket must be checked in before signing.',
            ], 400);
        }

        // Get or create detail row
        $detail = $ticket->workshopDetail
            ?? WorkshopTicketDetail::create([
                'ticket_id'        => $ticket->id,
                'signature_status' => 'pending',
            ]);

        if ($detail->isSigned()) {
            return response()->json([
                'success'  => false,
                'message'  => 'Signature already captured for this ticket.',
                'signed_at'=> $detail->signed_at,
            ], 409);
        }

        $success = $detail->storeSignature(
            base64Image: $request->signature,
            signedBy:    auth()->id(),
            deviceInfo:  $request->device_info,
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save signature. Please try again.',
            ], 500);
        }

        Log::info("Workshop signature saved: ticket {$ticket->ticket_number}");

        return response()->json([
            'success'      => true,
            'message'      => 'Signature saved successfully.',
            'signed_at'    => $detail->fresh()->signed_at,
            'signature_url'=> $detail->fresh()->signature_url,
        ]);
    }

    /**
     * Update workshop details (position, institution, district).
     * Called when gate staff fills in missing details at registration desk.
     *
     * PATCH /api/workshop/ticket/{ticketId}/details
     * Body: { "position": "Nurse", "institution": "MoH", "district": "maseru" }
     */
    public function updateDetails(Request $request, int $ticketId): JsonResponse
    {
        $request->validate([
            'position'    => 'nullable|string|max:100',
            'institution' => 'nullable|string|max:150',
            'district'    => 'nullable|string|max:30',
        ]);

        $ticket = Ticket::with(['event', 'workshopDetail'])->find($ticketId);

        if (!$ticket || !$ticket->event->isWorkshop()) {
            return response()->json(['success' => false, 'message' => 'Workshop ticket not found.'], 404);
        }

        $detail = $ticket->workshopDetail
            ?? WorkshopTicketDetail::create([
                'ticket_id'        => $ticket->id,
                'signature_status' => 'pending',
            ]);

        $detail->update($request->only(['position', 'institution', 'district']));

        return response()->json([
            'success' => true,
            'message' => 'Details updated.',
            'detail'  => [
                'position'       => $detail->position,
                'institution'    => $detail->institution,
                'district'       => $detail->district,
                'district_label' => $detail->district_label,
            ],
        ]);
    }

    /**
     * Mark signature as declined or skipped.
     *
     * POST /api/workshop/ticket/{ticketId}/signature-status
     * Body: { "status": "declined" | "skipped" }
     */
    public function updateSignatureStatus(Request $request, int $ticketId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:declined,skipped',
        ]);

        $ticket = Ticket::with(['event', 'workshopDetail'])->find($ticketId);

        if (!$ticket || !$ticket->event->isWorkshop()) {
            return response()->json(['success' => false, 'message' => 'Workshop ticket not found.'], 404);
        }

        $detail = $ticket->workshopDetail
            ?? WorkshopTicketDetail::create([
                'ticket_id'        => $ticket->id,
                'signature_status' => 'pending',
            ]);

        if ($request->status === 'declined') {
            $detail->markDeclined(auth()->id());
        } else {
            $detail->markSkipped(auth()->id());
        }

        return response()->json([
            'success' => true,
            'message' => 'Status updated to ' . $request->status . '.',
        ]);
    }

    /**
     * Get workshop attendance summary for an event.
     * Used by the scanner app dashboard to show gate stats.
     *
     * GET /api/workshop/event/{eventId}/summary
     */
    public function eventSummary(int $eventId): JsonResponse
    {
        $tickets = Ticket::where('event_id', $eventId)
            ->with('workshopDetail')
            ->get();

        $checkedIn = $tickets->where('status', 'checked_in');

        return response()->json([
            'total'           => $tickets->count(),
            'checked_in'      => $checkedIn->count(),
            'signed'          => $checkedIn->filter(fn ($t) => $t->workshopDetail?->isSigned())->count(),
            'awaiting_signature' => $checkedIn->filter(fn ($t) => $t->workshopDetail?->isPending())->count(),
            'declined'        => $checkedIn->filter(fn ($t) => $t->workshopDetail?->signature_status === 'declined')->count(),
            'skipped'         => $checkedIn->filter(fn ($t) => $t->workshopDetail?->signature_status === 'skipped')->count(),
        ]);
    }
}