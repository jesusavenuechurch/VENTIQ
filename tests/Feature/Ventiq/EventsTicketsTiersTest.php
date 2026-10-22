<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, TierTemplate, User};
use App\Services\TicketDeliveryService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->super = User::factory()->create(['organization_id' => null]);
    $this->super->assignRole('super_admin');
    $this->org = Organization::factory()->create(['name' => 'Maseru Youth Network']);
    $this->event = Event::create(['organization_id' => $this->org->id, 'name' => 'Youth Summit', 'slug' => 'summit-' . Str::random(4), 'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 200, 'quantity_available' => 10, 'is_active' => true]);
    $this->client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Lerato Mokoena', 'phone' => '+26659494756']);
    $this->ticket = Ticket::create(['event_id' => $this->event->id, 'client_id' => $this->client->id, 'event_tier_id' => $this->tier->id, 'status' => 'active', 'payment_status' => 'completed', 'amount' => 200]);
});

it('lists events across organizations and sponsors their fees', function () {
    $this->actingAs($this->super)->get(route('ventiq.events.index', ['q' => 'Maseru']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Ventiq/Events')->where('rows.data.0.name', 'Youth Summit')->where('rows.data.0.tickets', 1));

    $this->post(route('ventiq.events.sponsor', $this->event))->assertSessionHas('status');
    expect($this->event->fresh()->fees_sponsored)->toBeTruthy();
    $this->get(route('ventiq.events.index', ['filter' => ['sponsored' => 'no']]))->assertInertia(fn ($page) => $page->has('rows.data', 0));
});

it('finds a ticket by phone digits or entry code, and sends it again', function () {
    $this->actingAs($this->super)->get(route('ventiq.tickets.index', ['q' => '5949 4756']))
        ->assertInertia(fn ($page) => $page->component('Ventiq/Tickets')->has('rows.data', 1)->where('rows.data.0.holder', 'Lerato Mokoena'));
    $this->get(route('ventiq.tickets.index', ['q' => $this->ticket->voucher_code]))->assertInertia(fn ($page) => $page->has('rows.data', 1));
    $this->get(route('ventiq.tickets.index', ['q' => 'nobody']))->assertInertia(fn ($page) => $page->has('rows.data', 0));

    $delivery = Mockery::mock(TicketDeliveryService::class);
    $delivery->shouldReceive('deliver')->once()->andReturn(true);
    app()->instance(TicketDeliveryService::class, $delivery);
    $this->post(route('ventiq.tickets.resend', $this->ticket))->assertSessionHas('status');
    $this->post(route('ventiq.tickets.resend', $this->ticket))->assertSessionHas('error');
});

it('opens an event straight in its organizer view', function () {
    $this->actingAs($this->super)->get(route('organizer.act-as.start', ['organization' => $this->org, 'event' => $this->event->id, 'q' => 'VQ-1']))
        ->assertRedirect(route('organizer.events.attendees', [$this->event, 'q' => 'VQ-1']));
    $this->get(route('organizer.act-as.start', ['organization' => $this->org, 'event' => $this->event->id, 'to' => 'edit']))
        ->assertRedirect(route('organizer.events.edit', $this->event));
});

it('changes the price of many ticket types at once', function () {
    $vip = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'VIP', 'price' => 500, 'is_active' => true]);

    $this->actingAs($this->super)->post(route('ventiq.tiers.bulk'), ['ids' => [$this->tier->id, $vip->id], 'action' => 'change_price', 'value' => -10])
        ->assertSessionHas('status', '2 ticket types updated.');
    expect((float) $this->tier->fresh()->price)->toBe(180.0)->and((float) $vip->fresh()->price)->toBe(450.0);

    $this->post(route('ventiq.tiers.bulk'), ['ids' => [$vip->id], 'action' => 'deactivate']);
    expect($vip->fresh()->is_active)->toBeFalsy();
    // The ticket already issued keeps its price.
    expect((float) $this->ticket->fresh()->amount)->toBe(200.0);

    $this->get(route('ventiq.tiers.index', ['filter' => ['active' => 'no']]))->assertInertia(fn ($page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'VIP'));
});

it('lists clients across organizations', function () {
    $this->actingAs($this->super)->get(route('ventiq.clients.index', ['q' => 'lerato']))
        ->assertInertia(fn ($page) => $page->component('Ventiq/Clients')->has('rows.data', 1)->where('rows.data.0.tickets', 1));
});

it('saves a tier template and adds it to an event of its organization only', function () {
    $this->actingAs($this->super)->post(route('ventiq.tier-templates.store'), [
        'name' => 'Conference', 'organization_id' => $this->org->id, 'is_default' => true,
        'tiers' => [['tier_name' => 'Early bird', 'price' => 100, 'color' => '#1D4069'], ['tier_name' => 'Door', 'price' => 150, 'quantity_available' => 50]],
    ])->assertRedirect();
    $template = TierTemplate::sole();
    expect($template->tiers_config)->toHaveCount(2)->and($template->is_default)->toBeTrue();

    $this->post(route('ventiq.tier-templates.apply', $template), ['event_id' => $this->event->id])->assertSessionHas('status', fn ($s) => str_contains($s, '2 ticket types added'));
    expect($this->event->tiers()->count())->toBe(3);

    $other = Event::create(['organization_id' => Organization::factory()->create()->id, 'name' => 'Elsewhere', 'slug' => 'else-' . Str::random(4), 'event_date' => now()->addMonth(), 'status' => 'draft']);
    $this->post(route('ventiq.tier-templates.apply', $template), ['event_id' => $other->id])->assertSessionHasErrors('event_id');

    $this->post(route('ventiq.tier-templates.store'), ['name' => 'Bad', 'tiers' => [['tier_name' => 'A', 'price' => 1], ['tier_name' => 'a', 'price' => 2]]])
        ->assertSessionHasErrors('tiers.1.tier_name');
});

it('is for super admins only', function () {
    $admin = User::factory()->create(['organization_id' => $this->org->id]);
    $admin->assignRole('org_admin');
    foreach (['ventiq.events.index', 'ventiq.tickets.index', 'ventiq.tiers.index', 'ventiq.clients.index', 'ventiq.tier-templates.index'] as $name) {
        $this->actingAs($admin)->get(route($name))->assertForbidden();
    }
});
