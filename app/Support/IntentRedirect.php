<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class IntentRedirect
{
    /**
     * 'session' and 'host' are explicit and win. Without one (a bookmark,
     * the plain login page, an invite), an organization that uses VENTIQ
     * Sessions and has no events goes to Sessions; other org users land in
     * the organizer area (their events and payments to confirm). Users with
     * no organization — super admins and sales agents — keep landing on the
     * Filament dashboard.
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

        return route('filament.admin.pages.dashboard');
    }

    private static function usesOnlySessions(int $organizationId): bool
    {
        return \App\Models\Session::where('organization_id', $organizationId)->exists()
            && !\App\Models\Event::where('organization_id', $organizationId)->exists();
    }
}
