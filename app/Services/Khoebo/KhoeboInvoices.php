<?php

namespace App\Services\Khoebo;

use App\Models\{Event, TicketFee};
use App\Services\Fees\FeeInvoicing;
use Illuminate\Support\Facades\{DB, Log};

/**
 * An event's invoice in Khoebo, the day after the event: what the
 * organizer actually owes. The order made at publish was the most it
 * could owe; the invoice bills the tickets that really happened.
 *
 * Only fees VENTIQ didn't collect are invoiced (tickets paid to the
 * organizer, free and complimentary); fees on online payments already
 * came off the payout. The fees invoiced are marked with the invoice's
 * reference, the same way as an invoice raised from the Money page.
 */
class KhoeboInvoices
{
    public function __construct(
        private KhoeboClient $khoebo,
        private KhoeboCustomers $customers,
        private KhoeboProducts $products,
        private FeeInvoicing $fees,
    ) {}

    /** Why the event gets no invoice (yet), or null when it should have one. */
    public function reasonNotToInvoice(Event $event): ?string
    {
        return match (true) {
            (bool) $event->khoebo_invoice_id => "{$event->name} already has Khoebo invoice {$event->khoebo_invoice_reference}.",
            (bool) $event->fees_sponsored    => "{$event->name}'s fees are sponsored: there's nothing to invoice.",
            !$event->event_date || $event->event_date->isAfter(now()->subDay()->endOfDay()) => "{$event->name} hasn't finished yet: it's invoiced the day after.",
            !$this->fees->uninvoiced(null, $event->id)->exists() => "{$event->name} has no fees to invoice.",
            default => null,
        };
    }

    /** Finished events with fees to invoice and no invoice yet. */
    public function due()
    {
        return Event::query()
            ->whereNull('khoebo_invoice_id')
            ->where('fees_sponsored', false)
            ->where('event_date', '<', now()->startOfDay())
            ->where('created_at', '>=', config('services.khoebo.orders_from'))
            ->whereHas('ticketFees', fn ($q) => $q->where('collection', TicketFee::COLLECT_BY_INVOICE)->whereNull('invoiced_at'))
            ->with('organization');
    }

    /** @return array{people: int, operational: float, service: float, total: float, up_to: int} */
    public function actual(Event $event): array
    {
        $row = $this->fees->uninvoiced(null, $event->id)
            ->selectRaw('SUM(people) as people, SUM(operational_fee) as operational, SUM(service_fee) as service, MAX(id) as up_to')
            ->first();

        return [
            'people'      => (int) $row->people,
            'operational' => round((float) $row->operational, 2),
            'service'     => round((float) $row->service, 2),
            'total'       => round((float) $row->operational + (float) $row->service, 2),
            'up_to'       => (int) $row->up_to,
        ];
    }

    public function make(Event $event): array
    {
        if ($reason = $this->reasonNotToInvoice($event)) {
            throw new KhoeboException($reason);
        }

        $actual = $this->actual($event);
        $invoice = $this->khoebo->create('invoices', $this->body($event, $actual), "ventiq-invoice-event-{$event->id}");
        $reference = $invoice['reference'] ?? "KHOEBO-{$invoice['id']}";

        DB::transaction(function () use ($event, $invoice, $reference, $actual) {
            $event->forceFill(['khoebo_invoice_id' => $invoice['id'], 'khoebo_invoice_reference' => $reference])->saveQuietly();
            $this->fees->uninvoiced(null, $event->id)->where('id', '<=', $actual['up_to'])
                ->update(['invoiced_at' => now(), 'invoice_reference' => $reference]);
        });

        Log::info('Khoebo invoice made', ['event' => $event->id, 'invoice' => $reference, 'total' => $actual['total']]);

        return $invoice;
    }

    public function body(Event $event, array $actual): array
    {
        $perPerson = (float) config('constants.fees.operational_per_person');
        $order = $event->khoebo_order_reference ? " (order {$event->khoebo_order_reference})" : '';
        $money = fn (float $amount) => number_format($amount, 2, '.', '');

        // People × M7.50, unless the fees were recorded at another rate:
        // then the line carries the exact amount, so the invoice matches.
        $byPerson = abs($actual['people'] * $perPerson - $actual['operational']) < 0.005;
        $lines = [[
            'product_id'  => $this->products->id('fee-person'),
            'quantity'    => $byPerson ? $actual['people'] : 1,
            'unit_price'  => $money($byPerson ? $perPerson : $actual['operational']),
            'description' => "{$event->name}{$order}: {$actual['people']} people",
        ]];
        if ($actual['service'] > 0) {
            $lines[] = [
                'product_id'  => $this->products->id('fee-sales'),
                'quantity'    => 1,
                'unit_price'  => $money($actual['service']),
                'description' => "{$event->name}{$order}: ticket sales fee",
            ];
        }

        return [
            'customer_id'        => $this->customers->ensure($event->organization),
            'invoice_date'       => now()->toDateString(),
            'external_reference' => "ventiq-invoice-event-{$event->id}",
            'lines'              => $lines,
        ];
    }
}
