<?php
// app/Services/Fees/FeeService.php
namespace App\Services\Fees;

use App\Models\{Event, SettlementItem, Ticket, TicketFee, User};
use App\Services\Payments\TicketActivationService;
use Illuminate\Support\Facades\{DB, Log};

/**
 * VENTIQ's fees on tickets.
 *
 * Every ticket is charged once, when it becomes active (paid, free or
 * complimentary):
 *  - service fee:     constants.fees.service_percent of the ticket price
 *  - operational fee: constants.fees.operational_per_person × people
 *
 * Online (VENTIQ-collected) fees come off the organizer's payout; all
 * others are invoiced to the organizer. On a sponsored event the fee is
 * still recorded, marked sponsored, and nothing is collected.
 */
class FeeService
{
    public const SOURCE_FREE          = 'free';
    public const SOURCE_COMPLIMENTARY = 'complimentary';

    /** @return array{service: float, operational: float, total: float, people: int} */
    public function calculate(Ticket $ticket): array
    {
        $people = max(1, (int) ($ticket->admissions ?? 1));
        $price = $ticket->is_complimentary ? 0.0 : (float) $ticket->amount;

        $service = round($price * (float) config('constants.fees.service_percent'), 2);
        $operational = round($people * (float) config('constants.fees.operational_per_person'), 2);

        return [
            'service'     => $service,
            'operational' => $operational,
            'total'       => round($service + $operational, 2),
            'people'      => $people,
        ];
    }

    /** Record the fee for a ticket that has just become active. Safe to call twice. */
    public function charge(Ticket $ticket): TicketFee
    {
        if ($existing = TicketFee::where('ticket_id', $ticket->id)->first()) {
            return $existing;
        }

        $ticket->loadMissing('event');
        $source = $this->sourceOf($ticket);
        $fee = $this->calculate($ticket);

        return TicketFee::firstOrCreate(['ticket_id' => $ticket->id], [
            'event_id'        => $ticket->event_id,
            'organization_id' => $ticket->event->organization_id,
            'source'          => $source,
            'ticket_amount'   => $ticket->is_complimentary ? 0 : $ticket->amount,
            'people'          => $fee['people'],
            'service_fee'     => $fee['service'],
            'operational_fee' => $fee['operational'],
            'total_fee'       => $fee['total'],
            'sponsored'       => (bool) $ticket->event->fees_sponsored,
            'collection'      => $source === TicketActivationService::SOURCE_VENTIQ_ONLINE
                ? TicketFee::COLLECT_FROM_PAYOUT
                : TicketFee::COLLECT_BY_INVOICE,
        ]);
    }

    /**
     * Sponsor (or stop sponsoring) an event's fees. Fees not yet invoiced
     * or paid out follow the new setting; settled ones are history and
     * stay as they were.
     */
    public function setSponsored(Event $event, bool $sponsored, ?User $by = null): int
    {
        return DB::transaction(function () use ($event, $sponsored, $by) {
            $event->update([
                'fees_sponsored'    => $sponsored,
                'fees_sponsored_at' => $sponsored ? now() : null,
                'fees_sponsored_by' => $sponsored ? $by?->id : null,
            ]);

            $settledTickets = SettlementItem::whereNotNull('settlement_id')
                ->whereIn('ticket_id', $event->tickets()->select('id'))
                ->pluck('ticket_id');

            $fees = TicketFee::where('event_id', $event->id)
                ->whereNull('invoiced_at')
                ->whereNotIn('ticket_id', $settledTickets)
                ->get();

            foreach ($fees as $fee) {
                $fee->update(['sponsored' => $sponsored]);

                // Online fees are taken from the payout: keep the pending
                // settlement line in step.
                if ($fee->collection === TicketFee::COLLECT_FROM_PAYOUT) {
                    SettlementItem::where('ticket_id', $fee->ticket_id)->whereNull('settlement_id')->get()
                        ->each(fn ($item) => $item->update([
                            'gateway_fee'        => $fee->chargeable(),
                            'amount_owed_to_org' => max(round((float) $item->amount_received - $fee->chargeable(), 2), 0),
                        ]));
                }
            }

            Log::info('Event fees ' . ($sponsored ? 'sponsored' : 'charged again'), ['event' => $event->id, 'by' => $by?->id, 'fees' => $fees->count()]);

            return $fees->count();
        });
    }

    private function sourceOf(Ticket $ticket): string
    {
        if ($ticket->is_complimentary) {
            return self::SOURCE_COMPLIMENTARY;
        }

        if ((float) $ticket->amount <= 0) {
            return self::SOURCE_FREE;
        }

        $sources = $ticket->payments()->where('status', 'approved')->pluck('source');

        return $sources->contains(TicketActivationService::SOURCE_VENTIQ_ONLINE)
            ? TicketActivationService::SOURCE_VENTIQ_ONLINE
            : TicketActivationService::SOURCE_ORGANIZER_DIRECT;
    }
}
