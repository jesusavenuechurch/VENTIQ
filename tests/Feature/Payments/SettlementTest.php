<?php

use App\Models\{Client, Event, EventTier, Organization, PaymentSession, Settlement, SettlementItem, Ticket, TicketPayment, User};
use App\Services\Payments\{SettlementService, TicketActivationService};
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->org = Organization::factory()->create();
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid',
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]);
    $this->settlements = app(SettlementService::class);
    $this->super = User::factory()->create(['organization_id' => null]);

    $this->paidOnline = function () {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Guest', 'phone' => '+2665' . random_int(1000000, 9999999)]);
        $ticket = Ticket::create(['event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $this->tier->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200]);
        TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => 200, 'status' => 'pending', 'payment_type' => 'full']);
        $session = PaymentSession::create([
            'payable_type' => 'ticket', 'payable_id' => $ticket->id, 'gateway' => 'paylesotho', 'client_reference' => 'R' . Str::random(8),
            'payment_method' => 'ecocash', 'amount' => 200, 'status' => 'completed', 'organization_id' => $this->org->id,
        ]);
        app(TicketActivationService::class)->activate($ticket, TicketActivationService::SOURCE_VENTIQ_ONLINE, 'ecocash', 'TX', null, $session);

        return $ticket;
    };
});

it('batches the organizer\'s payout net of VENTIQ fees, from the existing lines', function () {
    ($this->paidOnline)();
    ($this->paidOnline)();
    $fee = round(200 * 0.049 + 7.5, 2);   // 17.30

    $batch = $this->settlements->createBatch($this->org->id, 'manual', $this->super);

    expect($batch)->not->toBeNull()
        ->and((float) $batch->amount_received)->toBe(400.0)
        ->and((float) $batch->ventiq_revenue)->toBe(round(2 * $fee, 2))
        ->and((float) $batch->amount_owed_to_org)->toBe(round(400 - 2 * $fee, 2))
        ->and(SettlementItem::count())->toBe(2)                  // no new lines made
        ->and(SettlementItem::where('settlement_id', $batch->id)->count())->toBe(2);

    // Nothing left to batch, so nothing can be paid twice.
    expect($this->settlements->createBatch($this->org->id))->toBeNull();
});

it('leaves lines for cancelled tickets out of the payout', function () {
    ($this->paidOnline)();
    ($this->paidOnline)()->update(['status' => 'cancelled']);

    $batch = $this->settlements->createBatch($this->org->id);

    expect($batch->items()->count())->toBe(1)
        ->and(SettlementItem::whereNull('settlement_id')->count())->toBe(1);
});

it('marks a payout paid once, at the lines\' total', function () {
    ($this->paidOnline)();
    $batch = $this->settlements->createBatch($this->org->id);
    $batch->update(['amount_owed_to_org' => 9999]);   // a wrong header figure is corrected from the lines

    $paid = $this->settlements->markPaid($batch, 'ecocash', 'PAYOUT-1', null, $this->super);

    expect($paid->status)->toBe('settled')
        ->and((float) $paid->amount_owed_to_org)->toBe(round(200 - (200 * 0.049 + 7.5), 2))
        ->and($paid->settled_by)->toBe($this->super->id);

    expect(fn () => $this->settlements->markPaid($paid, 'ecocash', 'PAYOUT-2', null, $this->super))
        ->toThrow(InvalidArgumentException::class);
});

it('shows the breakdown of a batch', function () {
    ($this->paidOnline)();
    $batch = $this->settlements->createBatch($this->org->id);

    $html = view('filament.modals.settlement-items', ['settlement' => $batch])->render();
    expect($html)->toContain('Guest')->toContain('M182.70');
});
