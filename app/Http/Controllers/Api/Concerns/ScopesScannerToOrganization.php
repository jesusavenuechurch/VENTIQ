<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Door devices only see their own organization's tickets (super admins
 * see all), and, when the app says which event it is scanning for, only
 * that event's.
 */
trait ScopesScannerToOrganization
{
    protected function scopeToScanner(Builder $tickets, ?int $eventId = null): Builder
    {
        $user = request()->user();

        if ($user && !$user->hasRole('super_admin')) {
            $tickets->whereHas('event', fn ($q) => $q->where('organization_id', $user->organization_id ?? 0));
        }

        return $eventId ? $tickets->where('event_id', $eventId) : $tickets;
    }
}
