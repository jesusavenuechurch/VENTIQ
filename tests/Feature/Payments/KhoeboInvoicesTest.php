<?php

use App\Models\{Client, Event, EventTier, KhoeboProduct, Organization, Ticket, TicketFee};
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\{Bus, Http, Mail, Notification};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake(); Mail::fake(); Notification::fake();
    config(['services.khoebo.url' => 'https://khoebo.test/api/v1', 'services.khoebo.token' => 'itk_test',
            'services.khoebo.orders_from' => '2026-01-01', 'constants.fees.invoice_from' => '2026-01-01']);
    KhoeboProduct::create(['key' => 'fee-person', 'khoebo_id' => 7]);
    KhoeboProduct::create(['key' => 'fee-sales', 'khoebo_id' => 8]);
    $this->org = Organization::factory()->create(['khoebo_customer_id' => 2]);
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->subDays(2), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->event->forceFill(['khoebo_order_reference' => 'QT-00011'])->saveQuietly();
    $tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Standard', 'price' => 200, 'quantity_available' => 100, 'is_active' => true]);

    // A ticket with its fee, as the fee ledger records it.
    $this->fee = function (string $collection, int $people, float $service) use ($tier) {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Guest', 'phone' => '+2665' . random_int(1000000, 9999999)]);
        $ticket = Ticket::create(['event_id' => $this->event->id, 'event_tier_id' => $tier->id, 'client_id' => $client->id,
            'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200]);
        Ticket::whereKey($ticket->id)->update(['status' => 'active']);
        $op = $people * 7.5;
        return TicketFee::create(['ticket_id' => $ticket->id, 'event_id' => $this->event->id, 'organization_id' => $this->org->id,
            'source' => 'organizer_direct', 'ticket_amount' => 200, 'people' => $people, 'service_fee' => $service,
            'operational_fee' => $op, 'total_fee' => $op + $service, 'sponsored' => false, 'collection' => $collection]);
    };
    $this->fakeKhoebo = fn () => Http::fake(['khoebo.test/*' => Http::response(['data' => ['id' => 21, 'reference' => 'INV-00021', 'status' => 'draft']], 201)]);
});

it('invoices what the organizer actually owes, the day after the event', function () {
    ($this->fakeKhoebo)();
    $direct = ($this->fee)(TicketFee::COLLECT_BY_INVOICE, 1, 9.80);
    ($this->fee)(TicketFee::COLLECT_BY_INVOICE, 4, 39.20);
    $online = ($this->fee)(TicketFee::COLLECT_FROM_PAYOUT, 1, 9.80);   // came off the payout

    $this->artisan('khoebo:invoice')->expectsOutputToContain('INV-00021')->assertSuccessful();

    Http::assertSent(fn (Request $r) => $r->url() === 'https://khoebo.test/api/v1/invoices'
        && $r->hasHeader('Idempotency-Key', "ventiq-invoice-event-{$this->event->id}")
        && $r['customer_id'] === 2
        && $r['external_reference'] === "ventiq-invoice-event-{$this->event->id}"
        && $r['lines'][0]['product_id'] === 7 && $r['lines'][0]['quantity'] === 5 && $r['lines'][0]['unit_price'] === '7.50'
        && str_contains($r['lines'][0]['description'], 'QT-00011')
        && $r['lines'][1]['product_id'] === 8 && $r['lines'][1]['unit_price'] === '49.00');

    expect($this->event->fresh()->khoebo_invoice_reference)->toBe('INV-00021')
        ->and($direct->fresh()->invoice_reference)->toBe('INV-00021')
        ->and($online->fresh()->invoiced_at)->toBeNull();

    // Once per event.
    $this->artisan('khoebo:invoice')->assertSuccessful();
    Http::assertSentCount(1);
});

