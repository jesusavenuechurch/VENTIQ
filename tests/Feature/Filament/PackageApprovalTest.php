<?php

use App\Models\OrganizationPackage;
use App\Models\AgentEarning;
use App\Filament\Resources\PackagePurchaseResource;
use function Pest\Livewire\livewire;

describe('Package Approval — Chain Reaction', function () {

    beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

    it('approving a package activates it and expires the old one', function () {
        $admin        = createSuperAdmin();
        [$org, $user] = createOrgWithFreeTrial(); // Has active free trial

        $pending = OrganizationPackage::factory()
            ->pending()
            ->create(['organization_id' => $org->id]);

        livewire(PackagePurchaseResource\Pages\ListPackagePurchases::class)
            ->actingAs($admin)
            ->callTableAction('approve_package', $pending)
            ->assertHasNoTableActionErrors();

        // ✅ New package is active
        expect($pending->fresh()->status)->toBe('active');

        // ✅ Old free trial is expired
        expect(
            OrganizationPackage::where('organization_id', $org->id)
                ->where('is_free_trial', true)
                ->value('status')
        )->toBe('expired');

        // ✅ Only ONE active package
        expect(
            OrganizationPackage::where('organization_id', $org->id)
                ->where('status', 'active')
                ->count()
        )->toBe(1);
    });

    it('approving creates commission for referred org', function () {
        $admin          = createSuperAdmin();
        [$agent, $agentUser] = createApprovedAgent();
        
        $org = \App\Models\Organization::factory()
            ->referredBy($agent)
            ->create();

        $pending = OrganizationPackage::factory()
            ->pending()
            ->create([
                'organization_id' => $org->id,
                'price_paid'      => 700,
            ]);

        livewire(PackagePurchaseResource\Pages\ListPackagePurchases::class)
            ->actingAs($admin)
            ->callTableAction('approve_package', $pending)
            ->assertHasNoTableActionErrors();

        // ✅ Commission record created
        $earning = AgentEarning::where('agent_id', $agent->id)->first();
        expect($earning)->not->toBeNull()
            ->and($earning->amount)->toBe(140.0)
            ->and($earning->type)->toBe('commission')
            ->and($earning->status)->toBe('pending');
    });

    it('approving does NOT create commission for direct registration', function () {
        $admin        = createSuperAdmin();
        [$org, $user] = createOrgWithAdmin();

        $pending = OrganizationPackage::factory()
            ->pending()
            ->create(['organization_id' => $org->id]);

        livewire(PackagePurchaseResource\Pages\ListPackagePurchases::class)
            ->actingAs($admin)
            ->callTableAction('approve_package', $pending);

        expect(AgentEarning::count())->toBe(0);
    });

    it('approving does NOT create commission after 3 packages', function () {
        $admin = createSuperAdmin();
        [$agent] = createApprovedAgent();

        $org = \App\Models\Organization::factory()
            ->referredBy($agent)
            ->create(['agent_commission_packages_count' => 3]); // Already at limit

        $pending = OrganizationPackage::factory()
            ->pending()
            ->create(['organization_id' => $org->id]);

        livewire(PackagePurchaseResource\Pages\ListPackagePurchases::class)
            ->actingAs($admin)
            ->callTableAction('approve_package', $pending);

        expect(AgentEarning::count())->toBe(0);
    });

});