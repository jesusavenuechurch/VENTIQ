<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, PaymentSession, Ticket, User};
use Illuminate\Support\Facades\{Bus, Mail, Notification};
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Notification::fake();
    config([
        'gateways.paylesotho.enabled' => true,
        'gateways.paylesotho.ecocash.enabled' => true,
        'gateways.paylesotho.ecocash.merchant_code' => '62000000',
        'gateways.paylesotho.mpesa.enabled' => false,
        'gateways.paylesotho.mpesa.merchant_number' => '58000000',
        'gateways.paylesotho.mpesa.merchant_name' => 'VENTIQ',
    ]);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'online', 'is_active' => true]);
    $this->event = Event::create(['organization_id' => $this->org->id, 'name' => 'Finite Awards', 'slug' => 'f-' . Str::random(6), 'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true]);
    $tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 150, 'is_active' => true]);
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Lerato Molapo', 'phone' => '+26658881234']);
    $this->ticket = Ticket::create(['event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 150, 'qr_code' => 'QR-' . Str::uuid()]);
});

it('offers M-Pesa and a bank transfer to VENTIQ by hand, with their details, and EcoCash online', function () {
    $this->get(route('ticket.pay', $this->ticket->qr_code))->assertInertia(fn (Assert $page) => $page->component('Tickets/Pay')
        ->where('online.methods', ['ecocash'])
        ->where('manual.0.method', 'mpesa')->where('manual.0.lines.0.value', '58000000')
        ->where('manual.1.method', 'bank')->where('manual.1.lines', fn ($lines) => collect($lines)->pluck('value', 'label')->get('Account number') === '63212657621'));

    // An event that chose only EcoCash doesn't offer the others.
    $this->event->update(['online_methods' => ['ecocash']]);
    $this->get(route('ticket.pay', $this->ticket->qr_code))->assertInertia(fn (Assert $page) => $page->where('manual', []));
});

it('takes an M-Pesa payment by hand with its transaction ID, for VENTIQ to confirm', function () {
    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), ['method' => 'mpesa', 'merchant_phone' => '58881234'])
        ->assertSessionHasErrors(['merchant_reference' => 'Add the transaction ID from your M-Pesa message, or a screenshot of it.']);

    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), ['method' => 'mpesa', 'merchant_phone' => '58881234', 'merchant_reference' => 'QK71H2XY9'])->assertRedirect();
    expect(PaymentSession::sole())->gateway->toBe('paylesotho')->payment_method->toBe('mpesa')->transaction_id->toBe('QK71H2XY9')->status->toBe('pending');

    // On VENTIQ's Money page to check, and confirmed there.
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $this->actingAs($super)->get(route('ventiq.money.index'))->assertInertia(fn (Assert $page) => $page->where('toCheck.0.method', 'M-Pesa'));
    $this->post(route('ventiq.money.online.decide', PaymentSession::sole()), ['decision' => 'received']);
    expect($this->ticket->fresh()->payment_status)->toBe('completed');
});

it('takes a bank transfer by hand with the bank\'s reference, without a phone number', function () {
    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), ['method' => 'bank', 'merchant_reference' => 'FNB 99812'])->assertRedirect();
    expect(PaymentSession::sole())->gateway->toBe('bank')->payment_method->toBe('bank_transfer');

    // Not one of the Sessions plan transfers.
    $admin = User::factory()->create(['organization_id' => $this->org->id]);
    $admin->assignRole('org_admin');
    $this->actingAs($admin)->get(route('sessions.plan'))->assertInertia(fn (Assert $page) => $page->where('transfers', []));
});

it('refuses a method the event doesn\'t offer', function () {
    $this->event->update(['online_methods' => ['ecocash']]);
    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), ['method' => 'bank', 'merchant_reference' => 'FNB 1'])->assertNotFound();
});

it('keeps EcoCash to numbers starting with 6', function () {
    $this->postJson(route('ticket.pay.online', $this->ticket->qr_code), ['method' => 'ecocash', 'mobile_number' => '+26658881234'])
        ->assertStatus(422)->assertJson(['message' => 'EcoCash numbers start with 6. For a number starting with 5, choose M-Pesa.']);
});

it('lets a super admin create an event for an organization', function () {
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $this->actingAs($super)->get(route('ventiq.events.index'))->assertInertia(fn (Assert $page) => $page->where('organizations', fn ($o) => collect($o)->pluck('value')->contains($this->org->id)));
    $this->get(route('organizer.act-as.start', ['organization' => $this->org->id, 'to' => 'create']))->assertRedirect(route('organizer.events.create'));
});
