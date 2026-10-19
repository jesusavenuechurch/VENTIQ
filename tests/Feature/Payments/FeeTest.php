<?php

use App\Models\{Client, Event, EventTier, Organization, PaymentSession, Settlement, SettlementItem, Ticket, TicketFee, TicketPayment, User};
use App\Services\Fees\FeeService;
use App\Services\Payments\TicketActivationService;
use App\Services\Reports\EventFinance;
use Illuminate\Support\Facades\{Bus, Mail, Notification};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->single = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 250, 'is_active' => true]);
    $this->table = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Table of 3', 'price' => 750, 'quantity_per_purchase' => 3, 'is_active' => true]);
    $this->activation = app(TicketActivationService::class);
});

function feeTicket(object $t, EventTier $tier, array $attrs = []): Ticket
{
    $client = Client::create(['organization_id' => $t->org->id, 'full_name' => 'Guest', 'phone' => '+2665' . random_int(1000000, 9999999)]);
    $ticket = Ticket::create(array_merge([
        'event_id' => $t->event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id,
        'status' => 'pending', 'payment_status' => 'pending', 'amount' => $tier->price,
        'admissions' => $tier->quantity_per_purchase ?? 1,
    ], $attrs));
    TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => $ticket->amount, 'status' => 'pending', 'payment_type' => 'full']);

    return $ticket;
}

function payOnline(object $t, Ticket $ticket): void
{
    $session = PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'R' . $ticket->id,
        'payment_method' => 'ecocash', 'amount' => $ticket->amount, 'status' => 'completed', 'organization_id' => $t->org->id,
    ]);
    $t->activation->activate($ticket, TicketActivationService::SOURCE_VENTIQ_ONLINE, 'ecocash', 'TX', null, $session);
}

it('takes the service and operational fee from the payout on an online ticket', function () {
    $ticket = feeTicket($this, $this->single);
    payOnline($this, $ticket);

    $fee = TicketFee::where('ticket_id', $ticket->id)->firstOrFail();
    expect($fee->source)->toBe('ventiq_online')
        ->and((float) $fee->service_fee)->toBe(12.25)       // 4.9% of 250
        ->and((float) $fee->operational_fee)->toBe(7.5)     // 1 person
        ->and((float) $fee->total_fee)->toBe(19.75)
        ->and($fee->collection)->toBe(TicketFee::COLLECT_FROM_PAYOUT);

    $item = SettlementItem::where('ticket_id', $ticket->id)->firstOrFail();
    expect((float) $item->gateway_fee)->toBe(19.75)
        ->and((float) $item->amount_owed_to_org)->toBe(230.25);
});

it('charges the operational fee per person on a group ticket', function () {
    $ticket = feeTicket($this, $this->table);
    payOnline($this, $ticket);

    $fee = TicketFee::where('ticket_id', $ticket->id)->firstOrFail();
    expect((float) $fee->service_fee)->toBe(36.75)        // 4.9% of 750
        ->and((float) $fee->operational_fee)->toBe(22.5)  // 3 × 7.50
        ->and($fee->people)->toBe(3)
        ->and((float) SettlementItem::where('ticket_id', $ticket->id)->value('amount_owed_to_org'))->toBe(690.75);
});

it('invoices fees on tickets paid directly to the organizer', function () {
    $ticket = feeTicket($this, $this->single);
    $this->activation->activate($ticket, TicketActivationService::SOURCE_ORGANIZER_DIRECT, 'cash', 'R1');

    $fee = TicketFee::where('ticket_id', $ticket->id)->firstOrFail();
    expect($fee->source)->toBe('organizer_direct')
        ->and($fee->collection)->toBe(TicketFee::COLLECT_BY_INVOICE)
        ->and((float) $fee->total_fee)->toBe(19.75)
        ->and(SettlementItem::where('ticket_id', $ticket->id)->exists())->toBeFalse()
        ->and(EventFinance::for($this->event)->fees()['fees_to_invoice'])->toBe(19.75);
});

