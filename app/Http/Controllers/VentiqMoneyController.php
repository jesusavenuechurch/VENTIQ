<?php

namespace App\Http\Controllers;

use App\Models\{Organization, PaymentSession, Settlement, SettlementItem, Ticket, TicketFee};
use App\Services\Payments\TicketActivationService;
use App\Services\Fees\FeeInvoicing;
use App\Services\Payments\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * VENTIQ's own money, for super admins: what it has earned, what it
 * holds for organizers and must pay out, and the fees it has to invoice.
 */
class VentiqMoneyController extends Controller
{
    public function __construct(private SettlementService $settlements, private FeeInvoicing $invoicing) {}

    public function index()
    {
        $payable = SettlementItem::whereNull('settlement_id')
            ->whereHas('ticket', fn ($q) => $q->whereIn('status', ['active', 'checked_in']));

        $payoutsDue = (clone $payable)
            ->select('organization_id', DB::raw('COUNT(*) as line_count'), DB::raw('SUM(amount_owed_to_org) as owed'), DB::raw('SUM(gateway_fee) as fees'))
            ->groupBy('organization_id')->get()
            ->each(fn ($row) => $row->setRelation('organization', Organization::find($row->organization_id)));

        $toInvoice = $this->invoicing->toInvoice();
        $awaiting = $this->invoicing->awaitingPayment();

        return view('ventiq-money', [
            'summary' => [
                'fees_from_payouts' => (float) SettlementItem::sum('gateway_fee'),
                'fees_invoiced_paid' => (float) TicketFee::whereNotNull('invoice_paid_at')->where('sponsored', false)->sum('total_fee'),
                'fees_invoiced_unpaid' => (float) $awaiting->sum('total'),
                'fees_to_invoice' => (float) $toInvoice->sum('total'),
                'fees_sponsored' => (float) TicketFee::where('sponsored', true)->sum('total_fee'),
                'held_for_organizers' => (float) (clone $payable)->sum('amount_owed_to_org'),
                'in_payout_batches' => (float) Settlement::where('status', 'pending')->sum('amount_owed_to_org'),
                'paid_out' => (float) Settlement::where('status', 'settled')->sum('amount_owed_to_org'),
                // The gateway's cut of every online payment, which VENTIQ absorbs.
                'gateway_costs' => round((float) SettlementItem::sum('amount_received') * (float) config('constants.payment.gateway_fee_rate'), 2),
            ],
            'gatewayRate' => (float) config('constants.payment.gateway_fee_rate'),
            'payoutsDue' => $payoutsDue,
            'batches'    => Settlement::with('organization:id,name')->where('status', 'pending')->latest()->get(),
            'toInvoice'  => $toInvoice,
            'awaiting'   => $awaiting,
            'methods'    => ['ecocash' => 'EcoCash', 'mpesa' => 'M-Pesa', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash'],
            'invoiceFrom' => config('constants.fees.invoice_from'),
            // Online payments with no answer from PayLesotho yet: checked by
            // hand against the merchant statement until its callbacks work.
            // Pushes EcoCash never answered (after the ~2 minute wait), and
            // attendees who paid the merchant by hand (straight away).
            'toCheck'    => PaymentSession::where('payable_type', 'ticket')->where('gateway', 'paylesotho')
                ->where('status', 'pending')
                ->where(fn ($q) => $q->where('created_at', '<', now()->subMinutes(3))->orWhereNotNull('purchase_meta'))
                ->where('created_at', '>', now()->subDays(7))->latest()->limit(100)->get()
                ->each(fn ($s) => $s->setRelation('ticket', Ticket::with(['client', 'event.organization'])->find($s->payable_id))),
            // Money that came in for a ticket already paid: to send back.
            'paidTwice'  => PaymentSession::where('payable_type', 'ticket')->where('status', 'completed')
                ->where('callback_payload->paid_twice', true)->latest()->limit(200)->get()
                ->reject(fn ($s) => isset($s->callback_payload['refunded_at']))
                ->each(fn ($s) => $s->setRelation('ticket', Ticket::with(['client', 'event.organization'])->find($s->payable_id))),
        ]);
    }

    public function createPayout(Request $request, Organization $organization)
    {
        $batch = $this->settlements->createBatch($organization->id, 'manual', $request->user());

        return back()->with('status', $batch
            ? "Payout batch of M" . number_format((float) $batch->amount_owed_to_org, 2) . " ready for {$organization->name}. Pay them, then mark it paid."
            : "Nothing is owed to {$organization->name} right now.");
    }

    public function markPayoutPaid(Request $request, Settlement $settlement)
    {
        $data = $request->validate([
            'method'    => ['required', 'in:ecocash,mpesa,bank_transfer,cash'],
            'reference' => ['required', 'string', 'max:100'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $paid = $this->settlements->markPaid($settlement, $data['method'], $data['reference'], $data['notes'] ?? null, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', 'Payout of M' . number_format((float) $paid->amount_owed_to_org, 2) . " to {$settlement->organization->name} recorded.");
    }

    public function feesCsv(Request $request, Organization $organization)
    {
        $lines = $this->invoicing->lines($organization->id, $request->integer('up_to') ?: null);
        $name = Str::slug($organization->name) . '-ventiq-fees-' . now()->format('Y-m-d') . '.csv';

        return response($this->invoicing->csv($lines), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    public function markInvoiced(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'up_to'     => ['required', 'integer'],
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $count = $this->invoicing->markInvoiced($organization->id, (int) $data['up_to'], $data['reference']);

        return back()->with('status', "{$count} " . Str::plural('fee', $count) . " on invoice {$data['reference']} for {$organization->name}.");
    }

    public function markInvoicePaid(Request $request, Organization $organization)
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:100']]);
        $this->invoicing->markPaid($organization->id, $data['reference']);

        return back()->with('status', "Invoice {$data['reference']} from {$organization->name} marked paid.");
    }

    /**
     * A super admin has checked an online payment against the merchant
     * statement: received activates the ticket as an online sale (fees off
     * the payout), not received closes it.
     */
    public function decideOnlinePayment(Request $request, PaymentSession $session, TicketActivationService $activation)
    {
        abort_unless($session->payable_type === 'ticket' && $session->status === 'pending', 404);
        $data = $request->validate([
            'decision'  => ['required', 'in:received,not_received'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['decision'] === 'not_received') {
            $session->update(['status' => 'failed', 'callback_payload' => array_merge($session->callback_payload ?? [], ['manual' => ['by' => $request->user()->id, 'decision' => 'not_received', 'at' => now()->toIso8601String()]])]);

            return back()->with('status', 'Marked as not received. The attendee can pay again.');
        }

        $session->update([
            'status'           => 'completed',
            'transaction_id'   => ($data['reference'] ?? null) ?: $session->transaction_id,
            'callback_payload' => array_merge($session->callback_payload ?? [], ['manual' => ['by' => $request->user()->id, 'decision' => 'received', 'at' => now()->toIso8601String()]]),
        ]);

        $ticket = Ticket::with(['client', 'event', 'tier'])->findOrFail($session->payable_id);
        $activation->activate($ticket, TicketActivationService::SOURCE_VENTIQ_ONLINE, $session->payment_method, $session->transaction_id, $request->user()->id, $session);

        return back()->with('status', "Received. {$ticket->client->full_name}'s ticket is active and on its way to them.");
    }

    public function onlineProof(PaymentSession $session)
    {
        return \App\Support\PaymentProof::response($session->callback_payload['by_hand']['proof_path'] ?? null);
    }

    public function markRefunded(Request $request, PaymentSession $session)
    {
        abort_unless(($session->callback_payload['paid_twice'] ?? false) === true, 404);
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);

        $session->update(['callback_payload' => array_merge($session->callback_payload, [
            'refunded_at' => now()->toIso8601String(), 'refunded_by' => $request->user()->id, 'refund_reference' => $data['reference'] ?? null,
        ])]);

        return back()->with('status', 'Marked as refunded.');
    }
}
