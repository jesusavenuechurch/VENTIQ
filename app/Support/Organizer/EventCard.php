<?php

namespace App\Support\Organizer;

use App\Models\{AppRelease, Event};

/** Pieces several organizer pages show, as React props. */
class EventCard
{
    /** What the Share & QR panel needs for an event, or null when it has no link. */
    public static function share(Event $event): ?array
    {
        if (!$event->share_url) {
            return null;
        }

        return [
            'name'      => $event->name,
            'date'      => $event->event_date?->format('l, j F Y'),
            'url'       => $event->share_url,
            'qr'        => route('organizer.events.qr', $event),
            'slug'      => $event->slug,
            'published' => (bool) $event->is_public,
        ];
    }

    /** The VENTIQ Scanner app for the door, when there's one to get. */
    public static function scannerApp(): ?array
    {
        $app = AppRelease::current();
        if (!$app || (!$app->hasApk() && !$app->play_store_url)) {
            return null;
        }

        return [
            'version'     => $app->version,
            'size'        => $app->sizeLabel(),
            'playStore'   => $app->play_store_url,
            'downloadUrl' => $app->hasApk() ? route('scanner-app.download') : null,
        ];
    }
}
