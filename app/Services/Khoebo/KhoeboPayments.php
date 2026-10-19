<?php

namespace App\Services\Khoebo;

use App\Models\{Event, TicketFee, User};
use Illuminate\Support\Facades\Log;

/**
 * A payment from the organizer against the event's Khoebo invoice (or its
 * balance invoice), recorded in Khoebo and on the event. When an invoice
 * is paid in full, its fees are marked paid, as on the Money page.
 */
class KhoeboPayments
{
    public const METHODS = ['bank_transfer' => 'Bank transfer', 'ecocash' => 'EcoCash', 'mpesa' => 'M-Pesa', 'cash' => 'Cash'];

    public function __construct(private KhoeboClient $khoebo) {}

    /** What's still owed on each of the event's invoices. @return array{invoice: float, balance: float} */
    public function outstanding(Event $event): array
    {
        return [
            'invoice' => max(0, round((float) $event->khoebo_invoice_total - (float) $event->khoebo_paid_total, 2)),
            'balance' => max(0, round((float) $event->khoebo_balance_total - (float) $event->khoebo_balance_paid, 2)),
        ];
    }

    public function record(Event $event, float $amount, string $date, string $method, ?string $reference, User $by, bool $balance = false): array
    {
        $invoiceId = $balance ? $event->khoebo_balance_invoice_id : $event->khoebo_invoice_id;
        $invoiceRef = $balance ? $event->khoebo_balance_invoice_reference : $event->khoebo_invoice_reference;
        if (! $invoiceId) {
            throw new KhoeboException("{$event->name} has no " . ($balance ? 'balance ' : '') . 'invoice in Khoebo yet.');
        }

        $owed = $this->outstanding($event)[$balance ? 'balance' : 'invoice'];
        if ($amount <= 0 || $amount > $owed + 0.005) {
            throw new KhoeboException('Enter an amount up to the M' . number_format($owed, 2) . " still owed on {$invoiceRef}.");
        }

        $journal = config("services.khoebo.journals.{$method}") ?: config('services.khoebo.journals.default');
        if (! $journal) {
            throw new KhoeboException('Set KHOEBO_JOURNAL_ID: the Khoebo journal payments are recorded in.');
        }

        $money = number_format($amount, 2, '.', '');
        $key = "ventiq-payment-{$invoiceId}-" . substr(sha1("{$money}|{$date}|{$method}|{$reference}"), 0, 12);
        $payment = $this->khoebo->create("invoices/{$invoiceId}/pay", array_filter([
            'journal_id'         => (int) $journal,
            'amount'             => $money,
            'payment_date'       => $date,
            'external_reference' => $reference ?: $key,
        ]), $key);

        $column = $balance ? 'khoebo_balance_paid' : 'khoebo_paid_total';
        $event->forceFill([$column => round((float) $event->{$column} + $amount, 2)])->saveQuietly();

        if ($this->outstanding($event->fresh())[$balance ? 'balance' : 'invoice'] <= 0.005) {
            TicketFee::where('event_id', $event->id)->where('invoice_reference', $invoiceRef)
                ->whereNull('invoice_paid_at')->update(['invoice_paid_at' => now()]);
        }

        Log::info('Khoebo payment recorded', ['event' => $event->id, 'invoice' => $invoiceRef, 'amount' => $money, 'by' => $by->id]);

        return $payment;
    }
}