it('waits for the event to finish, and skips sponsored events', function () {
    ($this->fakeKhoebo)();
    ($this->fee)(TicketFee::COLLECT_BY_INVOICE, 1, 9.80);
    $this->event->update(['event_date' => now()]);
    $this->artisan('khoebo:invoice')->assertSuccessful();
    Http::assertNothingSent();

    $this->event->update(['event_date' => now()->subDays(2)]);
    $this->event->forceFill(['fees_sponsored' => true])->save();
    $this->artisan('khoebo:invoice', ['event' => $this->event->id])->assertFailed();
    Http::assertNothingSent();
});

describe('paying ahead', function () {
    beforeEach(function () {
        config(['services.khoebo.journals.default' => 2]);
        $this->event->update(['event_date' => now()->addWeek()]);
        $this->event->tiers()->first()->update(['quantity_available' => 50]);   // 50 × M7.50 + 4.9% × M10,000 = M865
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $super = \App\Models\User::factory()->create(['organization_id' => null]);
        $super->assignRole('super_admin');
        $this->actingAs($super)->get(route('organizer.act-as.start', $this->org));
        $this->invoiceIds = [31, 32];
        Http::fake(function (Request $r) {
            if (str_ends_with($r->url(), '/pay')) {
                return Http::response(['data' => ['id' => 5, 'type' => 'customer_payment', 'status' => 'draft']], 201);
            }
            $id = array_shift($this->invoiceIds);
            return Http::response(['data' => ['id' => $id, 'reference' => "INV-000{$id}", 'status' => 'draft']], 201);
        });
    });

    it('invoices the order now, records the payment, and bills only attendance beyond it', function () {
        $this->get(route('organizer.events.attendees', $this->event))->assertSee('Invoice now (paying ahead)');
        $this->post(route('organizer.events.khoebo.invoice-now', $this->event))->assertSessionHas('status', fn ($s) => str_contains($s, 'INV-00031'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices') && $r['lines'][0]['quantity'] === 50
            && $r['lines'][1]['unit_price'] === '490.00');
        expect((float) $this->event->fresh()->khoebo_invoice_total)->toBe(865.0);

        $this->post(route('organizer.events.khoebo.payment', $this->event), ['amount' => '865.00', 'date' => now()->toDateString(), 'method' => 'bank_transfer', 'reference' => 'BANK-77'])
            ->assertSessionHas('status', 'M865.00 recorded in Khoebo.');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/invoices/31/pay') && $r['journal_id'] === 2
            && $r['amount'] === '865.00' && $r['external_reference'] === 'BANK-77');

        // 55 came: the 5 extra are billed after the event.
        $this->event->update(['event_date' => now()->subDays(2)]);
        $fee = ($this->fee)(TicketFee::COLLECT_BY_INVOICE, 55, 539.00);
        $this->artisan('khoebo:invoice', ['event' => $this->event->id])->expectsOutputToContain('balance invoice INV-00032 for M86.50')->assertSuccessful();
        expect($fee->fresh()->invoice_reference)->toBe('INV-00032');
    });

    it('needs nothing more when fewer came than were prepaid', function () {
        $this->post(route('organizer.events.khoebo.invoice-now', $this->event));
        $this->post(route('organizer.events.khoebo.payment', $this->event), ['amount' => '865.00', 'date' => now()->toDateString(), 'method' => 'cash']);

        $this->event->update(['event_date' => now()->subDays(2)]);
        $fee = ($this->fee)(TicketFee::COLLECT_BY_INVOICE, 40, 392.00);
        $this->artisan('khoebo:invoice')->assertSuccessful();

        expect($fee->fresh()->invoice_reference)->toBe('INV-00031')->and($fee->fresh()->invoice_paid_at)->not->toBeNull();
        Http::assertSentCount(2);   // the prepaid invoice and its payment
    });

    it("won't take more than is owed", function () {
        $this->post(route('organizer.events.khoebo.invoice-now', $this->event));
        $this->post(route('organizer.events.khoebo.payment', $this->event), ['amount' => '900', 'date' => now()->toDateString(), 'method' => 'cash'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'up to the M865.00'));
        expect((float) $this->event->fresh()->khoebo_paid_total)->toBe(0.0);
    });
});
