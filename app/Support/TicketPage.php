<?php

namespace App\Support;

use App\Models\Ticket;

/**
 * What the attendee pages show about a ticket, as React props
 * (resources/js/types/ticket.d.ts). Only what the ticket's own holder may
 * see: these pages are opened with the ticket's private link.
 */
class TicketPage
{
    /** @return array<string, mixed> */
    public static function ticket(Ticket $ticket): array
    {
        $ticket->loadMissing(['event.organization', 'tier', 'client']);
        $event = $ticket->event;

        return [
            'number'        => $ticket->ticket_number,
            'code'          => $ticket->qr_code,
            'holderName'    => $ticket->holder_name,
            'admissions'    => (int) ($ticket->admissions ?? 1),
            'voucherCode'   => $ticket->voucher_code,
            'status'        => $ticket->status,
            'paymentStatus' => $ticket->payment_status,
            'amount'        => (float) $ticket->amount,
            'amountPaid'    => (float) $ticket->amount_paid,
            'owed'          => round(max(0, (float) $ticket->amount - (float) $ticket->amount_paid), 2),
            'paymentDueAt'  => $ticket->payment_due_at?->format('d M Y, H:i'),
            'tier'          => ['name' => $ticket->tier?->tier_name, 'isVip' => str_contains(strtolower((string) $ticket->tier?->tier_name), 'vip')],
            'link'          => route('ticket.download', $ticket->qr_code),
            'event'         => [
                'name'         => $event->name,
                'date'         => $event->event_date?->format('d M Y'),
                'venue'        => $event->venue ?: $event->location,
                // The area too, when the venue is a building name.
                'location'     => $event->venue && $event->location && $event->location !== $event->venue ? $event->location : null,
                'organization' => $event->organization?->name,
            ],
        ];
    }

    /**
     * Why a ticket that exists won't scan, in the attendee's words.
     *
     * @return array{headline: string, body: string, tone: string}
     */
    public static function inactiveState(Ticket $ticket, bool $submitted): array
    {
        [$headline, $body, $tone] = match ($ticket->status) {
            'expired' => ['Payment window ended', 'This ticket was not paid in time, so its place has been released. No payment is needed for it. If places are still available, you can register again.', 'closed'],
            'cancelled', 'void', 'refunded' => ['Ticket cancelled', 'This ticket is no longer valid. Contact the organizer if you think this is a mistake.', 'closed'],
            default => $submitted
                ? ['Payment being confirmed', 'Your payment is being confirmed. This ticket activates as soon as it is, and we will let you know. There is no need to pay again.', 'confirming']
                : ['Inactive — awaiting payment', 'Your place is reserved. Pay to activate this ticket; the same ticket then works at the entrance.', 'waiting'],
        };

        return compact('headline', 'body', 'tone');
    }
}
