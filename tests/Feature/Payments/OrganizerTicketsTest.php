<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, TicketFee, User};
use App\Services\TicketDeliveryService;
use App\Services\WhatsAppCloudService;
use Illuminate\Support\Facades\{Cache, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    Cache::flush();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');

    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->general = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 250, 'is_active' => true]);
    $this->table = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Table of 3', 'price' => 750, 'quantity_per_purchase' => 3, 'is_active' => true]);
});

function attendee(object $t, string $name, string $phone, string $status = 'active'): Ticket
{
    $client = Client::create(['organization_id' => $t->org->id, 'full_name' => $name, 'phone' => $phone]);

    return Ticket::create([
        'event_id' => $t->event->id, 'client_id' => $client->id, 'event_tier_id' => $t->general->id,
        'status' => $status, 'payment_status' => $status === 'active' ? 'completed' : 'pending', 'amount' => 250,
    ]);
}

it('issues an active complimentary ticket and sends it once', function () {
    $delivery = Mockery::mock(TicketDeliveryService::class);
    $delivery->shouldReceive('deliver')->once()->andReturn(true);
    app()->instance(TicketDeliveryService::class, $delivery);

    $this->actingAs($this->admin)->get(route('organizer.events.comp.create', $this->event))
        ->assertOk()->assertSee('Table of 3')->assertSee('M7.50 per person');

    $this->post(route('organizer.events.comp.store', $this->event), [
        'event_tier_id' => $this->table->id, 'full_name' => 'Dr Mpho Lebona', 'phone' => '5949 4756',
        'reason' => 'Keynote speaker', 'send_whatsapp' => '1',
    ])->assertRedirect(route('organizer.events.attendees', $this->event))->assertSessionHas('status');

    $ticket = Ticket::where('is_complimentary', true)->with('client')->firstOrFail();
    expect($ticket->status)->toBe('active')
        ->and($ticket->admissions)->toBe(3)
        ->and($ticket->client->phone)->toBe('+26659494756')
        ->and($ticket->complimentary_reason)->toBe('Keynote speaker')
        ->and((float) TicketFee::where('ticket_id', $ticket->id)->value('total_fee'))->toBe(22.5)
        ->and($this->table->fresh()->quantity_sold)->toBe(1);
});

it('won\'t give away a place in a full tier', function () {
    $this->general->update(['quantity_available' => 1]);
    attendee($this, 'Already in', '+26650000001');

    $this->actingAs($this->admin)->post(route('organizer.events.comp.store', $this->event), [
        'event_tier_id' => $this->general->id, 'full_name' => 'Guest', 'phone' => '50000002',
    ])->assertSessionHasErrors('event_tier_id');

    expect(Ticket::where('is_complimentary', true)->exists())->toBeFalse();
});

it('keeps comps to the organization\'s own events and to people who can approve', function () {
    $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $outsider->assignRole('org_admin');
    $this->actingAs($outsider)->get(route('organizer.events.comp.create', $this->event))->assertNotFound();

    $tier = EventTier::create(['event_id' => Event::create([
        'organization_id' => $outsider->organization_id, 'name' => 'Other', 'slug' => 'o-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid',
    ])->id, 'tier_name' => 'X', 'price' => 1, 'is_active' => true]);
    $this->actingAs($this->admin)->post(route('organizer.events.comp.store', $this->event), [
        'event_tier_id' => $tier->id, 'full_name' => 'Guest', 'phone' => '50000002',
    ])->assertSessionHasErrors('event_tier_id');
});

it('resends a ticket on WhatsApp, to a corrected number if given', function () {
    $whatsapp = Mockery::mock(WhatsAppCloudService::class);
    $whatsapp->shouldReceive('sendTicketApproved')->once()->andReturn(true);
    app()->instance(WhatsAppCloudService::class, $whatsapp);

    $ticket = attendee($this, 'Lerato', '+26650000009');

    $this->actingAs($this->admin)->post(route('organizer.tickets.resend', $ticket), ['phone' => '5949 4756'])
        ->assertSessionHas('status', 'Ticket sent to Lerato on WhatsApp (+26659494756).');

    expect($ticket->client->fresh()->phone)->toBe('+26659494756')
        ->and($ticket->fresh()->whatsapp_delivered_at)->not->toBeNull();

    // A second tap straight away doesn't send again.
    $this->post(route('organizer.tickets.resend', $ticket))->assertSessionHas('status', fn ($s) => str_contains($s, 'just sent'));
});

it('says so when WhatsApp refuses, and only sends valid tickets', function () {
    $whatsapp = Mockery::mock(WhatsAppCloudService::class);
    $whatsapp->shouldReceive('sendTicketApproved')->once()->andReturn(false);
    app()->instance(WhatsAppCloudService::class, $whatsapp);

    $ticket = attendee($this, 'Lerato', '+26650000009');
    $this->actingAs($this->admin)->post(route('organizer.tickets.resend', $ticket))
        ->assertSessionHas('status', fn ($s) => str_contains($s, "didn't accept"));
    expect($ticket->fresh()->delivery_status)->toBe('failed');

    $unpaid = attendee($this, 'Thabo', '+26650000010', 'pending');
    $this->post(route('organizer.tickets.resend', $unpaid))->assertSessionHas('status', fn ($s) => str_contains($s, 'Only active'));

    $taken = attendee($this, 'Palesa', '+26650000011');
    $this->post(route('organizer.tickets.resend', $taken), ['phone' => '50000009'])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'already belongs to Lerato'));
});

it('finds attendees by name, phone digits or entry code', function () {
    $lerato = attendee($this, 'Lerato Mokoena', '+26659494756');
    $thabo = attendee($this, 'Thabo Molefe', '+26650001111');
    $url = fn ($q) => route('organizer.events.attendees', [$this->event, 'q' => $q]);

    $this->actingAs($this->admin)->get($url('lerato'))->assertSee('Lerato Mokoena')->assertDontSee('Thabo Molefe');
    $this->get($url('5949 4756'))->assertSee('Lerato Mokoena')->assertDontSee('Thabo Molefe');
    $this->get($url($thabo->voucher_code))->assertSee('Thabo Molefe')->assertDontSee('Lerato Mokoena');
    $this->get($url('nobody'))->assertSee('Nobody matches');
});
