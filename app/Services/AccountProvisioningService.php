<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Organization;
use App\Models\OrganizationPackage;
use App\Models\SessionPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// The one place a VENTIQ Organization + its primary User get created —
// whether that's the manual registration form, an agent referral link,
// or Google Sign-In. All three should always produce the same shape of
// account, so this exists to make that a guarantee, not a convention.
class AccountProvisioningService
{
    public function provision(array $data, ?Agent $agent = null): User
    {
        return DB::transaction(function () use ($data, $agent) {
            $organization = Organization::create([
                'name'          => $data['org_name'],
                'phone'         => $data['org_phone'] ?? null,
                'org_district'  => $data['org_district'] ?? null,
                'tagline'       => $data['tagline'] ?? null,
                'description'   => $data['description'] ?? null,
                'email'         => $data['email'] ?? $data['user_email'],
                'contact_email' => $data['contact_email'] ?? $data['user_email'],
                'is_active'     => true,
                'agent_id'      => $agent?->id,
                'registered_via_agent_at'          => $agent ? now() : null,
                'registration_source'              => $agent ? 'agent' : ($data['source'] ?? 'direct'),
                'agent_commission_packages_count'  => 0,
                'agent_commission_packages_limit'  => 3,
            ]);

            $user = User::create([
                'name'              => $data['user_name'],
                'email'             => $data['user_email'],
                // Google-provisioned accounts have no password of their
                // own — a random unusable hash keeps the column non-null
                // without ever being a real login path.
                'password'          => Hash::make($data['user_password'] ?? Str::random(40)),
                'google_id'         => $data['google_id'] ?? null,
                'organization_id'   => $organization->id,
                'email_verified_at' => $data['email_verified_at'] ?? null,
            ]);

            $user->assignRole('org_admin');
            OrganizationPackage::createFreeTrialPackage($organization->id);
            SessionPackage::createFreePackage($organization->id);

            return $user;
        });
    }
}