<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Org users' home is the organizer area, not the Filament dashboard. Any
 * route that still lands them on /admin (Filament's own login, old
 * bookmarks) is sent on. Only the dashboard: the Filament screens the
 * organizer area still links to (create or edit an event) keep working.
 * Super admins and sales agents have no organization and stay.
 */
class RedirectOrganizersFromAdminDashboard
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->organization_id && $request->routeIs('filament.admin.pages.dashboard')) {
            return redirect()->route('organizer.home');
        }

        return $next($request);
    }
}
