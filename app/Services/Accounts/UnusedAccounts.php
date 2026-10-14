<?php

namespace App\Services\Accounts;

use App\Models\{Organization, User};
use Illuminate\Support\Facades\{DB, Storage};

/**
 * Accounts that were opened and never used: nothing created on VENTIQ at
 * all, and nobody has used them for two months. They're warned a week
 * ahead, then removed. Anything created (an event, a session, a client, a
 * payment account, a paid package...) keeps the account for good.
 */
class UnusedAccounts
{
    public const WARN_AFTER_DAYS = 53;     // the warning goes a week before
    public const REMOVE_AFTER_DAYS = 60;

    /** Tables where a row means the organization created something. */
    private const CREATED = [
        'events', 'clients', 'capture_sessions', 'participants', 'organizational_records', 'certificates',
        'tier_templates', 'settlements', 'ticket_fees', 'payment_sessions', 'agent_commissions', 'agent_earnings',
    ];

    public function organizationIsUntouched(Organization $organization): bool
    {
        if ($organization->keep_account) {
            return false;
        }
        foreach (self::CREATED as $table) {
            if (DB::table($table)->where('organization_id', $organization->id)->exists()) {
                return false;
            }
        }

        // Every organization starts with free packages; only paid ones count.
        return !DB::table('organization_payment_methods')->where('organization_id', $organization->id)->where('payment_method', '!=', 'online')->exists()
            && !DB::table('organization_packages')->where('organization_id', $organization->id)->where('price_paid', '>', 0)->exists()
            && !DB::table('session_packages')->where('organization_id', $organization->id)->where('price_paid', '>', 0)->exists()
            && !$organization->members()->get()->contains(fn (User $u) => !$this->userIsPlain($u));
    }

    /** When anyone last used the organization (or its sign-up, if later). */
    public function organizationLastActive(Organization $organization)
    {
        return collect([$organization->created_at, ...$organization->members()->pluck('last_active_at')->filter()])
            ->filter()->max();
    }

    /** A user who signed up and never set up an organization. */
    public function userIsUntouched(User $user): bool
    {
        return !$user->organization_id && $this->userIsPlain($user);
    }

    public function userLastActive(User $user)
    {
        return collect([$user->created_at, $user->last_active_at])->filter()->max();
    }

    public function removeOrganization(Organization $organization): void
    {
        DB::transaction(function () use ($organization) {
            $users = $organization->members()->get();
            DB::table('assist_conversations')->where('organization_id', $organization->id)
                ->orWhereIn('user_id', $users->pluck('id'))->delete();
            $users->each->delete();

            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $organization->delete();   // packages, invites and the rest go with it
        });
    }

    public function removeUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            DB::table('assist_conversations')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }

    /** Not a super admin or sales agent: someone we'd remove with their account. */
    private function userIsPlain(User $user): bool
    {
        return !$user->hasAnyRole(['super_admin', 'sales_agent']);
    }
}
