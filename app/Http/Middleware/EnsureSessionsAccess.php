<?php

namespace App\Http\Middleware;

use App\Models\Session;
use App\Services\SessionQuotaService;
use App\Support\IntentRedirect;
use Closure;
use Illuminate\Http\Request;

/**
 * Sessions and Programmes belong to an organization. Super admins and
 * agents have none, so they go to their own home. For everyone else the
 * area's tabs get what they show: the organization, reports waiting for
 * review and this month's sessions.
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

        \Inertia\Inertia::share('sessionsArea', fn () => [
            'organization' => $organization->name,
            'needsReview'  => Session::forOrganization($organization->id)->where('status', 'reported')->whereNull('reviewed_at')->count(),
            'quota'        => [
                'used'      => $this->quota->currentPlan($organization)->sessions_used,
                'included'  => $this->quota->currentPlan($organization)->sessions_included,
                'remaining' => $this->quota->remainingSessions($organization),
            ],
        ]);

        return $next($request);
    }
}
