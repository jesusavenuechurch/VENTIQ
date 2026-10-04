<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class IntentRedirect
{
    /**
     * 'session' is always explicit and always wins. Otherwise ("host", or no
     * intent at all) org users land in the organizer area (their events and
     * payments to confirm). Users with no organization — super admins and
     * sales agents — keep landing on the Filament dashboard.
     */
    public static function resolve(?string $intent): string
    {
        $user = Auth::user();

        if ($intent === 'session') {
            return route('sessions.index');
        }

        if ($user?->organization_id) {
            return route('organizer.home');
        }

        return route('filament.admin.pages.dashboard');
    }
}