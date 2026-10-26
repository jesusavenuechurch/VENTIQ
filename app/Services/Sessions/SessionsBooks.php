<?php

namespace App\Services\Sessions;

use App\Models\Organization;
use App\Models\PaymentSession;
use App\Services\Khoebo\KhoeboClient;
use App\Services\Khoebo\KhoeboCustomers;
use App\Services\Khoebo\KhoeboException;
use App\Services\Khoebo\KhoeboProducts;
use App\Support\SessionPackageDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The Sessions books, kept in their own Khoebo company: an invoice is made
 * (a draft), posted to the ledger (only then can it be paid or sent, and
 * it gets its INV number), paid into the journal the money came by, and
 * emailed to the organization from Khoebo's template.
 */
class SessionsBooks
{
    public readonly KhoeboClient $khoebo;
    private KhoeboCustomers $customers;
    private KhoeboProducts $products;

    public function __construct()
    {
        $this->khoebo = KhoeboClient::for('sessions');
        $this->customers = new KhoeboCustomers($this->khoebo);
        $this->products = new KhoeboProducts($this->khoebo);
    }

    public function configured(): bool
    {
        return $this->khoebo->configured();
    }

    public function productId(string $key): int
    {
        return $this->products->id($key);
    }

    /**
     * An invoice, posted: Khoebo's record (id, reference INV-…, status, amount_due).
     *
     * @param array<int, array{product_id?: int, description: string, quantity: string, unit_price: string}> $lines
     */
    public function invoice(Organization $organization, array $lines, string $reference, ?string $date = null): array
    {
        $draft = $this->khoebo->create('invoices', [
            'customer_id'        => $this->customers->ensure($organization),
            'invoice_date'       => $date ?? today()->toDateString(),
            'external_reference' => $reference,
            'lines'              => $lines,
        ], $reference);

        // The number it's known by comes when it's posted.
        return $this->khoebo->create("invoices/{$draft['id']}/post", [], "{$reference}-post");
    }

    /** A payment against an invoice, into the journal for how it was paid. */
    public function pay(int $invoiceId, string $method, float $amount, string $date, string $reference, string $key): array
    {
        $journal = $this->khoebo->journal($method)
            ?? throw new KhoeboException('Set SESSIONS_KHOEBO_JOURNAL_ECOCASH, _MPESA and _BANK: the Sessions journals payments go to.');

        return $this->khoebo->create("invoices/{$invoiceId}/pay", [
            'journal_id'         => $journal,
            'amount'             => number_format($amount, 2, '.', ''),
            'payment_date'       => $date,
            'external_reference' => $reference,
        ], $key);
    }

    /** Khoebo emails the invoice, with its PDF, to these addresses. */
    public function send(int $invoiceId, array $to, string $key): void
    {
        $to = array_values(array_unique(array_filter($to)));
        if ($to === []) {
            return;
        }
        try {
            $this->khoebo->create("invoices/{$invoiceId}/send", ['attach_pdf' => true, 'to' => $to], $key);
        } catch (KhoeboException $e) {
            // The money is recorded either way; a missing email isn't worth failing for.
            Log::warning('Sessions invoice not emailed by Khoebo', ['invoice' => $invoiceId, 'error' => $e->getMessage()]);
        }
    }

    /** @return array{status: string, paid: float, due: float, reference: ?string} */
    public function state(int $invoiceId): array
    {
        $invoice = $this->khoebo->get("invoices/{$invoiceId}");

        return [
            'status'    => (string) ($invoice['status'] ?? ''),
            'paid'      => (float) ($invoice['amount_paid'] ?? 0),
            'due'       => (float) ($invoice['amount_due'] ?? 0),
            'reference' => $invoice['reference'] ?? null,
        ];
    }

    /**
     * A plan or sessions bought and paid (by mobile money, or a bank
     * transfer confirmed): its invoice, paid, in the Sessions books, and
     * emailed as the receipt. Done once; safe to run again.
     */
    public function recordSale(PaymentSession $payment): void
    {
        if ($payment->payable_type !== 'session_package' || $payment->status !== 'completed' || $payment->khoebo_payment_id) {
            return;
        }
        $organization = Organization::findOrFail($payment->organization_id);
        $meta = $payment->purchase_meta ?? [];
        $paidOn = Carbon::parse($payment->updated_at)->toDateString();
        $key = "ventiq-sessions-pay-{$payment->id}";

        try {
            if (!$payment->khoebo_invoice_id) {
                $invoice = $this->invoice($organization, [$this->line($meta, (float) $payment->amount, $paidOn, $payment->organization_id)], $key, $paidOn);
                $payment->forceFill(['khoebo_invoice_id' => $invoice['id'], 'khoebo_reference' => $invoice['reference'] ?? null])->save();
            }
            $paid = $this->pay((int) $payment->khoebo_invoice_id, $payment->payment_method === 'bank_transfer' ? 'bank_transfer' : (string) $payment->payment_method,
                (float) $payment->amount, $paidOn, $payment->transaction_id ?: $payment->client_reference ?: $key, "{$key}-payment");
            $payment->forceFill(['khoebo_payment_id' => $paid['id'] ?? null, 'khoebo_error' => null])->save();
        } catch (KhoeboException $e) {
            $payment->forceFill(['khoebo_error' => $e->getMessage()])->save();
            throw $e;
        }

        $this->send((int) $payment->khoebo_invoice_id, CorporateBilling::recipientsOf($organization), "{$key}-send");
        Log::info('Sessions sale in Khoebo', ['payment' => $payment->id, 'invoice' => $payment->khoebo_reference]);
    }

    /** The invoice line for what was bought. */
    private function line(array $meta, float $amount, string $date, int $organizationId): array
    {
        $money = number_format($amount, 2, '.', '');
        if (($meta['type'] ?? null) === 'plan') {
            $label = SessionPackageDefinition::get($meta['tier'])['label'] ?? ucfirst((string) $meta['tier']);
            // The month this paid for: the last one added to the plan (a renewal
            // paid early or late covers its own month, not one from the payment date).
            $paidUntil = \App\Models\SessionPackage::where('organization_id', $organizationId)->where('tier', $meta['tier'])
                ->whereNotNull('paid_until')->latest('id')->value('paid_until');
            $from = $paidUntil ? Carbon::parse($paidUntil)->subMonthNoOverflow() : Carbon::parse($date);

            return [
                'product_id'  => $this->productId("plan-{$meta['tier']}"),
                'quantity'    => '1',
                'unit_price'  => $money,
                'description' => "{$label} plan, {$from->format('j M')} to {$from->copy()->addMonthNoOverflow()->subDay()->format('j M Y')}",
            ];
        }
        $quantity = (int) ($meta['quantity'] ?? 1);

        // A bundle is one line at its price: M400 for 3 doesn't divide into cents.
        return [
            'product_id'  => $this->productId('payg'),
            'quantity'    => '1',
            'unit_price'  => $money,
            'description' => "{$quantity} pay-as-you-go " . ($quantity === 1 ? 'session' : 'sessions'),
        ];
    }
}
