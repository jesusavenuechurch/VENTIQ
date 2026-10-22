<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Agent;
use App\Models\OrganizationPackage;

uses(TestCase::class)->in('Feature', 'Unit');

// ── Create super admin ──
function createSuperAdmin(): User
{
    $user = User::factory()->create(['organization_id' => null]);
    $user->assignRole('super_admin');
    return $user;
}

// ── Create org + org_admin user ──
function createOrgWithAdmin(): array
{
    $org  = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $user->assignRole('org_admin');
    return [$org, $user];
}

// ── Create org + free trial already active ──
function createOrgWithFreeTrial(): array
{
    [$org, $user] = createOrgWithAdmin();
    OrganizationPackage::createFreeTrialPackage($org->id);
    $org->refresh(); // Reload relationships
    return [$org, $user];
}

// ── Create approved agent + user ──
function createApprovedAgent(): array
{
    $agent = Agent::factory()->approved()->create();

    // ⚠️ User model doesn't have agent_id in fillable
    // Check your users migration — if agent_id column exists, add to fillable
    $user = User::factory()->create([
        'email'           => $agent->email,
        'organization_id' => null,
    ]);
    $user->assignRole('sales_agent');

    return [$agent, $user];
}

// ── Create org referred by an agent ──
function createReferredOrg(Agent $agent): array
{
    $org  = Organization::factory()->referredBy($agent)->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $user->assignRole('org_admin');
    return [$org, $user];
}
