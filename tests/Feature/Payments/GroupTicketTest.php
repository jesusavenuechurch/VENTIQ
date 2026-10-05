<?php

use App\Models\{Client, Event, EventTier, Organization, OrganizationPaymentMethod, Ticket, TicketPayment, User};
use App\Services\Payments\TicketActivationService;
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::create([
        'organization_id' => $this->org->id,
        'name'            => 'Maseru Youth Summit',
        'slug'            => 'summit-' . Str::random(6),
        'event_date'      => now()->addMonth(),
        'status'          => 'published',
        'is_public'       => true,
    ]);
    $this->groupTier = EventTier::create([
        'event_id'              => $this->event->id,
        'tier_name'             => 'Group of 3',
        'price'                 => 750,
        'is_active'             => true,
        'quantity_per_purchase' => 3,
    ]);
    $this->user = User::factory()->create(['organization_id' => $this->org->id]);
});

function registerFor(object $t, EventTier $tier)
{
    return $t->post("/register/{$t->org->slug}/{$t->event->slug}", [
        'tier_id'   => $tier->id,
        'full_name' => 'Lerato Mokoena',
        'phone'     => '+26650001234',
        'terms'     => '1',
    ]);
}

function activeGroupTicket(object $t, int $admissions = 3): Ticket
{
    $client = Client::create(['organization_id' => $t->org->id, 'full_name' => 'Lerato Mokoena', 'phone' => '+2665' . random_int(1000000, 9999999)]);

    return Ticket::create([
        'event_id' => $t->event->id, 'client_id' => $client->id, 'event_tier_id' => $t->groupTier->id,
        'status' => 'active', 'payment_status' => 'completed', 'amount' => 750, 'admissions' => $admissions,
    ]);
}

function syncScans(object $t, Ticket $ticket, int $times)
{
    return $t->actingAs($t->user, 'sanctum')->postJson('/api/scanner/checkin/bulk', [
        'checkins' => collect(range(1, $times))->map(fn ($i) => [
            'ticket_id' => $ticket->id, 'checked_in_at' => now()->addMinutes($i)->toDateTimeString(),
        ])->all(),
    ]);
}

it('creates one ticket for a group purchase, priced for the whole group', function () {
    registerFor($this, $this->groupTier)->assertRedirect();

    $tickets = Ticket::where('event_id', $this->event->id)->get();
    expect($tickets)->toHaveCount(1);

    $ticket = $tickets->first();
    expect($ticket->admissions)->toBe(3)
        ->and((float) $ticket->amount)->toBe(750.0)
        ->and($ticket->status)->toBe('pending')
        ->and($ticket->payments()->pending()->count())->toBe(1)
        ->and((float) $ticket->payments()->first()->amount)->toBe(750.0);
});

it('holds an unpaid place for 48 hours by default, and not at all when switched off', function () {
    $this->travelTo(now()->startOfMinute());
    registerFor($this, $this->groupTier);
    expect(Ticket::first()->payment_due_at->equalTo(now()->addHours(48)))->toBeTrue();

    config(['ventiq.payment_window_hours' => null]);
    Ticket::query()->delete();
    $this->post("/register/{$this->org->slug}/{$this->event->slug}", ['tier_id' => $this->groupTier->id, 'full_name' => 'Thabo', 'phone' => '+26650009999', 'terms' => '1']);
    expect(Ticket::first()->payment_due_at)->toBeNull();
});

it('sets the payment deadline from the event window, capped at the event start', function () {
    $this->event->update(['payment_window_hours' => 48, 'event_date' => now()->addDay()]);

    registerFor($this, $this->groupTier);

    expect(Ticket::first()->payment_due_at->timestamp)->toBe($this->event->fresh()->event_date->timestamp);
});

it('falls back to the platform payment window', function () {
    config(['ventiq.payment_window_hours' => 24]);
    $this->travelTo(now()->startOfMinute());

    registerFor($this, $this->groupTier);

    expect(Ticket::first()->payment_due_at->equalTo(now()->addHours(24)))->toBeTrue();
});

