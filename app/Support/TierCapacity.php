<?php

namespace App\Support;

use App\Models\EventTier;

/**
 * Whether a tier still has room. An inactive (unpaid) ticket holds its
 * place until it's paid or its payment window expires, so held tickets
 * count against the tier's quantity along with paid ones. A tier's
 * quantity counts tickets: a group ticket uses one.
 */
class TierCapacity
{
    public const HOLDING_STATUSES = ['pending', 'active', 'checked_in'];

    public static function taken(EventTier $tier): int
    {
        return $tier->tickets()->whereIn('status', self::HOLDING_STATUSES)->count();
    }

    public static function hasRoom(EventTier $tier): bool
    {
        return !$tier->quantity_available || static::taken($tier) < $tier->quantity_available;
    }
}
