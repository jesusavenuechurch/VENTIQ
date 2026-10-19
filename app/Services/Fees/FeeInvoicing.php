<?php

namespace App\Services\Fees;

use App\Models\TicketFee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Log};

/**
 * Billing organizers for VENTIQ's fees on tickets it didn't collect the
 * money for (paid directly to the organizer, free and complimentary).
 *
 * VENTIQ doesn't issue invoices itself: a super admin downloads an
 * organization's uninvoiced fees, raises the invoice in the invoicing
 * system, records its reference here, and later marks it paid. Sponsored
 * fees, tickets no longer valid, and events before the cut-off date are
 * never billed.
 */
class FeeInvoicing
{
    public function uninvoiced(?int $organizationId = null, ?int $eventId = null): Builder
    {
        return TicketFee::query()
            ->where('collection', TicketFee::COLLECT_BY_INVOICE)
            ->where('sponsored', false)
            ->whereNull('invoiced_at')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereHas('ticket', fn ($q) => $q->whereIn('status', ['active', 'checked_in']))
            ->whereHas('event', fn ($q) => $q->where('event_date', '>=', config('constants.fees.invoice_from')));
    }

    /** Per organization: fees ready to invoice. */
    public function toInvoice(): Collection
    {
        return $this->uninvoiced()
            ->select('organization_id', DB::raw('COUNT(*) as tickets'), DB::raw('SUM(people) as people'),
                DB::raw('SUM(total_fee) as total'), DB::raw('MAX(id) as up_to'))
            ->groupBy('organization_id')
            ->with('organization:id,name')
            ->get();
    }

    /** Invoices raised and not yet paid, per organization and reference. */
    public function awaitingPayment(): Collection
    {
        return TicketFee::query()
            ->whereNotNull('invoiced_at')->whereNull('invoice_paid_at')
            ->select('organization_id', 'invoice_reference', DB::raw('COUNT(*) as tickets'),
                DB::raw('SUM(CASE WHEN sponsored = 0 THEN total_fee ELSE 0 END) as total'), DB::raw('MIN(invoiced_at) as invoiced_at'))
            ->groupBy('organization_id', 'invoice_reference')
            ->with('organization:id,name')
            ->get();
    }

    /**
     * The lines behind an organization's next invoice, up to the fee id the
     * super admin saw, so fees recorded meanwhile wait for the next one.
     */
    public function lines(int $organizationId, ?int $upTo = null): Collection
    {
        return $this->uninvoiced($organizationId)
            ->when($upTo, fn ($q) => $q->where('id', '<=', $upTo))
            ->with(['event:id,name,event_date', 'ticket:id,ticket_number,client_id', 'ticket.client:id,full_name'])
            ->orderBy('id')
            ->get();
    }

    /** @return int how many fees were put on the invoice */
    public function markInvoiced(int $organizationId, int $upTo, string $reference): int
    {
        $count = $this->uninvoiced($organizationId)->where('id', '<=', $upTo)
            ->update(['invoiced_at' => now(), 'invoice_reference' => $reference]);

        Log::info('VENTIQ fees invoiced', ['organization' => $organizationId, 'reference' => $reference, 'fees' => $count]);

        return $count;
    }

    public function markPaid(int $organizationId, string $reference): int
    {
        $count = TicketFee::where('organization_id', $organizationId)->where('invoice_reference', $reference)
            ->whereNull('invoice_paid_at')->update(['invoice_paid_at' => now()]);

        Log::info('VENTIQ fee invoice paid', ['organization' => $organizationId, 'reference' => $reference, 'fees' => $count]);

        return $count;
    }

    /** CSV rows for the invoicing system. */
    public function csv(Collection $lines): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Event', 'Event date', 'Ticket', 'Attendee', 'Type', 'People', 'Ticket price', 'Service fee', 'Operational fee', 'Total fee']);
        foreach ($lines as $fee) {
            fputcsv($out, [
                $fee->event?->name, $fee->event?->event_date?->format('Y-m-d'), $fee->ticket?->ticket_number,
                $fee->ticket?->client?->full_name, str_replace('_', ' ', $fee->source), $fee->people,
                number_format((float) $fee->ticket_amount, 2, '.', ''), number_format((float) $fee->service_fee, 2, '.', ''),
                number_format((float) $fee->operational_fee, 2, '.', ''), number_format((float) $fee->total_fee, 2, '.', ''),
            ]);
        }
        fputcsv($out, ['Total', '', '', '', '', $lines->sum('people'), '', '', '', number_format((float) $lines->sum('total_fee'), 2, '.', '')]);
        rewind($out);

        return stream_get_contents($out);
    }
}
