<?php

namespace App;

/**
 * DEPRECATED — package/quota system removed in favor of the flat
 * 4.9% + M7.50/ticket model (see TicketApprovalService).
 *
 * Kept as permissive no-ops so existing call sites (TicketResource
 * bulk import, comp ticket issuance, EventResource) don't fatal while
 * they're being migrated off package checks one at a time. Every
 * method here should be unused within one cleanup pass — grep for
 * ->canUseFeature(, ->hasEventsRemaining(, ->availablePackages(,
 * ->packageSummary(, ->activePackage( and delete the call sites,
 * then delete this trait, PackagePurchaseResource, and the
 * organization_packages table.
 */
trait HasPackageEntitlements
{
    public function availablePackages()
    {
        return collect(); // no packages left to fund events from
    }

    public function activePackage(): null
    {
        return null;
    }

    // Every feature is unlocked for every org now — this is the actual
    // policy, not a placeholder. Bulk import, tiers, installments,
    // private events are all just "available", full stop.
    public function canUseFeature(string $feature): bool
    {
        return true;
    }

    public function canUseFeatureForEvent(string $feature, \App\Models\Event $event): bool
    {
        return true;
    }

    public function hasAnyActivePackage(): bool
    {
        return true; // orgs no longer need a package to operate
    }

    public function isOnFreeTrial(): bool
    {
        return false;
    }

    public function packageSummary(): string
    {
        return 'Pay-as-you-go — no package required';
    }
}