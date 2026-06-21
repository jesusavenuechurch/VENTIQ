<?php
 
namespace App\Http\Controllers\Api;
 
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
 
class VoucherScanController extends Controller
{
    /**
     * Look up a ticket by voucher code.
     * Returns ticket details so the scanner can show a confirmation screen
     * before committing the check-in.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'voucher_code' => 'required|string|min:4|max:10',
        ]);
 
        $ticket = Ticket::byVoucherCode($request->voucher_code)
            ->with(['client', 'event', 'tier'])
            ->first();
 
        if (!$ticket) {
            return response()->json([
                'found'    => false,
                'message'  => 'No ticket found for this voucher code.',
            ], 404);
        }
 
        return response()->json([
            'found'   => true,
            'ticket'  => [
                'id'             => $ticket->id,
                'ticket_number'  => $ticket->ticket_number,
                'voucher_code'   => $ticket->voucher_code,
                'client_name'    => $ticket->client?->full_name,
                'event_name'     => $ticket->event?->name,
                'tier_name'      => $ticket->tier?->tier_name,
                'status'         => $ticket->status,
                'payment_status' => $ticket->payment_status,
                'is_valid'       => $ticket->isValid() || $ticket->is_complimentary,
                'is_checked_in'  => $ticket->isCheckedIn(),
                'is_complimentary' => $ticket->is_complimentary,
            ],
        ]);
    }
 
    /**
     * Check in a ticket by voucher code.
     * Same logic as QR check-in — just a different lookup method.
     */
    public function checkin(Request $request): JsonResponse
    {
        $request->validate([
            'voucher_code' => 'required|string|min:4|max:10',
            'scanned_by'   => 'nullable|integer|exists:users,id',
        ]);
 
        $ticket = Ticket::byVoucherCode($request->voucher_code)
            ->with(['client', 'event', 'tier'])
            ->first();
 
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'No ticket found for this voucher code.',
            ], 404);
        }
 
        if ($ticket->isCheckedIn()) {
            return response()->json([
                'success'    => false,
                'message'    => 'This ticket has already been checked in.',
                'checked_in_at' => $ticket->checked_in_at,
            ], 409);
        }
 
        if (!$ticket->isValid() && !$ticket->is_complimentary) {
            return response()->json([
                'success' => false,
                'message' => "Ticket status is '{$ticket->status}'. Cannot check in.",
            ], 403);
        }
 
        $success = $ticket->checkIn($request->scanned_by);
 
        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Check-in failed. Please try again.',
            ], 500);
        }
 
        Log::info("Voucher check-in: {$ticket->ticket_number} ({$ticket->voucher_code}) by user {$request->scanned_by}");
 
        return response()->json([
            'success'     => true,
            'message'     => "Welcome, {$ticket->client?->full_name}! ✓",
            'ticket'      => [
                'ticket_number'  => $ticket->ticket_number,
                'voucher_code'   => $ticket->voucher_code,
                'client_name'    => $ticket->client?->full_name,
                'event_name'     => $ticket->event?->name,
                'tier_name'      => $ticket->tier?->tier_name,
                'checked_in_at'  => $ticket->fresh()->checked_in_at,
            ],
        ]);
    }
}