<?php

namespace App\Http\Middleware;

use App\Models\Session;
use App\Services\SessionQuotaService;
use App\Support\IntentRedirect;
use App\Support\SessionsAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * Sessions and Programmes belong to an organization. Super admins and
 * agents have none, so they go to their own home. Everyone else may do
 * what their role allows (SessionsAccess), and the area's tabs get what
 * they show: the organization, reports waiting for review, this month's
 * sessions and what this person can do.
 */
class EnsureSessionsAccess
{
    public function __construct(private SessionQuotaService $quota) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $organization = $user?->organization;

        if (!$organization) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect(IntentRedirect::home($user));
        }

        // What their role lets them do here (App\Support\SessionsAccess).
        $ability = SessionsAccess::abilityFor($request->route()?->getName());
        if (!SessionsAccess::can($user, $ability)) {
            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect()->route('sessions.index')->with('error', "Your role on the team doesn't include that. Ask an Admin if you need it.");
            }
            abort(403);
        }

        \Inertia\Inertia::share('sessionsArea', fn () => [
            'can'          => SessionsAccess::all($user),
            'canSign'      => (bool) $user->can_sign_certificates,
            'organization' => $organization->name,
            'needsReview'  => Session::forOrganization($organization->id)->where('status', 'reported')->whereNull('reviewed_at')->count(),
            'overdueActions' => \App\Models\ActionPoint::where('organization_id', $organization->id)->where('status', 'open')->whereDate('due_date', '<', today())->count(),
            'quota'        => $this->quota->isCorporate($organization)
                ? ['used' => app(\App\Services\Sessions\CorporateBilling::class)->monthToDate($organization)['held'], 'included' => null, 'remaining' => 9999, 'corporate' => true]
                : [
                    'used'      => $this->quota->currentPlan($organization)->sessions_used,
                    'included'  => $this->quota->currentPlan($organization)->sessions_included,
                    'remaining' => $this->quota->remainingSessions($organization),
                    'corporate' => false,
                ],
        ]);

        return $next($request);
    }
}
