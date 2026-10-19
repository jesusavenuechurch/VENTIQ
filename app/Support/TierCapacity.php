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

    /**
     * Once a ticket type fills up, tell the organizer, once, that they can
     * allow more tickets. Run after the ticket that filled it is saved.
     */
    public static function noticeIfSoldOut(EventTier $tier): void
    {
        if (!$tier->quantity_available || $tier->sold_out_notified_at || static::hasRoom($tier)) {
            return;
        }

        // Only the first to get here sends it.
        $claimed = EventTier::whereKey($tier->id)->whereNull('sold_out_notified_at')->update(['sold_out_notified_at' => now()]);
        if ($claimed) {
            app(\App\Services\Notifications\OrganizerNotifier::class)->tierSoldOut($tier->fresh(['event.organization']));
        }
    }
}
