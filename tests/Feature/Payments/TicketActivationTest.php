<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, PaymentSession, SettlementItem, Ticket, TicketPayment, User};
use App\Services\Payments\TicketActivationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::create([
        'organization_id' => $this->org->id,
        'name'            => 'Maseru Youth Summit',
        'slug'            => 'summit-' . Str::random(6),
        'event_date'      => now()->addMonth(),
        'status'          => 'published',
        'is_public'       => true,
    ]);
    $this->tier = EventTier::create([
        'event_id'  => $this->event->id,
        'tier_name' => 'Standard',
        'price'     => 250,
        'is_active' => true,
    ]);
});

function pendingTicket(object $t, array $overrides = []): Ticket
{
    $client = Client::create([
        'organization_id' => $t->org->id,
        'full_name'       => 'Lerato Mokoena',
        'phone'           => '+2665' . random_int(1000000, 9999999),
    ]);

    $ticket = Ticket::create(array_merge([
        'event_id'       => $t->event->id,
        'client_id'      => $client->id,
        'event_tier_id'  => $t->tier->id,
        'status'         => 'pending',
        'payment_status' => 'pending',
        'amount'         => 250,
        'amount_paid'    => 0,
    ], $overrides));

    TicketPayment::create([
        'ticket_id'    => $ticket->id,
        'amount'       => 250,
        'status'       => 'pending',
        'payment_date' => now(),
        'payment_type' => 'full',
    ]);

    return $ticket;
}

it('activates an organizer-direct payment without creating a settlement item', function () {
    $ticket = pendingTicket($this);

    $activated = app(TicketActivationService::class)->activate(
        $ticket, TicketActivationService::SOURCE_ORGANIZER_DIRECT, 'ecocash', 'ABC123', null,
    );

    $ticket->refresh();
    expect($activated)->toBeTrue()
        ->and($ticket->status)->toBe('active')
        ->and($ticket->payment_status)->toBe('completed')
        ->and((float) $ticket->amount_paid)->toBe(250.0)
        ->and($ticket->payments()->approved()->count())->toBe(1)
        ->and($ticket->payments()->pending()->count())->toBe(0)
        ->and($this->tier->fresh()->quantity_sold)->toBe(1)
        ->and(SettlementItem::where('ticket_id', $ticket->id)->exists())->toBeFalse();
});

it('ignores a repeated online activation: one sale, one settlement item', function () {
    $ticket = pendingTicket($this);
    $service = app(TicketActivationService::class);
    $session = PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $ticket->id, 'gateway' => 'paylesotho',
        'client_reference' => 'ECOCASH' . $ticket->id . 'T0', 'payment_method' => 'ecocash',
        'amount' => 250, 'status' => 'completed', 'organization_id' => $this->org->id,
    ]);

    $first  = $service->activate($ticket, TicketActivationService::SOURCE_VENTIQ_ONLINE, 'ecocash', 'TX1', null, $session);
    $second = $service->activate($ticket->fresh(), TicketActivationService::SOURCE_VENTIQ_ONLINE, 'ecocash', 'TX1', null, $session);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and(SettlementItem::where('ticket_id', $ticket->id)->count())->toBe(1)
        ->and($this->tier->fresh()->quantity_sold)->toBe(1);

    // 4.9% of M250 = 12.25, + M7.50 flat
    $item = SettlementItem::where('ticket_id', $ticket->id)->first();
    expect((float) $item->gateway_fee)->toBe(19.75)
        ->and((float) $item->amount_owed_to_org)->toBe(230.25);
});

it('records an approved payment even when no pending row exists', function () {
    $ticket = pendingTicket($this);
    $ticket->payments()->delete();

    app(TicketActivationService::class)->activate($ticket, TicketActivationService::SOURCE_ORGANIZER_DIRECT, 'cash', 'R-1');

    expect($ticket->payments()->approved()->count())->toBe(1)
        ->and((float) $ticket->payments()->approved()->first()->amount)->toBe(250.0);
});

it('decides validity from the ticket status alone', function (string $status, string $payment, string $outcome) {
    $ticket = pendingTicket($this, ['status' => $status, 'payment_status' => $payment]);

    expect($ticket->scanOutcome())->toBe($outcome)
        ->and($ticket->isValid())->toBe($outcome === Ticket::SCAN_VALID)
        ->and($ticket->validateQrCode($ticket->qr_code))->toBe($outcome === Ticket::SCAN_VALID);
})->with([
    'active'               => ['active', 'completed', Ticket::SCAN_VALID],
    'active with deposit'  => ['active', 'partial', Ticket::SCAN_VALID],
    'pending'              => ['pending', 'pending', Ticket::SCAN_PAYMENT_NOT_CONFIRMED],
    'checked in'           => ['checked_in', 'completed', Ticket::SCAN_ALREADY_USED],
    'cancelled'            => ['cancelled', 'pending', Ticket::SCAN_CANCELLED],
    'expired'              => ['expired', 'pending', Ticket::SCAN_EXPIRED],
]);

it('flags a ticket from another event', function () {
    $ticket = pendingTicket($this, ['status' => 'active', 'payment_status' => 'completed']);

    expect($ticket->scanOutcome($this->event->id + 1))->toBe(Ticket::SCAN_WRONG_EVENT);
});

