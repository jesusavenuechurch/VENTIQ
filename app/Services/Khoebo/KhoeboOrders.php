<?php

namespace App\Services\Khoebo;

use App\Models\Event;

/**
 * An event's order in Khoebo: the most it could owe VENTIQ, from the ticket
 * numbers the organizer entered (M7.50 a person, 4.9% of possible sales).
 * The invoice, after the event, bills what actually happened. Orders are
 * made as drafts: Khoebo confirms them through its own approval.
 */
class KhoeboOrders
{
    public function __construct(
        private KhoeboClient $khoebo,
        private KhoeboCustomers $customers,
        private KhoeboProducts $products,
    ) {}

    /** @return array{people: int, sales: float, person_fee: float, sales_fee: float, total: float} */
    public function possible(Event $event): array
    {
        $tiers = $event->tiers()->where('is_active', true)->get();
        $people = (int) $tiers->sum(fn ($t) => (int) $t->quantity_available * max(1, (int) $t->quantity_per_purchase));
        $sales = (float) $tiers->sum(fn ($t) => (int) $t->quantity_available * (float) $t->price);

        $personFee = round($people * (float) config('constants.fees.operational_per_person'), 2);
        $salesFee = round($sales * (float) config('constants.fees.service_percent'), 2);

        return [
            'people'     => $people,
            'sales'      => $sales,
            'person_fee' => $personFee,
            'sales_fee'  => $salesFee,
            'total'      => round($personFee + $salesFee, 2),
        ];
    }

    /** Why the event gets no order (yet), or null when it should have one. */
    public function reasonNotToOrder(Event $event): ?string
    {
        return match (true) {
            (bool) $event->khoebo_order_id => "{$event->name} already has Khoebo order {$event->khoebo_order_reference}.",
            (bool) $event->fees_sponsored  => "{$event->name}'s fees are sponsored: there's nothing to order.",
            $this->possible($event)['people'] < 1 => "{$event->name} has no ticket numbers yet.",
            default => null,
        };
    }

    /**
     * Published events since orders went automatic that have no order yet,
     * e.g. because Khoebo was down when they were published.
     */
    public function missing()
    {
        return Event::query()
            ->whereIn('status', Event::OPEN_STATUSES)
            ->whereNull('khoebo_order_id')
            ->where('fees_sponsored', false)
            ->where('created_at', '>=', config('services.khoebo.orders_from'))
            ->where('event_date', '>=', now()->startOfDay())
            ->with('organization');
    }

    /** Make the event's draft order in Khoebo, once. */
    public function make(Event $event): array
    {
        if ($reason = $this->reasonNotToOrder($event)) {
            throw new KhoeboException($reason);
        }

        $possible = $this->possible($event);
        $order = $this->khoebo->create('orders', $this->body($event, $possible), "ventiq-order-event-{$event->id}");

        $event->forceFill(['khoebo_order_id' => $order['id'], 'khoebo_order_reference' => $order['reference'] ?? null])->saveQuietly();

        return $order;
    }

    public function body(Event $event, array $possible): array
    {
        $percent = rtrim(rtrim(number_format(config('constants.fees.service_percent') * 100, 2), '0'), '.');
        $money = fn (float $amount) => number_format($amount, 2, '.', '');

        $lines = [[
            'product_id'  => $this->products->id('fee-person'),
            'quantity'    => (string) $possible['people'],
            'unit_price'  => $money((float) config('constants.fees.operational_per_person')),
            'description' => "{$event->name}: {$possible['people']} people",
        ]];
        // A free event has no sales, so no sales fee.
        if ($possible['sales'] > 0) {
            $lines[] = [
                'product_id'  => $this->products->id('fee-sales'),
                'quantity'    => '1',
                'unit_price'  => $money($possible['sales_fee']),
                'description' => "{$event->name}: {$percent}% of M" . number_format($possible['sales'], 2) . ' possible ticket sales',
            ];
        }

        return array_filter([
            'customer_id'        => $this->customers->ensure($event->organization),
            'payment_term_id'    => config('services.khoebo.payment_term_id') ? (int) config('services.khoebo.payment_term_id') : null,
            'external_reference' => "ventiq-event-{$event->id}",
            'notes'              => "{$event->name}, " . $event->event_date?->format('j F Y'),
            'lines'              => $lines,
        ], fn ($value) => $value !== null);
    }
}
