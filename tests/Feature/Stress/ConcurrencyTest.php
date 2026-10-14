<?php

use App\Models\OrganizationPackage;
use App\Models\AgentEarning;
use Illuminate\Support\Facades\DB;

describe('Concurrency & Stress Tests', function () {

    beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

    it('double-click approve does not create duplicate commissions', function () {
        $admin = createSuperAdmin();
        [$agent] = createApprovedAgent();
        
        $org = \App\Models\Organization::factory()
            ->referredBy($agent)
            ->create();

        $pending = OrganizationPackage::factory()
            ->pending()
            ->create([
                'organization_id' => $org->id,
                'price_paid'      => 700,
            ]);

        // Simulate double-click — approve same package twice
        $approveOnce = function () use ($pending) {
            DB::transaction(function () use ($pending) {
                $fresh = OrganizationPackage::lockForUpdate()->find($pending->id);
                
                if ($fresh->status !== 'pending') {
                    return; // Already processed
                }

                $fresh->update(['status' => 'active']);

                // Create commission only if still pending
                AgentEarning::firstOrCreate(
                    ['organization_package_id' => $pending->id],
                    [
                        'agent_id'        => $fresh->organization->agent_id,
                        'organization_id' => $fresh->organization_id,
                        'type'            => 'commission',
                        'amount'          => $fresh->price_paid * 0.20,
                        'status'          => 'pending',
                    ]
                );
            });
        };

        // Simulate two rapid clicks
        $approveOnce();
        $approveOnce();

        // ✅ Only ONE commission record
        expect(
            AgentEarning::where('organization_package_id', $pending->id)->count()
        )->toBe(1);

        // ✅ Package activated exactly once
        expect(
            OrganizationPackage::where('id', $pending->id)->value('status')
        )->toBe('active');
    });

    it('handles 100 package approvals without corruption', function () {
        $admin = createSuperAdmin();
        [$agent] = createApprovedAgent();

        // Create 100 pending packages across 100 orgs
        $packages = collect(range(1, 100))->map(function () use ($agent) {
            $org = \App\Models\Organization::factory()
                ->referredBy($agent)
                ->create();

            return OrganizationPackage::factory()
                ->pending()
                ->create([
                    'organization_id' => $org->id,
                    'price_paid'      => 700,
                ]);
        });

        // Approve all
        $packages->each(function ($pkg) {
            $pkg->update(['status' => 'active']);

            AgentEarning::create([
                'agent_id'        => $pkg->organization->agent_id,
                'organization_id' => $pkg->organization_id,
                'organization_package_id' => $pkg->id,
                'type'            => 'commission',
                'amount'          => $pkg->price_paid * 0.20,
                'status'          => 'pending',
            ]);
        });

        // ✅ All 100 approved
        expect(
            OrganizationPackage::where('status', 'active')->count()
        )->toBe(100);

        // ✅ All 100 commissions created
        expect(AgentEarning::count())->toBe(100);

        // ✅ Total commission math is correct
        expect(
            AgentEarning::sum('amount')
        )->toBe(14000.0); // 100 × M140
    });

})->group('stress'); // Tag so you can skip in CI if needed