it('refuses to check in an inactive ticket sent by the scanner', function () {
    $user     = User::factory()->create(['organization_id' => $this->org->id]);
    $pending  = pendingTicket($this);
    $active   = pendingTicket($this, ['status' => 'active', 'payment_status' => 'completed']);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/scanner/checkin/bulk', [
        'checkins' => [
            ['ticket_id' => $pending->id, 'checked_in_at' => now()->toDateTimeString()],
            ['ticket_id' => $active->id, 'checked_in_at' => now()->toDateTimeString()],
        ],
    ]);

    $response->assertOk()->assertJsonPath('synced', 1);
    expect(collect($response->json('results'))->pluck('outcome', 'ticket_id')->all())->toBe([
        $pending->id => Ticket::SCAN_PAYMENT_NOT_CONFIRMED,
        $active->id  => Ticket::SCAN_VALID,
    ]);
    expect($pending->fresh()->checked_in_at)->toBeNull()
        ->and($active->fresh()->status)->toBe('checked_in');
});

it('returns the gate decision with the downloaded tickets', function () {
    $user = User::factory()->create(['organization_id' => $this->org->id]);
    pendingTicket($this);

    $this->actingAs($user, 'sanctum')->getJson("/api/scanner/tickets/{$this->event->id}")
        ->assertOk()
        ->assertJsonPath('tickets.0.is_scannable', false)
        ->assertJsonPath('tickets.0.scan_outcome', Ticket::SCAN_PAYMENT_NOT_CONFIRMED)
        ->assertJsonPath('event.date', $this->event->event_date->toJSON());
});

describe('PayLesotho callback', function () {
    beforeEach(function () {
        $this->ticket = pendingTicket($this);
        $this->session = PaymentSession::create([
            'payable_type'     => 'ticket',
            'payable_id'       => $this->ticket->id,
            'gateway'          => 'paylesotho',
            'client_reference' => 'ECOCASH' . $this->ticket->id . 'T1',
            'payment_method'   => 'ecocash',
            'amount'           => 250,
            'status'           => 'pending',
            'organization_id'  => $this->org->id,
        ]);
    });

    it('activates the ticket once, however often PayLesotho calls back', function () {
        $payload = ['client_reference' => $this->session->client_reference, 'status' => 'completed', 'amount' => '250'];

        $this->postJson('/payment/paylesotho/callback/ecocash', $payload)->assertOk();
        $this->postJson('/payment/paylesotho/callback/ecocash', $payload)->assertOk();

        expect($this->ticket->fresh()->status)->toBe('active')
            ->and(SettlementItem::where('ticket_id', $this->ticket->id)->count())->toBe(1);
    });

    it('does not reopen a completed session on a late failure callback', function () {
        $this->postJson('/payment/paylesotho/callback/ecocash', ['client_reference' => $this->session->client_reference, 'status' => 'completed']);
        $this->postJson('/payment/paylesotho/callback/ecocash', ['client_reference' => $this->session->client_reference, 'status' => 'failed']);

        expect($this->session->fresh()->status)->toBe('completed');
    });

    it('does not activate when the amount differs from what was charged', function () {
        $this->postJson('/payment/paylesotho/callback/ecocash', [
            'client_reference' => $this->session->client_reference, 'status' => 'completed', 'amount' => '1',
        ])->assertOk();

        expect($this->ticket->fresh()->status)->toBe('pending')
            ->and($this->session->fresh()->status)->toBe('pending');
    });

    it('rejects callbacks without the shared secret once one is configured', function () {
        config(['gateways.paylesotho.callback_secret' => 's3cret']);
        $payload = ['client_reference' => $this->session->client_reference, 'status' => 'completed'];

        $this->postJson('/payment/paylesotho/callback/ecocash?token=wrong', $payload)->assertForbidden();
        expect($this->ticket->fresh()->status)->toBe('pending');

        $this->postJson('/payment/paylesotho/callback/ecocash?token=s3cret', $payload)->assertOk();
        expect($this->ticket->fresh()->status)->toBe('active');
    });
});

describe('manual payment submission', function () {
    beforeEach(function () {
        $this->ecocash = OrganizationPaymentMethod::create([
            'organization_id' => $this->org->id,
            'payment_method'  => 'ecocash',
            'account_name'    => 'Events Account',
            'account_number'  => '62500000',
            'is_active'       => true,
        ]);
        $this->url = fn (Ticket $t) => "/ticket/{$t->qr_code}/pay/manual";
    });

    it('creates a new pending payment when resubmitting after a rejection', function () {
        $ticket = pendingTicket($this);
        $ticket->payments()->update(['status' => 'rejected']);

        $this->post(($this->url)($ticket), [
            'payment_method_id' => $this->ecocash->id,
            'payment_reference' => 'NEWREF',
        ]);

        $pending = $ticket->payments()->pending()->first();
        expect($pending)->not->toBeNull()
            ->and($pending->payment_reference)->toBe('NEWREF')
            ->and($pending->payment_method)->toBe('ecocash');
    });

    it('requires a reference for mobile money', function () {
        $ticket = pendingTicket($this);

        $this->post(($this->url)($ticket), ['payment_method_id' => $this->ecocash->id])
            ->assertSessionHasErrors('payment_reference');
    });

    it('keeps a claimed deposit pending until the organizer confirms it', function () {
        $this->event->update(['allow_installments' => true, 'minimum_deposit_percentage' => 30]);
        $ticket = pendingTicket($this);

        $this->post(($this->url)($ticket), [
            'payment_method_id' => $this->ecocash->id,
            'payment_reference' => 'DEP1',
            'payment_type'      => 'deposit',
            'deposit_amount'    => 100,
        ]);

        expect($ticket->fresh()->payment_status)->toBe('pending')
            ->and($ticket->payments()->pending()->first()->payment_type)->toBe('deposit');
    });
});
