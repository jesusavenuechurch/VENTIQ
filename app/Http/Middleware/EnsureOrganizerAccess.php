<?php

namespace App\Http\Middleware;

use App\Models\TicketPayment;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;

/**
 * The organizer area needs an organization to show. A super admin who
 * hasn't picked one goes to the organizations list to choose; anyone else without
 * an organization has no business here.
 */
class EnsureOrganizerAccess
{
    public function handle(Request $request, Closure $next)
    {
        $organization = CurrentOrganization::get();

        if (!$organization) {
            if ($request->user()?->isSuperAdmin()) {
                return redirect()->route('ventiq.organizations.index');
            }

            abort(403);
        }

        $request->attributes->set('organization', $organization);
        view()->share('currentOrganization', $organization);
        view()->share('actingAsOrganization', CurrentOrganization::isActingAs());
        // For the badge on the "To confirm" tab.
        view()->share('paymentsToConfirm', fn () => TicketPayment::awaitingDecisionFor($organization)->count());

        // The same for the React pages (resources/js/Layouts/OrganizerLayout.tsx).
        $user = $request->user();
        \Inertia\Inertia::share('organizer', fn () => [
            'organization'      => ['id' => $organization->id, 'name' => $organization->name, 'slug' => $organization->slug],
            'actingAs'          => CurrentOrganization::isActingAs(),
            'paymentsToConfirm' => TicketPayment::awaitingDecisionFor($organization)->count(),
            // Where settings send you back to when it isn't known where you came from.
            'usesEvents'        => \App\Support\IntentRedirect::usesEvents($organization->id),
            // What this person may do here, for showing only the buttons that work.
            'can' => collect([
                'approve_payment', 'create_event', 'edit_event', 'delete_event', 'edit_organization',
                'view_payment_method', 'create_payment_method', 'edit_payment_method', 'delete_payment_method',
                'manage_staff', 'view_reports',
            ])->mapWithKeys(fn ($p) => [$p => (bool) $user?->can($p)])->all(),
        ]);

        return $next($request);
    }
}