it('charges free and complimentary tickets the operational fee only', function () {
    $freeTier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Free', 'price' => 0, 'is_active' => true]);
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'F', 'phone' => '+26650003333']);
    $free = Ticket::create(['event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $freeTier->id, 'status' => 'active', 'payment_status' => 'completed', 'amount' => 0]);

    $comp = Ticket::create(['event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $this->table->id, 'is_complimentary' => true, 'amount' => 0, 'admissions' => 3]);
    $comp->markAsComplimentary(1, 'Speaker');

    $freeFee = TicketFee::where('ticket_id', $free->id)->firstOrFail();
    $compFee = TicketFee::where('ticket_id', $comp->id)->firstOrFail();

    expect($freeFee->source)->toBe('free')
        ->and((float) $freeFee->service_fee)->toBe(0.0)
        ->and((float) $freeFee->total_fee)->toBe(7.5)
        ->and($compFee->source)->toBe('complimentary')
        ->and((float) $compFee->service_fee)->toBe(0.0)
        ->and((float) $compFee->total_fee)->toBe(22.5)
        ->and($compFee->collection)->toBe(TicketFee::COLLECT_BY_INVOICE);
});

it('charges nothing until a ticket is active, and only once', function () {
    $pending = feeTicket($this, $this->single);
    feeTicket($this, $this->single, ['status' => 'expired']);
    expect(TicketFee::count())->toBe(0);

    payOnline($this, $pending);
    payOnline($this, $pending->fresh());
    $pending->fresh()->admit();

    expect(TicketFee::where('ticket_id', $pending->id)->count())->toBe(1);
});

it('records sponsored fees without charging them, and only until settled or invoiced', function () {
    $settled = feeTicket($this, $this->single);
    payOnline($this, $settled);
    $settlement = Settlement::create(['organization_id' => $this->org->id, 'status' => 'settled']);
    SettlementItem::where('ticket_id', $settled->id)->update(['settlement_id' => $settlement->id]);

    $unsettled = feeTicket($this, $this->single);
    payOnline($this, $unsettled);
    $direct = feeTicket($this, $this->single);
    $this->activation->activate($direct, TicketActivationService::SOURCE_ORGANIZER_DIRECT);

    $super = User::factory()->create(['organization_id' => null]);
    $changed = app(FeeService::class)->setSponsored($this->event, true, $super);

    expect($changed)->toBe(2)
        ->and(TicketFee::where('ticket_id', $settled->id)->value('sponsored'))->toBeFalsy()
        ->and(TicketFee::where('ticket_id', $unsettled->id)->value('sponsored'))->toBeTruthy()
        ->and((float) SettlementItem::where('ticket_id', $unsettled->id)->value('gateway_fee'))->toBe(0.0)
        ->and((float) SettlementItem::where('ticket_id', $unsettled->id)->value('amount_owed_to_org'))->toBe(250.0)
        ->and($this->event->fresh()->fees_sponsored_by)->toBe($super->id);

    $new = feeTicket($this, $this->single);
    $this->activation->activate($new, TicketActivationService::SOURCE_ORGANIZER_DIRECT);
    expect(TicketFee::where('ticket_id', $new->id)->value('sponsored'))->toBeTruthy();

    $fees = EventFinance::for($this->event)->fees();
    expect($fees['fees_total'])->toBe(79.0)        // 4 tickets × 19.75, all recorded
        ->and($fees['fees_sponsored'])->toBe(59.25)  // 3 of them sponsored
        ->and($fees['fees_charged'])->toBe(19.75)    // the one already settled
        ->and($fees['fees_to_invoice'])->toBe(0.0);

    app(FeeService::class)->setSponsored($this->event->fresh(), false, $super);
    expect((float) SettlementItem::where('ticket_id', $unsettled->id)->value('gateway_fee'))->toBe(19.75)
        ->and(EventFinance::for($this->event)->fees()['fees_to_invoice'])->toBe(39.5);
});

it('lets only super admins sponsor from the organizer area, and shows the fees', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $ticket = feeTicket($this, $this->single);
    $this->activation->activate($ticket, TicketActivationService::SOURCE_ORGANIZER_DIRECT);

    $admin = User::factory()->create(['organization_id' => $this->org->id]);
    $admin->assignRole('org_admin');
    $this->actingAs($admin)->get(route('organizer.events.attendees', $this->event))
        ->assertOk()->assertSee('VENTIQ fees')->assertSee('M19.75')->assertDontSee('Sponsor fees');
    $this->post(route('organizer.events.fee-sponsorship', $this->event))->assertForbidden();

    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $this->actingAs($super)->get(route('organizer.act-as.start', $this->org));
    $this->get(route('organizer.events.attendees', $this->event))->assertSee('Sponsor fees');
    $this->post(route('organizer.events.fee-sponsorship', $this->event))->assertSessionHas('status');

    expect($this->event->fresh()->fees_sponsored)->toBeTrue();
    $this->get(route('organizer.events.attendees', $this->event))->assertSee('Sponsored by VENTIQ');
});
