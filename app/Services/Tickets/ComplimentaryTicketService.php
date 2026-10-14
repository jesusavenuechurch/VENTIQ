<?php

namespace App\Services\Tickets;

use App\Models\{Client, Event, EventTier, Ticket, User};
use App\Support\TierCapacity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Free tickets the organizer gives away: speakers, sponsors, partners.
 * They're active straight away, take a place in their tier like any other
 * ticket, and carry VENTIQ's operational fee per person (FeeService).
 *
 * Delivery is left to Ticket's `updated` hook, which sends the ticket when
 * it becomes paid; the Filament action also dispatched it, so comps went
 * out twice.
 */
class ComplimentaryTicketService
{
    /**
     * @param array{full_name: string, phone: string, email?: ?string} $guest
     */
    public function issue(Event $event, EventTier $tier, array $guest, User $by, ?string $reason = null, bool $sendWhatsApp = true): Ticket
    {
        if ($tier->event_id !== $event->id) {
            throw new InvalidArgumentException('That ticket type belongs to another event.');
        }

        return DB::transaction(function () use ($event, $tier, $guest, $by, $reason, $sendWhatsApp) {
            // Lock the tier so two comps (or a comp and a registration)
            // can't both take its last place.
            $tier = EventTier::whereKey($tier->id)->lockForUpdate()->first();

            if (!TierCapacity::hasRoom($tier)) {
                throw new InvalidArgumentException("{$tier->tier_name} is full. Raise its quantity in Edit event to add a complimentary ticket.");
            }

            // Same rule as registration: one client per phone number in
            // the organization.
            $client = Client::firstOrCreate(
                ['phone' => $guest['phone'], 'organization_id' => $event->organization_id],
                ['full_name' => $guest['full_name'], 'email' => $guest['email'] ?? null, 'created_by' => $by->id],
            );
            if (!$client->email && !empty($guest['email'])) {
                $client->update(['email' => $guest['email']]);
            }

            $ticket = Ticket::create([
                'event_id'           => $event->id,
                'client_id'          => $client->id,
                'event_tier_id'      => $tier->id,
                'created_by'         => $by->id,
                'is_complimentary'   => true,
                'amount'             => 0,
                'admissions'         => max(1, (int) ($tier->quantity_per_purchase ?? 1)),
                'has_whatsapp'       => $sendWhatsApp,
                'preferred_delivery' => $sendWhatsApp ? 'both' : 'email',
            ]);

            $ticket->markAsComplimentary($by->id, $reason ?: 'Complimentary');
            $ticket->generateQrCode();
            $tier->increment('quantity_sold');

            Log::info("Complimentary ticket {$ticket->id} issued by user {$by->id}", ['event' => $event->id, 'tier' => $tier->id]);

            return $ticket;
        });
    }
}
