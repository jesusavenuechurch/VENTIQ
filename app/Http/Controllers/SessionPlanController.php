<?php

namespace App\Http\Controllers;

use App\Models\PaymentSession;
use App\Models\SessionPackage;
use App\Services\Sessions\BankTransfers;
use App\Support\BankDetails;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\SessionQuotaService;
use App\Support\SessionPackageDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/** An organization's Sessions plan: what it has this month, and buying more. */
class SessionPlanController extends Controller
{
    public function show(SessionQuotaService $quota)
    {
        $organization = Auth::user()->organization;
        $plan = $quota->currentPlan($organization);
        $tiers = SessionPackageDefinition::tiers();

        return Inertia::render('Sessions/Plan', [
            'plan' => [
                'tier'      => $plan->tier,
                'label'     => $tiers[$plan->tier]['label'] ?? ucfirst($plan->tier),
                'sessions'  => ['used' => $plan->sessions_used, 'included' => $plan->sessions_included],
                'whatsapp'  => ['used' => $plan->whatsapp_used, 'included' => $plan->whatsapp_included],
                'renews'    => $plan->period_end?->format('d M Y'),
                'payg'      => $quota->remainingSessions($organization) - $plan->remaining_sessions,
                // Paying for the next month: paid up, due soon, or on grace.
                'renewal'   => app(\App\Services\Sessions\PlanRenewals::class)->status($plan),
                'renewUrl'  => $plan->renews() ? route('organization.session-plan.payment', ['type' => 'plan', 'tier' => $plan->tier]) : null,
            ],
            // Enterprise is agreed with us, not bought here.
            'tiers' => collect($tiers)->except(['free', 'enterprise'])->map(fn ($t, $key) => [
                'key'         => $key,
                'label'       => $t['label'],
                'price'       => $t['price'],
                'description' => $t['description'],
                'current'     => $key === $plan->tier,
            ])->values(),
            'payg'    => collect(SessionPackageDefinition::paygPresets())->map(fn ($price, $qty) => ['quantity' => $qty, 'price' => $price])->values(),
            'history' => SessionPackage::where('organization_id', $organization->id)->latest()->take(20)->get()->map(fn ($p) => [
                'id'       => $p->id,
                'label'    => $tiers[$p->tier]['label'] ?? ($p->tier === 'payg' ? 'Pay as you go' : ucfirst($p->tier)),
                'status'   => $p->status,
                'sessions' => "{$p->sessions_used} / {$p->sessions_included}",
                'paid'     => (float) $p->price_paid,
                'date'     => $p->created_at->format('d M Y'),
            ]),
            'support' => config('ventiq.emails.support'),
            // Paid by bank transfer, waiting for VENTIQ to see it in the account.
            'transfers' => PaymentSession::where('organization_id', $organization->id)->where('gateway', BankTransfers::GATEWAY)->where('payable_type', 'session_package')
                ->where('status', 'pending')->latest()->get()->map(fn (PaymentSession $p) => [
                    'id'        => $p->id,
                    'label'     => $p->purchase_meta['label'] ?? 'Sessions',
                    'amount'    => (float) $p->amount,
                    'reference' => $p->client_reference,
                    'paidOn'    => !empty($p->callback_payload['paid_on']) ? \Illuminate\Support\Carbon::parse($p->callback_payload['paid_on'])->format('j M Y') : null,
                ]),
            'bank'    => BankDetails::configured() ? BankDetails::lines() : [],
            // A corporate rate agreed with VENTIQ, instead of the plans.
            'corporate' => ($agreement = app(\App\Services\Sessions\CorporateBilling::class)->agreement($organization)) ? [
                'rate'     => (float) $agreement->rate,
                'free'     => $agreement->free_per_month,
                'since'    => $agreement->starts_on->format('j M Y'),
                'month'    => app(\App\Services\Sessions\CorporateBilling::class)->monthToDate($organization),
                'invoiced' => now()->addMonthNoOverflow()->startOfMonth()->format('j M'),
            ] : null,
            'invoices' => \App\Models\SessionInvoice::where('organization_id', $organization->id)->latest()->limit(24)->get()->map(fn ($i) => [
                'id'          => $i->id,
                'number'      => $i->number,
                'period'      => $i->periodLabel(),
                'sessions'    => $i->sessions_count,
                'total'       => (float) $i->total,
                'outstanding' => $i->outstanding(),
                'status'      => $i->isOverdue() ? 'overdue' : $i->status,
                'due'         => $i->due_date->format('j M Y'),
                'pdf'         => route('sessions.invoices.pdf', $i),
            ]),
            'meta'    => ['title' => 'Sessions plan | VENTIQ'],
        ]);
    }

    public function showPayment(Request $request)
    {
        $organization = Auth::user()->organization;
        $type = (string) $request->query('type');
        $purchase = SessionPackageDefinition::purchase($type, $request->query('tier'), $request->query('quantity') !== null ? (int) $request->query('quantity') : null);
        abort_unless($purchase, 404);
        $reference = BankDetails::reference($organization->id, $purchase['code']);

        return Inertia::render('Sessions/PlanPayment', [
            'purchase' => [
                'type'     => $type,
                'tier'     => $purchase['meta']['tier'] ?? null,
                'quantity' => $purchase['meta']['quantity'] ?? null,
                'amount'   => $purchase['amount'],
                'label'    => $purchase['label'],
            ],
            // Mobile money while it's on; a bank transfer any time.
            'methods' => PaymentGatewayFactory::enabledMethods(),
            'bank'    => BankDetails::configured() ? [
                'lines'     => BankDetails::lines($reference),
                'reference' => $reference,
                // Already said it was paid: waiting for VENTIQ to see it.
                'claimed'   => PaymentSession::where('organization_id', $organization->id)->where('gateway', BankTransfers::GATEWAY)->where('payable_type', 'session_package')
                    ->where('client_reference', $reference)->where('status', 'pending')->exists(),
            ] : null,
            'support' => config('ventiq.emails.support'),
            'meta'    => ['title' => 'Payment | VENTIQ Sessions'],
        ])->withViewData(['noindex' => true]);
    }

    /** "We've paid by bank transfer": VENTIQ checks the account and confirms it. */
    public function claimBankTransfer(Request $request, BankTransfers $transfers)
    {
        $data = $request->validate([
            'type'     => 'required|in:plan,payg',
            'tier'     => 'nullable|string',
            'quantity' => 'nullable|integer|min:1|max:500',
            'paid_on'  => 'required|date|before_or_equal:today|after:-60 days',
            'note'     => 'nullable|string|max:500',
        ]);
        $purchase = SessionPackageDefinition::purchase($data['type'], $data['tier'] ?? null, $data['quantity'] ?? null);
        abort_unless($purchase && BankDetails::configured(), 404);

        $transfers->claim(Auth::user()->organization, Auth::user(), $purchase, $data['paid_on'], trim($data['note'] ?? '') ?: null);

        return redirect()->route('sessions.plan')->with('status', "Thanks. We'll check our account for your transfer and add {$purchase['label']} as soon as we see it, usually within a working day.");
    }
}
