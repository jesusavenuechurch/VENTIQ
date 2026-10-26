<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, User};
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create(['slug' => 'org-' . Str::random(4)]);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');

    $this->makeEvent = function (string $status, array $attrs = []) {
        $event = Event::create(array_merge([
            'organization_id' => $this->org->id, 'name' => ucfirst($status) . ' event', 'slug' => $status . '-' . Str::random(5),
            'event_date' => now()->addMonth(), 'status' => $status, 'payment_mode' => 'free', 'is_public' => true,
        ], $attrs));
        EventTier::create(['event_id' => $event->id, 'tier_name' => 'General', 'price' => 0, 'is_active' => true]);

        return $event;
    };
});

it('keeps drafts off the public site, except for the organization previewing them', function () {
    $draft = ($this->makeEvent)('draft');
    $url = route('event.short', [$this->org->slug, $draft->slug]);

    $this->get($url)->assertNotFound();
    $this->get(route('registration.form', [$this->org->slug, $draft->slug]))
        ->assertInertia(fn ($page) => $page->component('Events/RegistrationClosed')->where('closedReason', fn ($r) => str_contains($r, 'opened yet')));
    $this->get(route('public.events', $this->org->slug))->assertDontSee('Draft event');

    // The page shows its preview notice for a draft.
    $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Events/Show')->where('event.status', 'draft'));
});

it('closes registration on cancelled and finished events, and says why', function () {
    $cancelled = ($this->makeEvent)('cancelled');
    $this->get(route('event.short', [$this->org->slug, $cancelled->slug]))
        ->assertOk()->assertInertia(fn ($page) => $page->component('Events/Show')
            ->where('closedReason', 'This event has been cancelled.')->where('canRegister', false)->where('tiers.0.soldOut', true));

    $tier = $cancelled->tiers()->first();
    $this->post(route('registration.submit', [$this->org->slug, $cancelled->slug]), [
        'tier_id' => $tier->id, 'full_name' => 'Late Comer', 'phone' => '59494756', 'terms' => '1',
    ])->assertSee('This event has been cancelled.');

    expect(Ticket::count())->toBe(0);

    $ended = ($this->makeEvent)('completed');
    $this->get(route('registration.form', [$this->org->slug, $ended->slug]))
        ->assertInertia(fn ($page) => $page->component('Events/RegistrationClosed')->where('closedReason', 'This event has ended.'));
});

it('only sells this event\'s ticket types that are on sale', function () {
    $open = ($this->makeEvent)('published');
    $other = ($this->makeEvent)('published');
    $foreignTier = $other->tiers()->first();
    $offSale = EventTier::create(['event_id' => $open->id, 'tier_name' => 'Old', 'price' => 0, 'is_active' => false]);

    foreach ([$foreignTier, $offSale] as $tier) {
        $this->post(route('registration.submit', [$this->org->slug, $open->slug]), [
            'tier_id' => $tier->id, 'full_name' => 'Sneaky', 'phone' => '59494756', 'terms' => '1',
        ])->assertNotFound();
    }

    expect(Ticket::count())->toBe(0);
});

it('lists only published events', function () {
    ($this->makeEvent)('published', ['name' => 'Open summit']);
    ($this->makeEvent)('draft', ['name' => 'Secret plan']);
    ($this->makeEvent)('cancelled', ['name' => 'Called off']);

    $names = Event::listed()->pluck('name')->all();
    expect($names)->toContain('Open summit')->not->toContain('Secret plan')->not->toContain('Called off');
});

it('deletes an event nobody registered for, and refuses once people have', function () {
    $empty = ($this->makeEvent)('draft');
    $this->actingAs($this->admin)->delete(route('organizer.events.destroy', $empty))->assertRedirect(route('organizer.home'));
    expect(Event::find($empty->id))->toBeNull();

    $busy = ($this->makeEvent)('published');
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Guest', 'phone' => '+26650000001']);
    Ticket::create(['event_id' => $busy->id, 'client_id' => $client->id, 'event_tier_id' => $busy->tiers()->first()->id, 'status' => 'active', 'payment_status' => 'completed', 'amount' => 0]);

    $this->get(route('organizer.events.edit', $busy))->assertInertia(fn ($page) => $page->where('event.hasTickets', true));
    $this->delete(route('organizer.events.destroy', $busy))->assertSessionHas('status', fn ($s) => str_contains($s, 'Cancelled'));
    expect(Event::find($busy->id))->not->toBeNull();

    $staff = User::factory()->create(['organization_id' => $this->org->id]);
    $staff->assignRole('staff');
    $this->actingAs($staff)->delete(route('organizer.events.destroy', ($this->makeEvent)('draft')))->assertForbidden();
});

it('shows an event by invitation, but nobody registers on it: the organizer issues the tickets', function () {
    $gala = ($this->makeEvent)('published', ['by_invitation' => true, 'name' => 'Sponsors gala']);

    $this->get(route('event.short', [$this->org->slug, $gala->slug]))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Events/Show')->where('byInvitation', true)->where('canRegister', false)
            ->where('closedReason', "By invitation: {$this->org->name} issues the tickets for this event."));
    $this->get(route('registration.form', [$this->org->slug, $gala->slug]))
        ->assertInertia(fn ($page) => $page->component('Events/RegistrationClosed')->where('closedReason', fn ($r) => str_starts_with($r, 'By invitation')));
    $this->post(route('registration.submit', [$this->org->slug, $gala->slug]), [
        'tier_id' => $gala->tiers()->first()->id, 'full_name' => 'Gate Crasher', 'phone' => '59494756', 'terms' => '1',
    ]);
    expect(Ticket::count())->toBe(0);

    // The organizer still sends tickets.
    $this->actingAs($this->admin)->post(route('organizer.events.comp.store', $gala), [
        'event_tier_id' => $gala->tiers()->first()->id, 'full_name' => 'Invited Guest', 'phone' => '5949 4756',
    ])->assertSessionHasNoErrors();
    expect(Ticket::count())->toBe(1);

    // Once it's over, it says that instead.
    $gala->update(['status' => 'completed']);
    $this->get(route('event.short', [$this->org->slug, $gala->slug]))
        ->assertInertia(fn ($page) => $page->where('byInvitation', false)->where('closedReason', 'This event has ended.'));
});

