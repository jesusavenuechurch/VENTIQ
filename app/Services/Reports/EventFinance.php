<?php
// app/Services/Reports/EventFinance.php
namespace App\Services\Reports;

use App\Models\{Event, SettlementItem, Ticket, TicketPayment};
use App\Services\Payments\TicketActivationService;
use Illuminate\Support\Collection;

/**
 * One event's money and headcount, worked out once so the PDF reports and
 * the organizer area agree.
 *
 * Money is split by who collected it: VENTIQ (online, through the gateway;
 * paid out to the organizer at settlement minus VENTIQ's fee) or the
 * organizer directly (EcoCash/M-Pesa/bank/cash into their own account; no
 * VENTIQ fee, nothing to settle).
 *
 * Headcount counts people, not tickets: a group ticket for 3 is 3 people.
 * Expired, cancelled and refunded tickets aren't counted as sold.
 */
class EventFinance
{
    /** Tickets that still represent a place: unpaid but held, active, used. */
    public const LIVE_STATUSES = ['pending', 'active', 'checked_in'];

    public function __construct(public readonly Event $event) {}

    public static function for(Event $event): self
    {
        return new self($event);
    }

    public function summary(): array
    {
        $tickets = $this->liveTickets();
        $paid = $tickets->where('is_complimentary', false);
        $payments = $this->approvedPayments();
        $settlement = $this->settlementItems();

        $byVentiq    = (float) $payments->where('source', TicketActivationService::SOURCE_VENTIQ_ONLINE)->sum('amount');
        $byOrganizer = (float) $payments->where('source', TicketActivationService::SOURCE_ORGANIZER_DIRECT)->sum('amount');
        // Money we can't place: approved payments without a source, and
        // tickets an old approval marked paid without confirming any
        // payment record (the former dashboard widget did this).
        $unattributed = (float) $payments->whereNull('source')->sum('amount') + $this->legacyPaid($paid)->sum('amount');
        $collected = $byVentiq + $byOrganizer + $unattributed;
        $expected = (float) $paid->sum('amount');

        return [
            'tickets'          => $tickets->count(),
            'people'           => (int) $tickets->sum(fn ($t) => $t->admissions ?? 1),
            'people_admitted'  => (int) $tickets->sum('admitted_count'),
            'tickets_active'   => $tickets->whereIn('status', ['active', 'checked_in'])->count(),
            'tickets_unpaid'   => $tickets->where('status', 'pending')->count(),
            'comp_tickets'     => $tickets->where('is_complimentary', true)->count(),
            'released_tickets' => $this->event->tickets()->whereNotIn('status', self::LIVE_STATUSES)->count(),

            'expected'         => $expected,
            'collected'        => $collected,
            'collected_ventiq' => $byVentiq,
            'collected_direct' => $byOrganizer,
            'unattributed'     => $unattributed,
            'outstanding'      => max(0, $expected - $collected),
            'awaiting_confirmation' => (float) TicketPayment::whereIn('ticket_id', $tickets->pluck('id'))
                ->where('status', 'pending')->whereNotNull('submitted_at')->sum('amount'),
            'collection_rate'  => $expected > 0 ? round($collected / $expected * 100, 1) : 0,

            // Online money only: what VENTIQ keeps and what it owes the organizer.
            'ventiq_fee'       => (float) $settlement->sum('gateway_fee'),
            'payout_total'     => (float) $settlement->sum('amount_owed_to_org'),
            'payout_settled'   => (float) $settlement->whereNotNull('settlement_id')->sum('amount_owed_to_org'),
            'payout_due'       => (float) $settlement->whereNull('settlement_id')->sum('amount_owed_to_org'),
        ];
    }

    /** Per ticket type: tickets, people and money split the same way. */
    public function byTier(): Collection
    {
        $tickets = $this->liveTickets();
        $payments = $this->approvedPayments();

        return $this->event->tiers()->orderBy('price')->get()->map(function ($tier) use ($tickets, $payments) {
            $tierTickets = $tickets->where('event_tier_id', $tier->id);
            $tierPayments = $payments->whereIn('ticket_id', $tierTickets->pluck('id'));
            $legacy = (float) $this->legacyPaid($tierTickets->where('is_complimentary', false))->sum('amount');

            return [
                'name'             => $tier->tier_name,
                'price'            => (float) $tier->price,
                'tickets'          => $tierTickets->count(),
                'people'           => (int) $tierTickets->sum(fn ($t) => $t->admissions ?? 1),
                'expected'         => (float) $tierTickets->where('is_complimentary', false)->sum('amount'),
                'collected_ventiq' => (float) $tierPayments->where('source', TicketActivationService::SOURCE_VENTIQ_ONLINE)->sum('amount'),
                'collected_direct' => (float) $tierPayments->where('source', TicketActivationService::SOURCE_ORGANIZER_DIRECT)->sum('amount'),
                'collected'        => (float) $tierPayments->sum('amount') + $legacy,
            ];
        });
    }

    private ?Collection $liveTickets = null;
    private ?Collection $approvedPayments = null;

    private function liveTickets(): Collection
    {
        return $this->liveTickets ??= Ticket::where('event_id', $this->event->id)
            ->whereIn('status', self::LIVE_STATUSES)
            ->get();
    }

    /** Money actually confirmed, on tickets that are still live. */
    private function approvedPayments(): Collection
    {
        return $this->approvedPayments ??= TicketPayment::whereIn('ticket_id', $this->liveTickets()->pluck('id'))
            ->where('status', 'approved')
            ->get();
    }

    /** Paid-up tickets with no approved payment behind them (legacy approvals). */
    private function legacyPaid(Collection $tickets): Collection
    {
        $withPayments = $this->approvedPayments()->pluck('ticket_id')->unique();

        return $tickets->where('payment_status', 'completed')->whereNotIn('id', $withPayments);
    }

    private function settlementItems(): Collection
    {
        return SettlementItem::whereIn('ticket_id', $this->liveTickets()->pluck('id'))->get();
    }
}
