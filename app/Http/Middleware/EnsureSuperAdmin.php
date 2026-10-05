<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** VENTIQ's own staff only. */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return $next($request);
    }
}
