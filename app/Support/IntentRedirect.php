<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class IntentRedirect
{
    /**
     * 'session' is always explicit and always wins. Otherwise ("host", or no
     * intent at all) the default now depends on who's logging in: org users
     * land on the Sessions desk — the Filament dashboard has nothing for them
     * day-to-day, and Sessions itself already bounces anyone with no
     * organization_id (super admins) straight back to /admin. Super admins
     * keep landing on the Filament dashboard, same as before.
     */
    public static function resolve(?string $intent): string
    {
        if ($intent === 'session') {
            return route('sessions.index');
        }

        $user = Auth::user();

        if ($user && !$user->isSuperAdmin()) {
            return route('sessions.index');
        }

        return route('filament.admin.pages.dashboard');
    }
}