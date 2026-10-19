<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

/**
 * The organization the organizer area is showing. An org user always sees
 * their own. A super admin has no organization of their own; they pick
 * one from Filament ("Open organizer view") to act on its behalf, kept in
 * the session until they exit.
 */
class CurrentOrganization
{
    public const SESSION_KEY = 'acting_organization_id';

    public static function id(): ?int
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        if ($user->organization_id) {
            return (int) $user->organization_id;
        }

        return $user->isSuperAdmin() ? (session(self::SESSION_KEY) ?: null) : null;
    }

    public static function get(): ?Organization
    {
        $id = static::id();

        return $id ? Organization::find($id) : null;
    }

    /** True when a super admin is looking at someone else's organization. */
    public static function isActingAs(): bool
    {
        $user = Auth::user();

        return $user && !$user->organization_id && $user->isSuperAdmin() && session(self::SESSION_KEY);
    }
}
