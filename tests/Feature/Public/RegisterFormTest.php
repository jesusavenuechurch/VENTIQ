<?php

use App\Models\{Event, EventTier, Organization};
use Illuminate\Support\Str;

beforeEach(function () {
    $this->org = Organization::factory()->create(['slug' => 'org-' . Str::random(4)]);
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->url = route('registration.form', [$this->org->slug, $this->event->slug]);
});

it('uses the only ticket type when the link names none', function () {
    EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 250, 'is_active' => true]);

    $this->get($this->url)->assertOk()->assertSee('General');
});

it('sends people to the event page to choose when there are several types', function () {
    EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 250, 'is_active' => true]);
    EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'VIP', 'price' => 500, 'is_active' => true]);

    $this->get($this->url)->assertRedirect(route('event.short', [$this->org->slug, $this->event->slug]));
    $this->get($this->url . '?tier=999999')->assertRedirect();
});
