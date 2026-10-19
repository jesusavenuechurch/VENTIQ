<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, TicketFee, TicketPayment, User};
use Illuminate\Support\Facades\{Bus, Mail, Notification};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Notification::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Standard', 'price' => 200, 'quantity_available' => 2, 'is_active' => true]);
});

it('sells a ticket for cash: active, paid to the organizer, its fee invoiced', function () {
    $this->actingAs($this->admin)->get(route('organizer.events.comp.create', [$this->event, 'kind' => 'sold']))
        ->assertOk()->assertSee('Issue a ticket')->assertSee('Paid with')->assertSee("kind: 'sold'", false);

    $this->actingAs($this->admin)->post(route('organizer.events.comp.store', $this->event), [
        'kind' => 'sold', 'event_tier_id' => $this->tier->id, 'full_name' => 'Lineo Mokoena', 'phone' => '58123456',
        'method' => 'cash', 'reference' => 'Receipt 42',
    ])->assertRedirect(route('organizer.events.attendees', $this->event))->assertSessionHas('status', fn ($s) => str_contains($s, 'Ticket sold to Lineo Mokoena'));

    $ticket = Ticket::where('event_id', $this->event->id)->firstOrFail();
    expect($ticket->status)->toBe('active')
        ->and($ticket->payment_status)->toBe('completed')
        ->and((float) $ticket->amount)->toBe(200.0)
        ->and($ticket->is_complimentary)->toBeFalsy();
    $payment = TicketPayment::where('ticket_id', $ticket->id)->sole();
    expect($payment->status)->toBe('approved')->and($payment->payment_method)->toBe('cash')
        ->and($payment->payment_reference)->toBe('Receipt 42')->and($payment->approved_by)->toBe($this->admin->id);
    expect(TicketFee::where('ticket_id', $ticket->id)->value('collection'))->toBe(TicketFee::COLLECT_BY_INVOICE);
    expect($this->tier->fresh()->quantity_sold)->toBe(1);
});

it("won't sell past a ticket type's number", function () {
    $this->tier->update(['quantity_available' => 1]);
    $sell = fn (string $phone) => $this->actingAs($this->admin)->post(route('organizer.events.comp.store', $this->event), [
        'kind' => 'sold', 'event_tier_id' => $this->tier->id, 'full_name' => 'Lineo', 'phone' => $phone,
    ]);

    $sell('58123456')->assertSessionHasNoErrors();
    $sell('58123457')->assertSessionHasErrors('event_tier_id');
    expect(Ticket::count())->toBe(1);
});

it('marks a reserved ticket as paid in cash', function () {
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Thabo', 'phone' => '+26658000001']);
    $ticket = Ticket::create(['event_id' => $this->event->id, 'event_tier_id' => $this->tier->id, 'client_id' => $client->id,
        'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200]);

    $this->actingAs($this->admin)->get(route('organizer.events.attendees', $this->event))
        ->assertOk()->assertSee(route('organizer.tickets.paid', $ticket), false);

    $this->post(route('organizer.tickets.paid', $ticket), ['method' => 'cash'])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe('active')->and($ticket->payment_status)->toBe('completed')->and($ticket->payment_method)->toBe('cash');
    expect(TicketFee::where('ticket_id', $ticket->id)->value('collection'))->toBe(TicketFee::COLLECT_BY_INVOICE);

    // Twice is harmless.
    $this->post(route('organizer.tickets.paid', $ticket), ['method' => 'cash'])->assertSessionHas('status', fn ($s) => str_contains($s, 'Only tickets awaiting payment'));
    expect(TicketPayment::where('ticket_id', $ticket->id)->where('status', 'approved')->count())->toBe(1);
});

it("keeps other organizations' tickets out of reach", function () {
    $other = Organization::factory()->create();
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Thabo', 'phone' => '+26658000001']);
    $ticket = Ticket::create(['event_id' => $this->event->id, 'event_tier_id' => $this->tier->id, 'client_id' => $client->id,
        'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200]);
    $outsider = User::factory()->create(['organization_id' => $other->id]);
    $outsider->assignRole('org_admin');

    $this->actingAs($outsider)->post(route('organizer.tickets.paid', $ticket))->assertNotFound();
    expect($ticket->fresh()->status)->toBe('pending');
});