it('records the account, source and time of a direct payment, and stops the clock', function () {
    $this->event->update(['payment_window_hours' => 48]);
    $main = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_name' => 'Main', 'account_number' => '58000000', 'is_active' => true]);
    $events = OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_name' => 'Events', 'account_number' => '62500000', 'is_active' => true, 'is_default' => true]);

    registerFor($this, $this->groupTier);
    $ticket = Ticket::first();
    expect($ticket->payment_due_at)->not->toBeNull();

    $this->post("/register/{$this->org->slug}/{$this->event->slug}/payment/{$ticket->id}/manual", [
        'payment_method_id' => $events->id,
        'payment_reference' => 'ABC123',
    ])->assertSessionHasNoErrors();

    $payment = $ticket->payments()->pending()->first();
    expect($payment->organization_payment_method_id)->toBe($events->id)
        ->and($payment->paymentAccount->account_name)->toBe('Events')
        ->and($payment->source)->toBe(TicketActivationService::SOURCE_ORGANIZER_DIRECT)
        ->and($payment->submitted_at)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(750.0)
        ->and($ticket->fresh()->payment_due_at)->toBeNull();
});

it('marks who collected the money when activating', function () {
    registerFor($this, $this->groupTier);
    $ticket = Ticket::first();

    app(TicketActivationService::class)->activate($ticket, TicketActivationService::SOURCE_ORGANIZER_DIRECT, 'cash', 'R1');

    expect($ticket->payments()->approved()->first()->source)->toBe(TicketActivationService::SOURCE_ORGANIZER_DIRECT)
        ->and($this->groupTier->fresh()->quantity_sold)->toBe(1);
});

it('admits a group one person per scan until its admissions run out', function () {
    $ticket = activeGroupTicket($this);

    syncScans($this, $ticket, 2)->assertJsonPath('synced', 2);
    $ticket->refresh();
    expect($ticket->admitted_count)->toBe(2)
        ->and($ticket->status)->toBe('active')
        ->and($ticket->scanOutcome())->toBe(Ticket::SCAN_VALID)
        ->and($ticket->checked_in_at)->not->toBeNull();

    $response = syncScans($this, $ticket, 2);
    $response->assertJsonPath('synced', 1)->assertJsonPath('errors', []);
    expect(collect($response->json('results'))->pluck('outcome')->all())
        ->toBe([Ticket::SCAN_VALID, Ticket::SCAN_ALREADY_USED]);

    $ticket->refresh();
    expect($ticket->admitted_count)->toBe(3)
        ->and($ticket->status)->toBe('checked_in')
        ->and($ticket->scanOutcome())->toBe(Ticket::SCAN_ALREADY_USED);
});

it('keeps a single ticket behaving as before: one scan uses it', function () {
    $ticket = activeGroupTicket($this, 1);

    syncScans($this, $ticket, 1)->assertJsonPath('synced', 1);
    syncScans($this, $ticket, 1)->assertJsonPath('synced', 0)->assertJsonPath('errors', []);

    expect($ticket->fresh()->status)->toBe('checked_in')
        ->and($ticket->fresh()->admitted_count)->toBe(1);
});

it('reports admissions with the downloaded tickets', function () {
    activeGroupTicket($this);

    $this->actingAs($this->user, 'sanctum')->getJson("/api/scanner/tickets/{$this->event->id}")
        ->assertJsonPath('tickets.0.admissions', 3)
        ->assertJsonPath('tickets.0.admitted_count', 0)
        ->assertJsonPath('tickets.0.is_scannable', true);
});

it('allows several accounts for the same payment method', function () {
    OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_name' => 'Events', 'account_number' => '62500000']);
    OrganizationPaymentMethod::create(['organization_id' => $this->org->id, 'payment_method' => 'ecocash', 'account_name' => 'Main', 'account_number' => '58000000']);

    expect(OrganizationPaymentMethod::where('organization_id', $this->org->id)->where('payment_method', 'ecocash')->count())->toBe(2);
});

it('offers only the online drivers that are switched on', function () {
    config(['gateways.paylesotho.mpesa.enabled' => false]);
    $ticket = activeGroupTicket($this);

    expect(\App\Services\Payments\PaymentGatewayFactory::enabledMethods())->toBe(['ecocash']);

    $this->postJson('/payment/paylesotho/ticket/initiate', [
        'ticket_id' => $ticket->id, 'method' => 'mpesa', 'mobile_number' => '+26650001234',
    ])->assertUnprocessable()->assertJsonValidationErrors('method');

    config(['gateways.paylesotho.enabled' => false]);
    expect(\App\Services\Payments\PaymentGatewayFactory::enabledMethods())->toBe([]);
});
