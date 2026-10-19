<?php

namespace App\Http\Middleware;

use App\Models\TicketPayment;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;

/**
 * The organizer area needs an organization to show. A super admin who
 * hasn't picked one goes back to Filament to choose; anyone else without
 * an organization has no business here.
 */
class EnsureOrganizerAccess
{
    public function handle(Request $request, Closure $next)
    {
        $organization = CurrentOrganization::get();

        if (!$organization) {
            if ($request->user()?->isSuperAdmin()) {
                return redirect()->route('filament.admin.organization.resources.organizations.index');
            }

            abort(403);
        }

        $request->attributes->set('organization', $organization);
        view()->share('currentOrganization', $organization);
        view()->share('actingAsOrganization', CurrentOrganization::isActingAs());
        // For the badge on the "To confirm" tab.
        view()->share('paymentsToConfirm', fn () => TicketPayment::awaitingDecisionFor($organization)->count());

        return $next($request);
    }
}
