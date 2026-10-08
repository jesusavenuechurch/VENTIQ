<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;

/**
 * Google sign-up creates the organization from the person's own name and
 * no phone number, so the first stop is a short setup page: the real
 * organization name and a phone number (organizer WhatsApp notices go
 * there). A super admin acting for an organization is never stopped.
 */
class EnsureOrganizationSetUp
{
    public function handle(Request $request, Closure $next)
    {
        $organization = $request->attributes->get('organization');

        if ($organization && blank($organization->phone) && !CurrentOrganization::isActingAs()
            && !$request->routeIs('organizer.setup', 'organizer.setup.store')) {
            return redirect()->route('organizer.setup');
        }

        return $next($request);
    }
}
