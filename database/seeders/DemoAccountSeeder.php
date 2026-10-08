<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['email' => 'demo@ventiq.co.ls'],
            [
                'name'        => 'VENTIQ Demo Org',
                'phone'       => '+266 5800 0000',
                'description' => 'Demo organization for internal testing.',
                'is_active'   => true,
            ]
        );

        $user = User::updateOrCreate(
            ['email' => 'demo@ventiq.co.ls'],
            [
                'name'              => 'Demo User',
                'password'          => Hash::make('Demo@1234'),
                'organization_id'   => $organization->id,
                'email_verified_at' => now(), // skips the verify-email gate entirely
            ]
        );

        // Without this the account has zero Spatie permissions — Filament
        // resources gated on them (Events, Tickets, etc.) just vanish from
        // nav with no visible error, which is exactly what happened here.
        if (!$user->hasRole('org_admin')) {
            $user->assignRole('org_admin');
        }

        $this->command->info("Demo account ready — login with demo@ventiq.co.ls / Demo@1234 (org_id: {$organization->id}, user_id: {$user->id})");

        // Feed straight into the session seeder so everything lands on
        // this exact org/user — no guessing, no Organization::first().
        (new SessionTestSeeder)->seedForOrganization($organization, $user);
    }
}