<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, PaymentSession, Ticket};
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\{Bus, Http, Mail, Notification};
use Illuminate\Support\Str;

/** The templates sent to Meta, in order. */
function templatesSent(): array
{
    return collect(Http::recorded())->map(fn ($pair) => $pair[0])
        ->filter(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com'))
        ->map(fn (Request $r) => $r['template']['name'] ?? null)->values()->all();
}

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Notification::fake();
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.1']], 'status_code' => '200', 'reference' => 'PL-1'])]);
    config([
        'services.whatsapp.access_token' => 't', 'services.whatsapp.phone_number_id' => '1', 'constants.ventiq_alerts' => ['email' => null, 'whatsapp' => null],
        'gateways.paylesotho.enabled' => true, 'gateways.paylesotho.ecocash.enabled' => true, 'gateways.paylesotho.ecocash.merchant_code' => '62000000',
    ]);
    $this->org = Organization::factory()->create();
    OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'online', 'is_active' => true]);
    $this->event = Event::create(['organization_id' => $this->org->id, 'name' => 'Sunday Service', 'slug' => 'sunday-' . Str::random(4), 'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true]);
    $tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Standard', 'price' => 100, 'is_active' => true]);
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Amo Lebona', 'phone' => '+26662221111']);
    $this->ticket = Ticket::create([
        'event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id, 'status' => 'pending', 'payment_status' => 'pending',
        'amount' => 100, 'qr_code' => 'QR-' . Str::uuid(), 'has_whatsapp' => true, 'preferred_delivery' => 'whatsapp', 'payment_due_at' => now()->addDays(2),
    ]);
});

it('says nothing is needed only once there is a payment to check, not when a PIN prompt goes out', function () {
    $this->postJson(route('ticket.pay.online', $this->ticket->qr_code), ['method' => 'ecocash', 'mobile_number' => '62221111']);
    expect(templatesSent())->not->toContain('ticket_registered');

    // Paid VENTIQ by hand: "received, being checked", once.
    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), ['merchant_phone' => '62221111', 'merchant_reference' => 'MP1']);
    $this->post(route('ticket.pay.merchant', $this->ticket->qr_code), ['merchant_phone' => '62221111', 'merchant_reference' => 'MP1']);
    expect(collect(templatesSent())->filter(fn ($t) => $t === 'ticket_registered')->count())->toBe(1);
});

it('reminds someone who registered and left, about 15 minutes later, once, and not while they are paying', function () {
    $this->travel(10)->minutes();
    $this->artisan('tickets:expire-unpaid');
    expect(templatesSent())->toBe([]);

    $this->travel(6)->minutes();
    $this->artisan('tickets:expire-unpaid');
    $this->artisan('tickets:expire-unpaid');
    expect(templatesSent())->toBe(['payment_reminder']);

    // Someone else who started paying gets no reminder.
    $other = $this->ticket->replicate()->fill(['qr_code' => 'QR-' . Str::uuid(), 'created_at' => now()->subMinutes(20), 'payment_nudge_sent_at' => null, 'voucher_code' => null, 'ticket_number' => null]);
    $other->save();
    PaymentSession::create(['payable_type' => 'ticket', 'payable_id' => $other->id, 'gateway' => 'paylesotho', 'status' => 'pending', 'amount' => 100, 'payment_method' => 'ecocash', 'organization_id' => $this->org->id, 'client_reference' => 'X1']);
    $this->artisan('tickets:expire-unpaid');
    expect(templatesSent())->toBe(['payment_reminder']);
});

it("sends the expired message with its button to the event's page", function () {
    $this->ticket->update(['payment_due_at' => now()->subMinute(), 'created_at' => now()->subDays(3)]);
    $this->artisan('tickets:expire-unpaid');

    expect($this->ticket->fresh()->status)->toBe('expired');
    Http::assertSent(fn (Request $r) => ($r['template']['name'] ?? null) === 'payment_expired'
        && collect($r['template']['components'] ?? [])->contains(fn ($c) => ($c['type'] ?? null) === 'button'
            && ($c['parameters'][0]['text'] ?? null) === "e/{$this->org->slug}/{$this->event->slug}"));
});
