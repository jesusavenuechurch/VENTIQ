<?php

use App\Models\{Client, Event, EventTier, Organization, PaymentSession, Settlement, Ticket, TicketFee, TicketPayment, User};
use App\Services\Payments\TicketActivationService;
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    config(['constants.fees.invoice_from' => now()->subDays(10)->toDateString()]);

    $this->super = User::factory()->create(['organization_id' => null]);
    $this->super->assignRole('super_admin');
    $this->org = Organization::factory()->create(['name' => 'Maseru Youth Network']);

    $this->makeEvent = fn (array $attrs = []) => tap(Event::create(array_merge([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid',
    ], $attrs)), fn ($e) => EventTier::create(['event_id' => $e->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]));

    $this->sell = function (Event $event, string $source) {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Guest ' . Str::random(3), 'phone' => '+2665' . random_int(1000000, 9999999)]);
        $ticket = Ticket::create(['event_id' => $event->id, 'client_id' => $client->id, 'event_tier_id' => $event->tiers()->first()->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200]);
        TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => 200, 'status' => 'pending', 'payment_type' => 'full']);
        $session = $source === 'online' ? PaymentSession::create([
            'payable_type' => 'ticket', 'payable_id' => $ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'R' . Str::random(8),
            'payment_method' => 'ecocash', 'amount' => 200, 'status' => 'completed', 'organization_id' => $this->org->id,
        ]) : null;
        app(TicketActivationService::class)->activate($ticket, $source === 'online' ? TicketActivationService::SOURCE_VENTIQ_ONLINE : TicketActivationService::SOURCE_ORGANIZER_DIRECT, 'ecocash', 'TX', null, $session);

        return $ticket;
    };
});

it('is for super admins only', function () {
    $this->get(route('ventiq.money.index'))->assertRedirect(route('login'));

    $admin = User::factory()->create(['organization_id' => $this->org->id]);
    $admin->assignRole('org_admin');
    $this->actingAs($admin)->get(route('ventiq.money.index'))->assertForbidden();
});

it('shows what is owed to organizers and pays it out in a batch', function () {
    $event = ($this->makeEvent)();
    ($this->sell)($event, 'online');
    ($this->sell)($event, 'online');
    $owed = 2 * round(200 - (200 * 0.049 + 7.5), 2);

    $this->actingAs($this->super)->get(route('ventiq.money.index'))
        ->assertOk()->assertSee('Maseru Youth Network')->assertSee('M' . number_format($owed, 2));

    $this->post(route('ventiq.money.payouts.create', $this->org))->assertSessionHas('status', fn ($s) => str_contains($s, 'ready for Maseru Youth Network'));
    $batch = Settlement::sole();

    $this->post(route('ventiq.money.payouts.paid', $batch), ['method' => 'ecocash', 'reference' => 'EC-PAYOUT-1'])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'recorded'));
    expect($batch->fresh()->status)->toBe('settled')
        ->and((float) $batch->fresh()->amount_owed_to_org)->toBe($owed);
});

it('lists fees to invoice, skipping past events and sponsored fees, and tracks the invoice', function () {
    $event = ($this->makeEvent)();
    $sponsored = ($this->makeEvent)(['fees_sponsored' => true]);
    $past = ($this->makeEvent)(['event_date' => now()->subMonths(2)]);
    $a = ($this->sell)($event, 'direct');
    ($this->sell)($sponsored, 'direct');
    ($this->sell)($past, 'direct');
    ($this->sell)($event, 'online');   // its fee comes off the payout instead

    $fee = round(200 * 0.049 + 7.5, 2);
    $page = $this->actingAs($this->super)->get(route('ventiq.money.index'))->assertOk();
    $page->assertSee('1 ticket')->assertSee('M' . number_format($fee, 2));

    preg_match('/name="up_to" value="(\d+)"/', $page->getContent(), $m);
    $csv = $this->get(route('ventiq.money.fees.csv', [$this->org, 'up_to' => $m[1]]))->assertOk();
    expect($csv->getContent())->toContain($a->ticket_number)->toContain(number_format($fee, 2, '.', ''));

    // A fee recorded after the list was opened waits for the next invoice.
    ($this->sell)($event, 'direct');
    $this->post(route('ventiq.money.fees.invoiced', $this->org), ['up_to' => $m[1], 'reference' => 'INV-001'])
        ->assertSessionHas('status', fn ($s) => str_starts_with($s, '1 fee on invoice INV-001'));
    expect(TicketFee::where('invoice_reference', 'INV-001')->count())->toBe(1)
        ->and(TicketFee::whereNull('invoiced_at')->where('collection', 'invoice')->where('sponsored', false)->whereHas('event', fn ($q) => $q->where('event_date', '>', now()))->count())->toBe(1);

    $this->post(route('ventiq.money.fees.paid', $this->org), ['reference' => 'INV-001']);
    expect(TicketFee::where('invoice_reference', 'INV-001')->value('invoice_paid_at'))->not->toBeNull();
});
