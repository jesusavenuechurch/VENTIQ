<?php

namespace App;

use App\Models\OrganizationPackage;

trait HasPackageEntitlements
{
    /**
     * All packages belonging to this org that are available to fund new events.
     * Active, not yet fully bound (still has events remaining).
     */
    public function availablePackages()
    {
        return OrganizationPackage::where('organization_id', $this->id)
            ->whereIn('status', ['active'])
            ->where('is_free_trial', false)
            ->orWhere(function ($q) {
                $q->where('organization_id', $this->id)
                  ->where('status', 'active')
                  ->where('is_free_trial', true);
            })
            ->get()
            ->filter(fn ($p) => $p->hasEventsRemaining());
    }

    /**
     * The active paid package — most recently activated non-trial package.
     * Used for org-level feature display (e.g. what to show in the sidebar).
     */
    public function activePackage(): ?OrganizationPackage
    {
        return OrganizationPackage::where('organization_id', $this->id)
            ->where('status', 'active')
            ->where('is_free_trial', false)
            ->latest('purchased_at')
            ->first();
    }

    /**
     * Check if the org has ANY package (including trial) that supports a feature.
     * Used for org-level UI decisions — e.g. show/hide nav items.
     * NOT used for event-level access. Use $event->package->hasFeature() for that.
     */
    public function canUseFeature(string $feature): bool
    {
        return OrganizationPackage::where('organization_id', $this->id)
            ->whereIn('status', ['active'])
            ->get()
            ->some(fn ($package) => $package->hasFeature($feature));
    }

    /**
     * Check feature access scoped to a specific event's bound package.
     * This is the authoritative check for event-level operations.
     */
    public function canUseFeatureForEvent(string $feature, \App\Models\Event $event): bool
    {
        if (!$event->organization_package_id) return false;

        return $event->organizationPackage?->hasFeature($feature) ?? false;
    }

    /**
     * Whether this org has any usable package at all.
     */
    public function hasAnyActivePackage(): bool
    {
        return OrganizationPackage::where('organization_id', $this->id)
            ->whereIn('status', ['active'])
            ->exists();
    }

    /**
     * Whether the org is still on free trial only (no paid packages ever).
     */
    public function isOnFreeTrial(): bool
    {
        $hasActive = OrganizationPackage::where('organization_id', $this->id)
            ->where('status', 'active')
            ->exists();

        $hasPaid = OrganizationPackage::where('organization_id', $this->id)
            ->where('is_free_trial', false)
            ->whereIn('status', ['active', 'exhausted', 'expired'])
            ->exists();

        return $hasActive && !$hasPaid;
    }

    /**
     * Summary for display — used in Filament dashboards.
     */
    public function packageSummary(): string
    {
        $packages = $this->availablePackages();

        if ($packages->isEmpty()) return 'No active packages';

        return $packages->map(fn ($p) => "{$p->display_name} ({$p->remaining_tickets} tickets remaining)")->implode(' · ');
    }
}