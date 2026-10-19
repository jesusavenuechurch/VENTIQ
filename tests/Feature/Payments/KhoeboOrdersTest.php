<?php

use App\Models\{Event, EventTier, KhoeboProduct, Organization};
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['services.khoebo.url' => 'https://khoebo.test/api/v1', 'services.khoebo.token' => 'itk_test', 'services.khoebo.payment_term_id' => 3]);
    KhoeboProduct::create(['key' => 'fee-person', 'khoebo_id' => 7]);
    KhoeboProduct::create(['key' => 'fee-sales', 'khoebo_id' => 8]);
    $this->org = Organization::factory()->create(['khoebo_customer_id' => 2]);
    $this->event = fn (string $mode) => Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => $mode, 'is_public' => true,
    ]);
    Http::fake(['khoebo.test/*' => Http::response(['data' => ['id' => 11, 'reference' => 'QT-00011', 'status' => 'draft', 'totals' => ['grand_total' => '2370.00']]], 201)]);
});

it("orders an event's possible fees from its ticket numbers", function () {
    $event = ($this->event)('paid');
    EventTier::create(['event_id' => $event->id, 'tier_name' => 'Standard', 'price' => 200, 'quantity_available' => 100, 'is_active' => true]);
    EventTier::create(['event_id' => $event->id, 'tier_name' => 'VIP', 'price' => 500, 'quantity_available' => 20, 'is_active' => true]);

    $this->artisan('khoebo:order', ['event' => $event->id])->expectsOutputToContain('QT-00011')->assertSuccessful();

    Http::assertSent(fn (Request $r) => $r->url() === 'https://khoebo.test/api/v1/orders'
        && $r->hasHeader('Idempotency-Key', "ventiq-order-event-{$event->id}")
        && $r['customer_id'] === 2 && $r['payment_term_id'] === 3
        && $r['external_reference'] === "ventiq-event-{$event->id}"
        && $r['lines'][0]['product_id'] === 7 && $r['lines'][0]['quantity'] === '120' && $r['lines'][0]['unit_price'] === '7.50'
        && $r['lines'][1]['product_id'] === 8 && $r['lines'][1]['quantity'] === '1' && $r['lines'][1]['unit_price'] === '1470.00');
    expect($event->fresh()->khoebo_order_reference)->toBe('QT-00011');

    // Once per event.
    $this->artisan('khoebo:order', ['event' => $event->id])->assertFailed();
    Http::assertSentCount(1);
});

it('orders only the per-person fee for a free event', function () {
    $event = ($this->event)('free');
    EventTier::create(['event_id' => $event->id, 'tier_name' => 'Free', 'price' => 0, 'quantity_available' => 80, 'is_active' => true]);

    $this->artisan('khoebo:order', ['event' => $event->id])->assertSuccessful();

    Http::assertSent(fn (Request $r) => count($r['lines']) === 1 && $r['lines'][0]['quantity'] === '80');
});

it('orders nothing for a sponsored event', function () {
    $event = ($this->event)('paid');
    $event->forceFill(['fees_sponsored' => true])->save();
    EventTier::create(['event_id' => $event->id, 'tier_name' => 'Standard', 'price' => 200, 'quantity_available' => 10, 'is_active' => true]);

    $this->artisan('khoebo:order', ['event' => $event->id])->assertFailed();
    Http::assertNothingSent();
});

it('sends the order when an organizer publishes an event', function () {
    $event = app(\App\Services\Events\EventService::class)->create($this->org, [
        'name' => 'Launch', 'category' => 'business', 'city' => 'Maseru', 'payment_mode' => 'paid', 'status' => 'published',
        'event_date' => now()->addMonth()->toDateString(), 'event_time' => '18:00', 'venue' => 'Hall',
        'tiers' => [['tier_name' => 'Standard', 'price' => 200, 'quantity_available' => 100, 'is_active' => true]],
    ]);
    app()->terminate();   // after-response jobs run here

    Http::assertSent(fn (Request $r) => $r['external_reference'] === "ventiq-event-{$event->id}" && $r['lines'][0]['quantity'] === '100');
    expect($event->fresh()->khoebo_order_id)->toBe(11);
});

it('sends nothing for a draft, and orders it once published', function () {
    $event = ($this->event)('paid');
    $event->update(['status' => 'draft']);
    EventTier::create(['event_id' => $event->id, 'tier_name' => 'Standard', 'price' => 200, 'quantity_available' => 10, 'is_active' => true]);
    Http::assertNothingSent();

    $event->update(['status' => 'published']);
    app()->terminate();
    Http::assertSentCount(1);
});

it('catches up on published events whose order failed', function () {
    $event = ($this->event)('paid');   // published before its tiers: nothing to order then
    app()->terminate();
    Http::assertNothingSent();
    EventTier::create(['event_id' => $event->id, 'tier_name' => 'Standard', 'price' => 200, 'quantity_available' => 10, 'is_active' => true]);

    $this->artisan('khoebo:orders')->assertSuccessful();

    Http::assertSentCount(1);
    expect($event->fresh()->khoebo_order_id)->toBe(11);
});
