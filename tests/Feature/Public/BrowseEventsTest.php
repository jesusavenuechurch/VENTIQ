<?php

use App\Models\{Event, EventTier, Organization};
use Illuminate\Support\Str;

beforeEach(function () {
    $this->org = Organization::factory()->create(['slug' => 'org-' . Str::random(4)]);
    $this->event = function (array $attrs) {
        $event = Event::create(array_merge([
            'organization_id' => $this->org->id, 'slug' => 'e-' . Str::random(6), 'event_date' => now()->addWeeks(2),
            'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true, 'city' => 'Maseru',
        ], $attrs));
        EventTier::create(['event_id' => $event->id, 'tier_name' => 'General', 'price' => ($attrs['payment_mode'] ?? 'paid') === 'free' ? 0 : 150, 'is_active' => true]);

        return $event;
    };
});

it('lists upcoming events and filters them', function () {
    ($this->event)(['name' => 'Gospel Night']);
    ($this->event)(['name' => 'Free Fun Run', 'payment_mode' => 'free', 'city' => 'Leribe']);
    ($this->event)(['name' => 'Last Year', 'event_date' => now()->subYear()]);
    ($this->event)(['name' => 'Secret Draft', 'status' => 'draft']);

    $this->get(route('events.browse'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Events/Browse')->has('events.data', 2)->where('filters.free', false));

    $this->get(route('events.browse', ['free' => 1]))
        ->assertInertia(fn ($page) => $page->has('events.data', 1)->where('events.data.0.name', 'Free Fun Run')->where('filters.free', true));

    // A district that isn't one is ignored rather than emptying the list.
    $this->get(route('events.browse', ['district' => 'Atlantis']))
        ->assertInertia(fn ($page) => $page->has('events.data', 2)->where('filters.district', null));
    $this->get(route('events.browse', ['district' => 'Leribe']))
        ->assertInertia(fn ($page) => $page->has('events.data', 1)->where('filters.district', 'Leribe'));
});
