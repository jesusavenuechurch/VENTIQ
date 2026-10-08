<?php

use App\Models\{Client, Event, EventTier, Organization, PaymentSession, SettlementItem, Ticket, TicketPayment, User};
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->super = User::factory()->create(['organization_id' => null]);
    $this->super->assignRole('super_admin');

    $org = Organization::factory()->create();
    $event = Event::create(['organization_id' => $org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6), 'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid']);
    $tier = EventTier::create(['event_id' => $event->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]);
    $client = Client::create(['organization_id' => $org->id, 'full_name' => 'Lerato', 'phone' => '+26659494756']);
    $this->ticket = Ticket::create(['event_id' => $event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200, 'payment_due_at' => now()->subMinute()]);
    TicketPayment::create(['ticket_id' => $this->ticket->id, 'amount' => 200, 'status' => 'pending', 'payment_type' => 'full']);
    $this->session = PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $this->ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'ECOCASH1T' . random_int(1e9, 9e9),
        'payment_method' => 'ecocash', 'amount' => 200, 'status' => 'pending', 'organization_id' => $org->id,
    ]);
    $this->session->forceFill(['created_at' => now()->subMinutes(10)])->save();
});

it('keeps the place while an online payment waits for confirmation', function () {
    $this->artisan('tickets:expire-unpaid')->assertSuccessful();
    expect($this->ticket->fresh()->status)->toBe('pending');

    $this->get(route('ticket.download', $this->ticket->qr_code))->assertSee('being confirmed');
});

it('lets a super admin confirm a payment found on the merchant statement', function () {
    $this->actingAs($this->super)->get(route('ventiq.money.index'))->assertSee('Online payments to check')->assertSee('Lerato');

    $this->post(route('ventiq.money.online.decide', $this->session), ['decision' => 'received', 'reference' => 'MP123'])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'is active'));

    expect($this->ticket->fresh()->status)->toBe('active')
        ->and($this->session->fresh()->status)->toBe('completed')
        ->and(SettlementItem::where('ticket_id', $this->ticket->id)->exists())->toBeTrue();
});

it('closes a payment that never arrived, and is for super admins only', function () {
    $staff = User::factory()->create(['organization_id' => $this->ticket->event->organization_id]);
    $this->actingAs($staff)->post(route('ventiq.money.online.decide', $this->session), ['decision' => 'received'])->assertForbidden();

    $this->actingAs($this->super)->post(route('ventiq.money.online.decide', $this->session), ['decision' => 'not_received']);
    expect($this->session->fresh()->status)->toBe('failed')
        ->and($this->ticket->fresh()->status)->toBe('pending');
});
