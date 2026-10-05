<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, PaymentSession, SettlementItem, Ticket, TicketFee, TicketPayment};
use App\Services\Payments\TicketActivationService;
use Illuminate\Support\Facades\{Bus, Http, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Http::fake(['*' => Http::response(['status_code' => '201', 'transaction_reference' => 'PL-' . Str::random(6)], 201)]);
    config(['gateways.paylesotho.enabled' => true, 'gateways.paylesotho.ecocash.enabled' => true]);

    $this->org = Organization::factory()->create();
    OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'online', 'is_active' => true]);
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]);
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Lerato', 'phone' => '+26659494756']);
    $this->ticket = Ticket::create([
        'event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $this->tier->id,
        'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200, 'amount_paid' => 0,
    ]);
    TicketPayment::create(['ticket_id' => $this->ticket->id, 'amount' => 200, 'status' => 'pending', 'payment_type' => 'full']);

    $this->pay = fn () => $this->postJson(route('paylesotho.ticket.initiate'), [
        'ticket_id' => $this->ticket->id, 'method' => 'ecocash', 'mobile_number' => '59494756',
    ]);
    $this->callback = function (PaymentSession $session) {
        return $this->postJson('/payment/paylesotho/callback/ecocash', ['client_reference' => $session->client_reference, 'status' => 'completed']);
    };
});

it('uses a payment reference nobody can guess', function () {
    ($this->pay)()->assertOk();
    $ref = PaymentSession::sole()->client_reference;

    expect($ref)->toStartWith('ECOCASH' . $this->ticket->id . 'T')
        ->and(strlen($ref))->toBe(strlen('ECOCASH' . $this->ticket->id . 'T') + 10)
        ->and(str_contains($ref, (string) now()->timestamp))->toBeFalse();
});

it('follows a push already on its way instead of charging twice, and refuses paid tickets', function () {
    $first = ($this->pay)()->assertOk()->json('session_id');
    expect(($this->pay)()->json('session_id'))->toBe($first)
        ->and(PaymentSession::count())->toBe(1);

    ($this->callback)(PaymentSession::find($first))->assertOk();
    ($this->pay)()->assertStatus(422)->assertJson(['message' => 'This ticket is already paid.']);
});

it('charges only the balance after a deposit paid to the organizer, and pays out only that', function () {
    $deposit = $this->ticket->payments()->first();
    $deposit->update(['amount' => 80, 'submitted_at' => now(), 'payment_method' => 'ecocash']);
    app(TicketActivationService::class)->confirmDeposit($deposit);   // M80 held by the organizer
    $this->ticket->refresh()->update(['status' => 'active']);         // organizer let them in on the deposit
    expect(TicketFee::where('ticket_id', $this->ticket->id)->value('collection'))->toBe(TicketFee::COLLECT_BY_INVOICE);

    ($this->pay)()->assertOk();
    $session = PaymentSession::sole();
    expect((float) $session->amount)->toBe(120.0);

    ($this->callback)($session)->assertOk();

    $item = SettlementItem::sole();
    expect((float) $item->gross_paid)->toBe(120.0)
        ->and((float) $item->gateway_fee)->toBe(0.0)             // fee already invoiced, not taken twice
        ->and((float) $item->amount_owed_to_org)->toBe(120.0)
        ->and($this->ticket->fresh()->payment_status)->toBe('completed')
        ->and((float) $this->ticket->fresh()->amount_paid)->toBe(200.0);
});

it('records what was charged online, and closes a manual submission still waiting', function () {
    $manual = $this->ticket->payments()->first();
    $manual->update(['amount' => 50, 'submitted_at' => now(), 'payment_method' => 'mpesa']);   // a deposit claim
    TicketPayment::create(['ticket_id' => $this->ticket->id, 'amount' => 200, 'status' => 'pending', 'payment_type' => 'full']);

    ($this->pay)()->assertOk();
    ($this->callback)(PaymentSession::sole())->assertOk();

    $ticket = $this->ticket->fresh();
    expect($ticket->payment_status)->toBe('completed')
        ->and($ticket->status)->toBe('active')
        ->and((float) $ticket->amount_paid)->toBe(200.0)
        ->and($manual->fresh()->status)->toBe('rejected')
        ->and(TicketPayment::where('ticket_id', $ticket->id)->where('status', 'approved')->sole()->source)->toBe('ventiq_online');

    $item = SettlementItem::sole();
    expect((float) $item->gross_paid)->toBe(200.0)
        ->and((float) $item->gateway_fee)->toBe(round(200 * 0.049 + 7.5, 2))
        ->and((float) $item->amount_owed_to_org)->toBe(round(200 - (200 * 0.049 + 7.5), 2));
});

it('won\'t take money for a ticket that has expired', function () {
    $this->ticket->update(['status' => 'expired']);
    ($this->pay)()->assertStatus(422);
    expect(PaymentSession::count())->toBe(0);
});
