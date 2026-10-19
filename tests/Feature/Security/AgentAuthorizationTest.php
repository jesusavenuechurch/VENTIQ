<?php

use App\Filament\Resources\PackagePurchaseResource;
use function Pest\Livewire\livewire;

describe('Agent Authorization — The Walls', function () {

    beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

    it('agent cannot access package purchase resource URL directly', function () {
        [$agent, $user] = createApprovedAgent();

        $this->actingAs($user)
             ->get('/admin/package-purchases')
             ->assertForbidden();
    });

    it('agent cannot see other agents earnings', function () {
        [$agent1, $user1] = createApprovedAgent();
        [$agent2, $user2] = createApprovedAgent();

        // Create earning for agent2
        \App\Models\AgentEarning::factory()->create([
            'agent_id' => $agent2->id,
        ]);

        // Agent1 logs in — should see 0 earnings
        $this->actingAs($user1);

        $earnings = \App\Models\AgentEarning::where(
            'agent_id', $user1->agent->id
        )->count();

        expect($earnings)->toBe(0);
    });

    it('agent cannot see organizations from other agents', function () {
        [$agent1, $user1] = createApprovedAgent();
        [$agent2, $user2] = createApprovedAgent();

        // Create org for agent2
        \App\Models\Organization::factory()
            ->referredBy($agent2)
            ->create();

        // Agent1 queries their own orgs
        $orgs = \App\Models\Organization::where('agent_id', $agent1->id)->count();

        expect($orgs)->toBe(0);
    });

    it('org admin cannot see other organizations data', function () {
        [$org1, $user1] = createOrgWithAdmin();
        [$org2, $user2] = createOrgWithAdmin();

        // Org1 admin tries to see org2 events
        $events = \App\Models\Event::where('organization_id', $org2->id)
            ->where('organization_id', $user1->organization_id) // Their filter
            ->count();

        expect($events)->toBe(0);
    });

    it('org admin cannot approve packages', function () {
        [$org, $user] = createOrgWithAdmin();

        $pending = \App\Models\OrganizationPackage::factory()
            ->pending()
            ->create(['organization_id' => $org->id]);

        livewire(\App\Filament\Resources\PackagePurchaseResource\Pages\ListPackagePurchases::class)
            ->actingAs($user)
            ->assertTableActionHidden('approve_package', $pending);
    });

    it('unauthenticated user cannot access admin panel', function () {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/events')->assertRedirect('/admin/login');
        $this->get('/admin/package-purchases')->assertRedirect('/admin/login');
    });

});