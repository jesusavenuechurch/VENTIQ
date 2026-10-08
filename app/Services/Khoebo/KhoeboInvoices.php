<?php

namespace App\Services\Khoebo;

use App\Models\{Event, TicketFee};
use App\Services\Fees\FeeInvoicing;
use Illuminate\Support\Facades\{DB, Log};

/**
 * An event's invoice in Khoebo: what the organizer actually owes VENTIQ.
 *
 * Usually made the day after the event, from the tickets that really
 * happened. A customer who wants to pay ahead (a company bringing 50
 * people) can be invoiced before the event instead, for the event's order
 * (the most it could owe); the day after, only attendance beyond that
 * prepaid invoice is billed, as a balance invoice.
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
        private KhoeboOrders $orders,
        private FeeInvoicing $fees,
    ) {}

    /** Why the day-after invoicing has nothing to do for the event, or null. */
    public function reasonNotToInvoice(Event $event): ?string
    {
        return match (true) {
            (bool) $event->fees_sponsored => "{$event->name}'s fees are sponsored: there's nothing to invoice.",
            // Fees that arrive after the invoice (a late ticket) are left
            // for the Money page rather than billed twice.
            $event->khoebo_invoice_id && !$event->khoebo_invoice_prepaid => "{$event->name} already has Khoebo invoice {$event->khoebo_invoice_reference}.",
            !$event->event_date || $event->event_date->isAfter(now()->subDay()->endOfDay()) => "{$event->name} hasn't finished yet: it's invoiced the day after.",
            !$this->fees->uninvoiced(null, $event->id)->exists() => $event->khoebo_invoice_id
                ? "{$event->name} already has Khoebo invoice {$event->khoebo_invoice_reference}."
                : "{$event->name} has no fees to invoice.",
            default => null,
        };
    }

    /** Finished events with fees not yet on an invoice. */
    public function due()
    {
        return Event::query()
            ->where('fees_sponsored', false)
            ->where(fn ($q) => $q->whereNull('khoebo_invoice_id')->orWhere('khoebo_invoice_prepaid', true))
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

    /**
     * The day after the event: invoice what's owed, or, after a prepaid
     * invoice, any balance beyond it.
     *
     * @return array{reference: string, total: float, kind: string}
     */
    public function make(Event $event): array
    {
        if ($reason = $this->reasonNotToInvoice($event)) {
            throw new KhoeboException($reason);
        }

        return $event->khoebo_invoice_prepaid ? $this->settlePrepaid($event) : $this->invoiceActual($event);
    }

    /** Before the event, for a customer paying ahead: the event's order as an invoice. */
    public function invoiceNow(Event $event): array
    {
        if ($event->khoebo_invoice_id) {
            throw new KhoeboException("{$event->name} already has Khoebo invoice {$event->khoebo_invoice_reference}.");
        }
        if ($event->fees_sponsored) {
            throw new KhoeboException("{$event->name}'s fees are sponsored: there's nothing to invoice.");
        }

        $possible = $this->orders->possible($event);
        if ($possible['people'] < 1) {
            throw new KhoeboException("{$event->name} has no ticket numbers yet.");
        }

        $lines = $this->lines($event, $possible['people'], $possible['person_fee'], $possible['sales_fee'], 'prepaid for');
        $invoice = $this->send($event, $lines, "ventiq-invoice-event-{$event->id}");

        $event->forceFill([
            'khoebo_invoice_id'        => $invoice['id'],
            'khoebo_invoice_reference' => $invoice['reference'],
            'khoebo_invoice_total'     => $possible['total'],
            'khoebo_invoice_prepaid'   => true,
        ])->saveQuietly();

        Log::info('Khoebo prepaid invoice made', ['event' => $event->id, 'invoice' => $invoice['reference'], 'total' => $possible['total']]);

        return ['reference' => $invoice['reference'], 'total' => $possible['total'], 'kind' => 'prepaid'];
    }

    private function invoiceActual(Event $event): array
    {
        $actual = $this->actual($event);
        $lines = $this->lines($event, $actual['people'], $actual['operational'], $actual['service']);
        $invoice = $this->send($event, $lines, "ventiq-invoice-event-{$event->id}");

        DB::transaction(function () use ($event, $invoice, $actual) {
            $event->forceFill([
                'khoebo_invoice_id'        => $invoice['id'],
                'khoebo_invoice_reference' => $invoice['reference'],
                'khoebo_invoice_total'     => $actual['total'],
            ])->saveQuietly();
            $this->markInvoiced($event, $actual['up_to'], $invoice['reference'], false);
        });

        Log::info('Khoebo invoice made', ['event' => $event->id, 'invoice' => $invoice['reference'], 'total' => $actual['total']]);

        return ['reference' => $invoice['reference'], 'total' => $actual['total'], 'kind' => 'invoice'];
    }

    /**
     * After a prepaid invoice: the fees are covered by it up to its total;
     * attendance beyond that is billed as a balance. Coming in under it is
     * not refunded: the customer paid for the places they booked.
     */
    private function settlePrepaid(Event $event): array
    {
        $actual = $this->actual($event);
        $already = (float) $event->khoebo_invoice_total + (float) $event->khoebo_balance_total;
        $extra = round($actual['total'] - $already, 2);
        $prepaidPaid = (float) $event->khoebo_paid_total >= (float) $event->khoebo_invoice_total - 0.005;

        if ($extra <= 0.005 || $event->khoebo_balance_invoice_id) {
            // Within what was prepaid: the fees are on the prepaid invoice.
            $this->markInvoiced($event, $actual['up_to'], $event->khoebo_invoice_reference, $prepaidPaid);

            return ['reference' => $event->khoebo_invoice_reference, 'total' => 0.0, 'kind' => 'covered'];
        }

        $order = $event->khoebo_invoice_reference;
        $lines = [[
            'product_id'  => $this->products->id('fee-person'),
            'quantity'    => 1,
            'unit_price'  => number_format($extra, 2, '.', ''),
            'description' => "{$event->name}: attendance beyond prepaid invoice {$order} ({$actual['people']} people in all)",
        ]];
        $invoice = $this->send($event, $lines, "ventiq-invoice-event-{$event->id}-balance");

        DB::transaction(function () use ($event, $invoice, $extra, $actual) {
            $event->forceFill([
                'khoebo_balance_invoice_id'        => $invoice['id'],
                'khoebo_balance_invoice_reference' => $invoice['reference'],
                'khoebo_balance_total'             => $extra,
            ])->saveQuietly();
            // Fees past the prepaid amount are what the balance is for.
            $this->markInvoiced($event, $actual['up_to'], $invoice['reference'], false);
        });

        Log::info('Khoebo balance invoice made', ['event' => $event->id, 'invoice' => $invoice['reference'], 'total' => $extra]);

        return ['reference' => $invoice['reference'], 'total' => $extra, 'kind' => 'balance'];
    }

    private function markInvoiced(Event $event, int $upTo, string $reference, bool $paid): void
    {
        $this->fees->uninvoiced(null, $event->id)->where('id', '<=', $upTo)->update(array_filter([
            'invoiced_at'       => now(),
            'invoice_reference' => $reference,
            'invoice_paid_at'   => $paid ? now() : null,
        ]));
    }

    private function lines(Event $event, int $people, float $operational, float $service, string $for = ''): array
    {
        $perPerson = (float) config('constants.fees.operational_per_person');
        $order = $event->khoebo_order_reference ? " (order {$event->khoebo_order_reference})" : '';
        $money = fn (float $amount) => number_format($amount, 2, '.', '');
        $what = $for ? "{$for} {$people} people" : "{$people} people";

        // People × M7.50, unless the fees were recorded at another rate:
        // then the line carries the exact amount, so the invoice matches.
        $byPerson = abs($people * $perPerson - $operational) < 0.005;
        $lines = [[
            'product_id'  => $this->products->id('fee-person'),
            'quantity'    => $byPerson ? $people : 1,
            'unit_price'  => $money($byPerson ? $perPerson : $operational),
            'description' => "{$event->name}{$order}: {$what}",
        ]];
        if ($service > 0) {
            $lines[] = [
                'product_id'  => $this->products->id('fee-sales'),
                'quantity'    => 1,
                'unit_price'  => $money($service),
                'description' => "{$event->name}{$order}: ticket sales fee",
            ];
        }

        return $lines;
    }

    private function send(Event $event, array $lines, string $key): array
    {
        $invoice = $this->khoebo->create('invoices', [
            'customer_id'        => $this->customers->ensure($event->organization),
            'invoice_date'       => now()->toDateString(),
            'external_reference' => $key,
            'lines'              => $lines,
        ], $key);

        $invoice['reference'] = $invoice['reference'] ?? "KHOEBO-{$invoice['id']}";

        return $invoice;
    }
}
