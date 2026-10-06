<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Notes when a signed-in person last used VENTIQ (at most once an hour),
 * so an account in use is never counted as unused. See accounts:remove-unused.
 */
class TrackLastActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && (!$user->last_active_at || $user->last_active_at->lt(now()->subHour()))) {
            $user->forceFill(['last_active_at' => now(), 'removal_warned_at' => null])->saveQuietly();
            if ($user->organization_id) {
                \App\Models\Organization::whereKey($user->organization_id)->whereNotNull('removal_warned_at')->update(['removal_warned_at' => null]);
            }
        }

        return $next($request);
    }
}
