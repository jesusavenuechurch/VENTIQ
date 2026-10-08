<?php

namespace App\Services\Tickets;

use App\Models\{Client, Event, EventTier, Ticket, TicketPayment, User};
use App\Services\Payments\TicketActivationService;
use App\Support\TierCapacity;
use Illuminate\Support\Facades\{DB, Log};
use InvalidArgumentException;

/**
 * Tickets paid to the organizer in person: cash at their office or a
 * reseller, or a transfer they've checked themselves. The organizer either
 * sells a new ticket on the spot, or marks a reserved one as paid.
 *
 * Either way it's an organizer-direct payment at the ticket price, so its
 * fees are invoiced like any ticket paid to the organizer.
 */
class DirectSaleService
{
    public const METHODS = ['cash' => 'Cash', 'ecocash' => 'EcoCash', 'mpesa' => 'M-Pesa', 'bank_transfer' => 'Bank transfer'];

    public function __construct(private TicketActivationService $activation) {}

    /**
     * A new ticket, paid on the spot.
     *
     * @param array{full_name: string, phone: string, email?: ?string} $buyer
     */
    public function sell(Event $event, EventTier $tier, array $buyer, User $by, string $method = 'cash', ?string $reference = null, bool $sendWhatsApp = true): Ticket
    {
        if ($tier->event_id !== $event->id) {
            throw new InvalidArgumentException('That ticket type belongs to another event.');
        }

        $ticket = DB::transaction(function () use ($event, $tier, $buyer, $by, $sendWhatsApp) {
            // Lock the tier so a sale and a registration can't both take its last place.
            $tier = EventTier::whereKey($tier->id)->lockForUpdate()->first();
            if (!TierCapacity::hasRoom($tier)) {
                throw new InvalidArgumentException("{$tier->tier_name} is sold out. Raise its number in Edit event to sell more.");
            }

            $client = Client::firstOrCreate(
                ['phone' => $buyer['phone'], 'organization_id' => $event->organization_id],
                ['full_name' => $buyer['full_name'], 'email' => $buyer['email'] ?? null, 'created_by' => $by->id],
            );
            if (!$client->email && !empty($buyer['email'])) {
                $client->update(['email' => $buyer['email']]);
            }

            $ticket = Ticket::create([
                'event_id'           => $event->id,
                'client_id'          => $client->id,
                'event_tier_id'      => $tier->id,
                'created_by'         => $by->id,
                'status'             => 'pending',
                'payment_status'     => 'pending',
                'amount'             => $tier->price,
                'admissions'         => max(1, (int) ($tier->quantity_per_purchase ?? 1)),
                'has_whatsapp'       => $sendWhatsApp,
                'preferred_delivery' => $sendWhatsApp ? 'both' : 'email',
            ]);
            TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => $tier->price, 'status' => 'pending', 'payment_type' => 'full']);

            return $ticket;
        });

        $this->activation->activate($ticket, TicketActivationService::SOURCE_ORGANIZER_DIRECT, $method, $reference ?: 'Sold by organizer', $by->id);
        $ticket->generateQrCode();

        Log::info("Ticket {$ticket->id} sold directly by user {$by->id}", ['event' => $event->id, 'method' => $method]);

        return $ticket;
    }

    /** A reserved ticket the attendee paid the organizer for, without paying online. */
    public function markPaid(Ticket $ticket, User $by, string $method = 'cash', ?string $reference = null): void
    {
        if ($ticket->status !== 'pending') {
            throw new InvalidArgumentException('Only tickets awaiting payment can be marked as paid.');
        }

        $this->activation->activate($ticket, TicketActivationService::SOURCE_ORGANIZER_DIRECT, $method, $reference ?: 'Paid to organizer', $by->id);
        if (!$ticket->fresh()->qr_code_path) {
            $ticket->generateQrCode();
        }

        Log::info("Ticket {$ticket->id} marked paid by user {$by->id}", ['method' => $method]);
    }
}
