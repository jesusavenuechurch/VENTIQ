<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class IntentRedirect
{
    /**
     * 'session' and 'host' are explicit and win. Without one (a bookmark,
     * the plain login page, an invite), an organization that uses VENTIQ
     * Sessions and has no events goes to Sessions; other org users land in
     * the organizer area (their events and payments to confirm). Super
     * admins land in VENTIQ's admin, sales agents on their own page.
     */
    public static function resolve(?string $intent): string
    {
        $user = Auth::user();

        if ($intent === 'session') {
            return route('sessions.index');
        }

        if ($user?->organization_id) {
            if ($intent !== 'host' && static::usesOnlySessions($user->organization_id)) {
                return route('sessions.index');
            }

            return route('organizer.home');
        }

        return static::home($user);
    }

    /** Where someone with no organization belongs. */
    public static function home(?\App\Models\User $user): string
    {
        return match (true) {
            (bool) $user?->isSuperAdmin() => route('ventiq.money.index'),
            (bool) $user?->isSalesAgent() => route('agent.home'),
            // An account with no organization and no role (the old /admin
            // sign-up made these): the home page, where they can start one.
            default => url('/'),
        };
    }

    private static function usesOnlySessions(int $organizationId): bool
    {
        return \App\Models\Session::where('organization_id', $organizationId)->exists()
            && !\App\Models\Event::where('organization_id', $organizationId)->exists();
    }
}
