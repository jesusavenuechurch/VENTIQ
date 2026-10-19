<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\{Organization, SessionPackage};
use App\Services\SessionPackageService;
use App\Support\Admin\AdminTable;
use App\Support\SessionPackageDefinition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Organizations' Sessions plans: put one on a plan, or add pay-as-you-go sessions, for a deal agreed outside the app. */
class SessionPlansController extends Controller
{
    public function index(Request $request)
    {
        $table = AdminTable::for(SessionPackage::query()->with('organization'), $request)
            ->search(fn ($q, $term) => $q->whereHas('organization', fn ($o) => $o->where('name', 'like', AdminTable::like($term))))
            ->sorts(['created' => 'created_at', 'ends' => 'period_end'], 'created')
            ->filters([
                'tier'   => fn ($q, $v) => $q->where('tier', $v),
                'status' => fn ($q, $v) => $q->where('status', $v),
            ]);
        $tiers = SessionPackageDefinition::tiers();

        return Inertia::render('Ventiq/SessionPlans', [
            'rows'          => $table->paginate()->through(fn (SessionPackage $p) => [
                'id'           => $p->id,
                'organization' => ['id' => $p->organization_id, 'name' => $p->organization?->name],
                'tier'         => $p->tier,
                'tierLabel'    => $tiers[$p->tier]['label'] ?? ucfirst((string) $p->tier),
                'status'       => $p->status,
                'sessions'     => "{$p->sessions_used} / {$p->sessions_included}",
                'whatsapp'     => "{$p->whatsapp_used} / {$p->whatsapp_included}",
                'ends'         => $p->period_end?->format('j M Y'),
                'price'        => (float) $p->price_paid,
                'created'      => $p->created_at?->format('j M Y'),
            ]),
            'state'         => $table->state(),
            'tiers'         => collect($tiers)->map(fn ($t, $key) => [
                'value' => $key, 'label' => $t['label'], 'description' => $t['description'], 'price' => $t['price'],
                'sessions' => $t['sessions_included'], 'whatsapp' => $t['whatsapp_included'], 'sms' => $t['sms_included'],
            ])->values(),
            'paygPresets'   => collect(SessionPackageDefinition::paygPresets())->map(fn ($price, $qty) => ['quantity' => $qty, 'price' => $price])->values(),
            'organizations' => Organization::orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['value' => $o->id, 'label' => $o->name]),
            'meta'          => ['title' => 'Session plans | VENTIQ'],
        ]);
    }

    public function changePlan(Request $request, SessionPackageService $plans)
    {
        $data = $request->validate([
            'organization_id'   => ['required', 'integer', Rule::exists('organizations', 'id')],
            'tier'              => ['required', Rule::in(array_keys(SessionPackageDefinition::tiers()))],
            'sessions_included' => ['required', 'integer', 'min:0'],
            'whatsapp_included' => ['required', 'integer', 'min:0'],
            'sms_included'      => ['required', 'integer', 'min:0'],
            'price_paid'        => ['required', 'numeric', 'min:0'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);
        $plans->changePlan(
            organizationId: (int) $data['organization_id'],
            tier: $data['tier'],
            sessionsIncluded: (int) $data['sessions_included'],
            whatsappIncluded: (int) $data['whatsapp_included'],
            smsIncluded: (int) $data['sms_included'],
            pricePaid: (float) $data['price_paid'],
            notes: $data['notes'] ?? null,
        );

        return back()->with('status', Organization::find($data['organization_id'])->name . ' is on the ' . SessionPackageDefinition::get($data['tier'])['label'] . ' plan.');
    }

    public function addPayg(Request $request, SessionPackageService $plans)
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'quantity'        => ['required', 'integer', 'min:1', 'max:1000'],
            'price_paid'      => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);
        $quantity = (int) $data['quantity'];
        $plans->addPaygCredits(
            organizationId: (int) $data['organization_id'],
            quantity: $quantity,
            pricePaid: (float) ($data['price_paid'] ?? SessionPackageDefinition::paygBundlePrice($quantity)),
            notes: $data['notes'] ?? null,
        );

        return back()->with('status', "{$quantity} pay-as-you-go " . ($quantity === 1 ? 'session' : 'sessions') . ' added for ' . Organization::find($data['organization_id'])->name . '.');
    }

    public function destroy(SessionPackage $sessionPlan)
    {
        $sessionPlan->delete();

        return back()->with('status', 'That plan has been removed.');
    }
}
