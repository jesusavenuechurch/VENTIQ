<?php

namespace App\Http\Controllers;

use App\Models\SessionPackage;
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

        $type = $request->query('type');
        abort_unless(in_array($type, ['plan', 'payg']), 404);

        if ($type === 'plan') {
            $tier = (string) $request->query('tier');
            $def  = SessionPackageDefinition::get($tier);
            abort_unless($def && !in_array($tier, ['free', 'enterprise'], true), 404);

            $amount = $def['price'];
            $label  = "{$def['label']} plan, M{$def['price']} a month";
        } else {
            $quantity = (int) $request->query('quantity');
            abort_unless($quantity > 0 && $quantity <= 500, 404);

            $amount = SessionPackageDefinition::paygBundlePrice($quantity);
            $label  = "{$quantity} pay-as-you-go " . ($quantity === 1 ? 'session' : 'sessions');
        }

        return Inertia::render('Sessions/PlanPayment', [
            'purchase' => [
                'type'     => $type,
                'tier'     => $tier ?? null,
                'quantity' => $quantity ?? null,
                'amount'   => (float) $amount,
                'label'    => $label,
            ],
            // This is VENTIQ's own billing, with no pay-by-hand fallback:
            // while online payments are off, it says to get in touch.
            'methods' => PaymentGatewayFactory::enabledMethods(),
            'support' => config('ventiq.emails.support'),
            'meta'    => ['title' => 'Payment | VENTIQ Sessions'],
        ])->withViewData(['noindex' => true]);
    }
}
