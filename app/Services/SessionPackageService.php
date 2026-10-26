<?php

namespace App\Services;

use App\Models\SessionPackage;

/**
 * The single place a SessionPackage row actually gets created — called from
 * both Admin › Session plans (super admins) and the org self-serve
 * PayLesotho payment callback, so the two paths can never drift apart.
 */
class SessionPackageService
{
    public function changePlan(
        int $organizationId,
        string $tier,
        int $sessionsIncluded,
        int $whatsappIncluded,
        int $smsIncluded,
        float $pricePaid,
        ?string $notes = null,
    ): SessionPackage {
        // The same plan paid again: another month on it, from where it's paid to.
        $current = SessionPackage::where('organization_id', $organizationId)->where('tier', $tier)->where('status', 'active')
            ->whereNotNull('paid_until')->latest('period_start')->first();
        if ($current && $pricePaid > 0 && $current->renews()) {
            $current->update([
                'paid_until' => $current->paid_until->copy()->addMonthNoOverflow(),
                'price_paid' => (float) $current->price_paid + $pricePaid,
                'notes'      => trim(($current->notes ? $current->notes . "\n" : '') . ($notes ?? 'Renewed ' . now()->format('j M Y'))),
            ]);

            return $current;
        }

        SessionPackage::where('organization_id', $organizationId)
            ->where('tier', '!=', 'payg')
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $end = now()->addMonthNoOverflow();

        return SessionPackage::create([
            'organization_id'   => $organizationId,
            'tier'              => $tier,
            'status'            => 'active',
            'sessions_included' => $sessionsIncluded,
            'whatsapp_included' => $whatsappIncluded,
            'sms_included'      => $smsIncluded,
            'price_paid'        => $pricePaid,
            'period_start'      => now(),
            'period_end'        => $end,
            // Bought: paid to the end of this month, and renewed by paying again.
            'paid_until'        => $pricePaid > 0 && in_array($tier, SessionPackage::RENEWING, true) ? $end : null,
            'notes'             => $notes,
        ]);
    }

    public function addPaygCredits(
        int $organizationId,
        int $quantity,
        float $pricePaid,
        ?string $notes = null,
    ): SessionPackage {
        return SessionPackage::create([
            'organization_id'   => $organizationId,
            'tier'              => 'payg',
            'status'            => 'active',
            'sessions_included' => $quantity,
            'price_paid'        => $pricePaid,
            'period_start'      => null,
            'period_end'        => null,
            'notes'             => $notes,
        ]);
    }
}
