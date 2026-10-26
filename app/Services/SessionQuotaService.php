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

        // A paid plan whose new month wasn't paid in the days' grace: Free again.
        return app(\App\Services\Sessions\PlanRenewals::class)->lapseIfOverdue($plan) ?? $plan;
    }

    protected function rolloverPeriod(SessionPackage $plan): SessionPackage
    {
        // The next month starts where the last ended (paid or not, a plan's
        // month is the same each time); months nobody looked are skipped.
        $start = $plan->period_end->copy();
        while ($start->copy()->addMonthNoOverflow()->isPast()) {
            $start->addMonthNoOverflow();
        }
        $plan->update([
            'sessions_used' => 0,
            'whatsapp_used' => 0,
            'sms_used'      => 0,
            'period_start'  => $start,
            'period_end'    => $start->copy()->addMonthNoOverflow(),
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

    /** On a corporate rate: sessions are invoiced, so no quota stops them. */
    public function isCorporate(Organization $org): bool
    {
        return (bool) app(\App\Services\Sessions\CorporateBilling::class)->agreement($org);
    }

    public function remainingSessions(Organization $org): int
    {
        if ($this->isCorporate($org)) {
            return PHP_INT_MAX;
        }

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
        if ($this->isCorporate($org)) {
            return true;
        }

        return $this->remainingSessions($org) > 0;
    }

    /**
     * Decrement one session credit — subscription allowance first, then the
     * oldest active PAYG package with room. No-ops silently if the org is
     * actually out of quota (callers should check hasSessionQuota() first).
     */
    public function consumeSession(Organization $org): void
    {
        // Corporate: priced on the session itself (CorporateBilling::charge()), invoiced monthly.
        if ($this->isCorporate($org)) {
            return;
        }

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
        // A corporate agreement includes everything the plans do.
        if ($this->isCorporate($org)) {
            return true;
        }

        $currentRank = SessionPackageDefinition::rankOf($this->currentTier($org));
        $requiredRank = SessionPackageDefinition::rankOf($minimumTier);

        return $currentRank >= $requiredRank;
    }
}
