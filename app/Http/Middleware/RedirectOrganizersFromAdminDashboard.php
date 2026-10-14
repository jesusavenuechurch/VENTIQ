<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Org users' home is the organizer area. The Filament screens it has
 * replaced (dashboard, event list/create/edit, payment accounts) send
 * them to the organizer equivalent, so old bookmarks and Filament's own
 * login keep working. Super admins and sales agents have no organization
 * and see Filament as before; Filament screens without a replacement yet
 * stay reachable for everyone.
 */
class RedirectOrganizersFromAdminDashboard
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()?->organization_id) {
            return $next($request);
        }

        $route = $request->route();

        return match (true) {
            $request->routeIs('filament.admin.pages.dashboard', 'filament.admin.events', 'filament.admin.events.resources.events.index')
                => redirect()->route('organizer.home'),
            $request->routeIs('filament.admin.events.resources.events.create')
                => redirect()->route('organizer.events.create'),
            $request->routeIs('filament.admin.events.resources.events.edit', 'filament.admin.events.resources.events.tiers')
                => redirect()->route('organizer.events.edit', $route->parameter('record')),
            $request->routeIs('filament.admin.events.resources.events.registrations')
                => redirect()->route('organizer.events.attendees', $route->parameter('record')),
            $request->routeIs('filament.admin.organization.resources.organization-payment-methods.*')
                => redirect()->route('organizer.accounts.index'),
            default => $next($request),
        };
    }
}
