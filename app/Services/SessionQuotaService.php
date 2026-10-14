<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\SessionPackage;
use App\Support\SessionPackageDefinition;

/**
 * The only place Sessions code should ask about pricing/quota. It never
 * references OrganizationPackage or anything ticketing-shaped — Sessions
 * has its own SessionPackage model, entirely separate from the ticketed-
 * events product.
 */
class SessionQuotaService
{
    /**
     * The org's active subscription-tier row (free/team/business/enterprise
     * — never a 'payg' row). Self-heals for orgs with no row yet, and
     * lazily rolls the period over once it's expired.
     */
    public function currentPlan(Organization $org): SessionPackage
    {
        $plan = SessionPackage::where('organization_id', $org->id)
            ->where('tier', '!=', 'payg')
            ->where('status', 'active')
            ->latest('period_start')
            ->first();

        if (!$plan) {
            $plan = SessionPackage::createFreePackage($org->id);
        }

        if ($plan->period_end && $plan->period_end->isPast()) {
            $plan = $this->rolloverPeriod($plan);
        }

        return $plan;
    }

    protected function rolloverPeriod(SessionPackage $plan): SessionPackage
    {
        $plan->update([
            'sessions_used' => 0,
            'whatsapp_used' => 0,
            'sms_used'      => 0,
            'period_start'  => now(),
            'period_end'    => now()->addMonth(),
        ]);

        return $plan->fresh();
    }

    protected function activePaygPackages(Organization $org)
    {
        return SessionPackage::where('organization_id', $org->id)
            ->where('tier', 'payg')
            ->where('status', 'active')
            ->oldest('created_at')
            ->get();
    }

    public function remainingSessions(Organization $org): int
    {
        $plan = $this->currentPlan($org);
        $payg = $this->activePaygPackages($org)->sum('remaining_sessions');

        return $plan->remaining_sessions + $payg;
    }

    public function includedSessions(Organization $org): int
    {
        return $this->currentPlan($org)->sessions_included;
    }

    public function currentTier(Organization $org): string
    {
        return $this->currentPlan($org)->tier;
    }

    public function hasSessionQuota(Organization $org): bool
    {
        return $this->remainingSessions($org) > 0;
    }

    /**
     * Decrement one session credit — subscription allowance first, then the
     * oldest active PAYG package with room. No-ops silently if the org is
     * actually out of quota (callers should check hasSessionQuota() first).
     */
    public function consumeSession(Organization $org): void
    {
        $plan = $this->currentPlan($org);

        if ($plan->hasSessionsRemaining()) {
            $plan->increment('sessions_used');
            return;
        }

        foreach ($this->activePaygPackages($org) as $payg) {
            if ($payg->hasSessionsRemaining()) {
                $payg->increment('sessions_used');

                if (!$payg->fresh()->hasSessionsRemaining()) {
                    $payg->update(['status' => 'exhausted']);
                }

                return;
            }
        }
    }

    public function remainingWhatsapp(Organization $org): int
    {
        return $this->currentPlan($org)->remaining_whatsapp;
    }

    public function hasWhatsappQuota(Organization $org): bool
    {
        return $this->remainingWhatsapp($org) > 0;
    }

    public function consumeWhatsapp(Organization $org): void
    {
        $plan = $this->currentPlan($org);

        if ($plan->hasWhatsappRemaining()) {
            $plan->increment('whatsapp_used');
        }
    }

    /**
     * Compares the org's current subscription tier against a minimum tier,
     * by rank. PAYG credit never counts as "the plan" for this check.
     */
    public function meetsMinimumTier(Organization $org, string $minimumTier): bool
    {
        $currentRank = SessionPackageDefinition::rankOf($this->currentTier($org));
        $requiredRank = SessionPackageDefinition::rankOf($minimumTier);

        return $currentRank >= $requiredRank;
    }
}
