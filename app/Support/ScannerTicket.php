<?php

namespace App\Support;

use App\Models\Ticket;

/**
 * A ticket as the door app sees it: the same shape whether it came from
 * the event download, an online QR check or an entry-code lookup. The app
 * admits only when is_scannable is true; scan_outcome says why not.
 * Given the event the door is scanning, a ticket for another event reads
 * as wrong_event.
 */
class ScannerTicket
{
    public static function present(Ticket $ticket, ?int $eventId = null): array
    {
        $ticket->loadMissing(['client', 'tier', 'event']);
        $outcome = $ticket->scanOutcome($eventId);

        return [
            'id'             => $ticket->id,
            'event_id'       => $ticket->event_id,
            'event_name'     => $ticket->event?->name,
            'ticket_number'  => $ticket->ticket_number,
            'qr_code'        => $ticket->qr_code,
            'voucher_code'   => $ticket->voucher_code,
            'status'         => $ticket->status,
            'is_scannable'   => $outcome === Ticket::SCAN_VALID,
            'scan_outcome'   => $outcome,
            'admissions'     => (int) ($ticket->admissions ?: 1),
            'admitted_count' => (int) $ticket->admitted_count,
            'payment_status' => $ticket->payment_status,
            'amount'         => (float) $ticket->amount,
            'amount_paid'    => (float) $ticket->amount_paid,
            'is_complimentary' => (bool) $ticket->is_complimentary,
            'checked_in_at'  => $ticket->checked_in_at,
            'client' => [
                'id'        => $ticket->client?->id,
                'full_name' => $ticket->holder_name,
                'phone'     => $ticket->client?->phone ?? '',
                'email'     => $ticket->client?->email ?? '',
            ],
            'tier' => [
                'id'    => $ticket->tier?->id,
                'name'  => $ticket->tier?->tier_name,
                'color' => $ticket->tier?->color ?? '#3B82F6',
                'price' => (float) ($ticket->tier?->price ?? 0),
            ],
        ];
    }
}
