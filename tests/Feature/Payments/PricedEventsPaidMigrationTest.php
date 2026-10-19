<?php

use App\Models\{Event, EventTier, Organization};
use Illuminate\Support\Str;

it('marks old events with priced tickets as paid, and leaves free ones free', function () {
    $org = Organization::factory()->create();
    $make = fn ($price) => tap(Event::create([
        'organization_id' => $org->id, 'name' => 'E', 'slug' => 'e-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'free',
    ]), fn ($e) => EventTier::create(['event_id' => $e->id, 'tier_name' => 'T', 'price' => $price, 'quantity_available' => 5, 'is_active' => true]));
    $priced = $make(150);
    $free = $make(0);

    (include database_path('migrations/2026_10_07_000001_mark_priced_events_as_paid.php'))->up();

    expect($priced->fresh()->payment_mode)->toBe('paid')
        ->and($free->fresh()->payment_mode)->toBe('free');
});
