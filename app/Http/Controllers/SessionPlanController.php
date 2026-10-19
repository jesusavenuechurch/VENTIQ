<?php

namespace App\Http\Controllers;

use App\Support\SessionPackageDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionPlanController extends Controller
{
    public function showPayment(Request $request)
    {
        $organization = Auth::user()->organization;
        abort_unless($organization, 403);

        $type = $request->query('type');
        abort_unless(in_array($type, ['plan', 'payg']), 404);

        if ($type === 'plan') {
            $tier = $request->query('tier');
            $def  = SessionPackageDefinition::get($tier);
            abort_unless($def && $tier !== 'free', 404);

            $amount = $def['price'];
            $label  = "{$def['label']} Plan — M{$def['price']}/month";
        } else {
            $quantity = (int) $request->query('quantity');
            abort_unless($quantity > 0, 404);

            $amount = SessionPackageDefinition::paygBundlePrice($quantity);
            $label  = "{$quantity} PAYG " . ($quantity === 1 ? 'Session' : 'Sessions');
            $tier   = null;
        }

        return view('organization.session-plan-payment', [
            'organization'  => $organization,
            'type'          => $type,
            'tier'          => $tier ?? null,
            'quantity'      => $quantity ?? null,
            'amount'        => $amount,
            'label'         => $label,
            // This flow has no manual/cash fallback (it's Ventiq's own
            // billing, not the org's) — while the shared gateway is off,
            // show a "contact us" message instead of a broken form.
            'onlineEnabled' => config('gateways.paylesotho.enabled'),
        ]);
    }
}
