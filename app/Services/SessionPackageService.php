<?php

namespace App\Services;

use App\Models\SessionPackage;

/**
 * The single place a SessionPackage row actually gets created — called from
 * both the superadmin manual-entry Filament actions and the org self-serve
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
        SessionPackage::where('organization_id', $organizationId)
            ->where('tier', '!=', 'payg')
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        return SessionPackage::create([
            'organization_id'   => $organizationId,
            'tier'              => $tier,
            'status'            => 'active',
            'sessions_included' => $sessionsIncluded,
            'whatsapp_included' => $whatsappIncluded,
            'sms_included'      => $smsIncluded,
            'price_paid'        => $pricePaid,
            'period_start'      => now(),
            'period_end'        => now()->addMonth(),
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
