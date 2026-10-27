<?php

namespace App\Http\Controllers;

use App\Models\{MobileMoneyMessage, Organization, PaymentSession, Settlement, SettlementItem, Ticket, TicketFee};
use App\Services\MobileMoney\MobileMoneyInbox;
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

        $summary = [
            'fees_from_payouts'    => (float) SettlementItem::sum('gateway_fee'),
            'fees_invoiced_paid'   => (float) TicketFee::whereNotNull('invoice_paid_at')->where('sponsored', false)->sum('total_fee'),
            'fees_invoiced_unpaid' => (float) $awaiting->sum('total'),
            'fees_to_invoice'      => (float) $toInvoice->sum('total'),
            'fees_sponsored'       => (float) TicketFee::where('sponsored', true)->sum('total_fee'),
            'held_for_organizers'  => (float) (clone $payable)->sum('amount_owed_to_org'),
            'in_payout_batches'    => (float) Settlement::where('status', 'pending')->sum('amount_owed_to_org'),
            'paid_out'             => (float) Settlement::where('status', 'settled')->sum('amount_owed_to_org'),
            // The gateway's cut of every online payment, which VENTIQ absorbs.
            'gateway_costs'        => round((float) SettlementItem::sum('amount_received') * (float) config('constants.payment.gateway_fee_rate'), 2),
        ];
        $lastHeard = MobileMoneyInbox::lastHeard();
        $quietHours = (int) config('services.mobile_money.quiet_hours', 24);
        $ticketLine = fn (?Ticket $t) => $t ? [
            'holder' => $t->client?->full_name, 'phone' => $t->client?->phone, 'event' => $t->event?->name,
            'organization' => $t->event?->organization?->name, 'code' => $t->voucher_code,
        ] : null;

        return \Inertia\Inertia::render('Ventiq/Money', [
            'summary'     => $summary,
            'gatewayRate' => (float) config('constants.payment.gateway_fee_rate'),
            'invoiceFrom' => \Illuminate\Support\Carbon::parse(config('constants.fees.invoice_from'))->format('j M Y'),
            'methods'     => ['ecocash' => 'EcoCash', 'mpesa' => 'M-Pesa', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash'],
            'payoutsDue'  => $payoutsDue->map(fn ($row) => [
                'organizationId' => $row->organization_id,
                'organization'   => $row->organization?->name ?? "Organization #{$row->organization_id}",
                'lines'          => (int) $row->line_count,
                'owed'           => (float) $row->owed,
                'fees'           => (float) $row->fees,
                'payoutTo'       => $this->payoutTo($row->organization),
            ])->values(),
            'batches'     => Settlement::with('organization')->where('status', 'pending')->latest()->get()->map(fn (Settlement $b) => [
                'id'           => $b->id,
                'organization' => $b->organization?->name,
                'amount'       => (float) $b->amount_owed_to_org,
                'created'      => $b->created_at->format('j M, H:i'),
                'payoutTo'     => $this->payoutTo($b->organization),
            ])->values(),
            'toInvoice'   => $toInvoice->map(fn ($row) => [
                'organizationId' => $row->organization_id,
                'organization'   => $row->organization?->name,
                'tickets'        => (int) $row->tickets,
                'people'         => (int) $row->people,
                'total'          => (float) $row->total,
                'upTo'           => (int) $row->up_to,
            ])->values(),
            'awaiting'    => $awaiting->map(fn ($row) => [
                'organizationId' => $row->organization_id,
                'organization'   => $row->organization?->name,
                'reference'      => $row->invoice_reference,
                'tickets'        => (int) $row->tickets,
                'total'          => (float) $row->total,
                'invoiced'       => \Illuminate\Support\Carbon::parse($row->invoiced_at)->format('j M Y'),
            ])->values(),
            // Online payments with no answer from PayLesotho yet: checked by
            // hand against the merchant statement until its callbacks work.
            // Pushes EcoCash never answered (after the ~2 minute wait), and
            // attendees who paid the merchant by hand (straight away).
            'toCheck'     => PaymentSession::where('payable_type', 'ticket')->whereIn('gateway', ['paylesotho', 'bank'])
                ->where('status', 'pending')
                ->where(fn ($q) => $q->where('created_at', '<', now()->subMinutes(3))->orWhereNotNull('purchase_meta'))
                ->where('created_at', '>', now()->subDays(7))->latest()->limit(100)->get()
                ->map(function (PaymentSession $s) use ($ticketLine) {
                    $hand = $s->callback_payload['by_hand'] ?? null;

                    return [
                        'id'        => $s->id,
                        'amount'    => (float) $s->amount,
                        'method'    => \App\Services\Payments\PaymentGatewayFactory::CATALOG[$s->payment_method] ?? ucfirst((string) $s->payment_method),
                        'at'        => $s->created_at->format('j M, H:i'),
                        'reference' => $s->client_reference,
                        'transaction' => $s->transaction_id,
                        'ticket'    => $ticketLine(Ticket::with(['client', 'event.organization'])->find($s->payable_id)),
                        'byHand'    => $hand ? [
                            'from'      => $hand['paid_from'] ?? null,
                            'reference' => $hand['reference'] ?? null,
                            'proofUrl'  => ($hand['proof_path'] ?? null) ? route('ventiq.money.online.proof', $s) : null,
                        ] : null,
                        'pushTo'    => $s->callback_payload['push_to'] ?? null,
                        'noAnswer'  => (bool) ($s->callback_payload['no_answer'] ?? false),
                    ];
                })->values(),
            // EcoCash SMS the merchant SIM forwarded that couldn't be matched
            // (or weren't trusted) on their own, and when it last forwarded.
            'sms'         => [
                'setUp'     => filled(config('services.mobile_money.token')),
                'lastHeard' => $lastHeard?->diffForHumans(),
                'quiet'     => !$lastHeard || $lastHeard->lt(now()->subHours($quietHours)),
                'toCheck'   => MobileMoneyMessage::toCheck()->with(['ticket.client', 'ticket.event'])->latest('received_at')->limit(100)->get()
                    ->map(fn (MobileMoneyMessage $m) => [
                        'id'          => $m->id,
                        'amount'      => (float) $m->amount,
                        'payer'       => $m->payer_name,
                        'payerPhone'  => $m->payer_phone,
                        'at'          => $m->received_at->format('j M, H:i'),
                        'transaction' => $m->transaction_id,
                        'reference'   => $m->reference,
                        'balance'     => (float) $m->balance_after,
                        'note'        => $m->note,
                        'held'        => $m->status === 'held',
                        'canAccept'   => $m->status === 'held' && $m->balance_ok === false,
                        'ticket'      => $m->ticket ? ['code' => $m->ticket->voucher_code, 'holder' => $m->ticket->holder_name, 'event' => $m->ticket->event?->name] : null,
                    ])->values(),
            ],
            // Money that came in for a ticket already paid: to send back.
            'paidTwice'   => PaymentSession::where('payable_type', 'ticket')->where('status', 'completed')
                ->where('callback_payload->paid_twice', true)->latest()->limit(200)->get()
                ->reject(fn ($s) => isset($s->callback_payload['refunded_at']))
                ->map(fn (PaymentSession $s) => [
                    'id'          => $s->id,
                    'amount'      => (float) $s->amount,
                    'method'      => ucfirst((string) $s->payment_method),
                    'at'          => $s->created_at->format('j M, H:i'),
                    'transaction' => $s->transaction_id,
                    'phone'       => $s->callback_payload['push_to'] ?? null,
                    'ticket'      => $ticketLine(Ticket::with(['client', 'event.organization'])->find($s->payable_id)),
                ])->values(),
            'meta'        => ['title' => 'VENTIQ money | VENTIQ'],
        ]);
    }

    /** Where an organization's payout goes, and whether to check it first. @return array<string, mixed> */
    private function payoutTo(?Organization $org): array
    {
        return [
            'summary'       => $org?->hasPayoutDetails() ? $org->payoutSummary() : null,
            'changed'       => $org?->hasPayoutDetails() && $org->payoutRecentlyChanged() ? $org->payout_updated_at->diffForHumans() : null,
            'phone'         => $org?->phone,
        ];
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

        return back()->with('status', "Received. {$ticket->holder_name}'s ticket is active and on its way to them.");
    }

    /** A forwarded EcoCash payment: matched to a ticket by entry code, accepted as real, or set aside. */
    public function decideSms(Request $request, MobileMoneyMessage $message, MobileMoneyInbox $inbox)
    {
        $data = $request->validate([
            'decision'   => ['required', 'in:match,accept,dismiss'],
            'entry_code' => ['required_if:decision,match', 'nullable', 'string', 'max:20'],
            'note'       => ['nullable', 'string', 'max:200'],
        ]);

        try {
            return match ($data['decision']) {
                'match' => back()->with('status', 'Matched. ' . $inbox->matchToTicket($message, $data['entry_code'], $request->user()->id)->holder_name . "'s ticket is active and on its way to them."),
                'accept' => back()->with('status', match ($inbox->accept($message, $request->user()->id)->status) {
                    MobileMoneyMessage::UNMATCHED => 'Accepted as real. No ticket matches it yet: match it by entry code.',
                    MobileMoneyMessage::HELD      => 'Accepted as real, but it still needs matching by entry code.',
                    default                       => 'Accepted and matched: the ticket is active.',
                }),
                'dismiss' => tap(back()->with('status', 'Set aside: not for a ticket.'), fn () => $inbox->dismiss($message, $request->user()->id, $data['note'] ?? null)),
            };
        } catch (InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }
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
