<?php

namespace App\Support;

use App\Models\{Event, EventTier, Organization};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What the public event pages show, as React props
 * (resources/js/types/event.d.ts).
 */
class EventPage
{
    /** @return array<string, mixed> */
    public static function event(Event $event, Organization $organization): array
    {
        return [
            'name'              => $event->name,
            'slug'              => $event->slug,
            'status'            => $event->status,
            'type'              => $event->event_type,
            'description'       => (string) $event->description,
            'date'              => $event->event_date?->format('l, d M Y'),
            'shortDate'         => $event->event_date?->format('d M, Y'),
            'time'              => $event->event_date?->format('g:i A'),
            'venue'             => $event->venue,
            'banner'            => Thumb::url($event->banner_image, 480),
            'allowInstallments' => (bool) $event->allow_installments,
            'depositPercent'    => (float) $event->minimum_deposit_percentage,
            'organization'      => ['name' => $organization->name, 'slug' => $organization->slug, 'contactEmail' => $organization->contact_email],
        ];
    }

    /**
     * A ticket type as sold on the public pages.
     *
     * @param  array{available: ?int, is_sold_out: bool}|null  $availability
     * @return array<string, mixed>
     */
    public static function tier(EventTier $tier, Event $event, ?array $availability = null): array
    {
        $soldOut = (bool) ($availability['is_sold_out'] ?? false);
        $available = $availability['available'] ?? null;
        $price = (float) $tier->price;

        return [
            'id'          => $tier->id,
            'name'        => $tier->tier_name,
            'description' => $tier->description,
            'price'       => $price,
            'priceLabel'  => Money::price($price),
            'admits'      => max(1, (int) ($tier->quantity_per_purchase ?? 1)),
            'soldOut'     => $soldOut,
            // "Only 4 left" once ten or fewer remain.
            'fewLeft'     => !$soldOut && $available !== null && $available <= 10 ? $available : null,
            'depositFrom' => $event->allow_installments && $price > 0 && !$soldOut
                ? Money::price($price * ((float) $event->minimum_deposit_percentage / 100))
                : null,
        ];
    }

    /** The link preview for an event page. @return array<string, string|null> */
    public static function meta(Event $event, Organization $organization): array
    {
        return [
            'title'       => "{$event->name} - {$organization->name}",
            'description' => Str::limit((string) $event->description, 150),
            // Absolute: link previews fetch it from elsewhere.
            'image'       => $event->banner_image ? url(Storage::url($event->banner_image)) : null,
        ];
    }
}
