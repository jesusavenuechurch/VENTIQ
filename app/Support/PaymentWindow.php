<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Carbon;

/**
 * How long an unpaid ticket holds its place: the event's payment window,
 * else the platform default (ventiq.payment_window_hours), never later
 * than the event itself. Null when no window is configured — the ticket
 * simply waits, as before.
 */
class PaymentWindow
{
    public static function hours(Event $event): ?int
    {
        return $event->payment_window_hours ?? config('ventiq.payment_window_hours');
    }

    public static function dueAt(Event $event, ?Carbon $from = null): ?Carbon
    {
        $hours = static::hours($event);

        if (!$hours) {
            return null;
        }

        $due = ($from ?? now())->copy()->addHours($hours);

        return $event->event_date && $event->event_date->lt($due) ? $event->event_date->copy() : $due;
    }
}
