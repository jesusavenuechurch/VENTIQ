<?php

use App\Models\{Event, EventTier, Organization, PaymentSession, Ticket, TicketFee};
use App\Services\Payments\TicketActivationService;
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    config(['ventiq.group_purchase' => true, 'constants.fees.service_percent' => 0.049, 'constants.fees.operational_per_person' => 7.5]);
    $this->org = Organization::factory()->create();
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Finite Awards', 'slug' => 'f-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 150, 'quantity_available' => 10, 'is_active' => true]);
    $this->register = fn (array $data = []) => $this->post("/register/{$this->org->slug}/{$this->event->slug}", array_merge([
        'tier_id' => $this->tier->id, 'full_name' => 'Lerato Molapo', 'phone' => '59494756', 'terms' => '1',
    ], $data));
});

it('sells a ticket each to several people on one payment', function () {
    ($this->register)(['others' => ['Thabo Molapo', 'Mpho Molapo']]);

    [$lead, $thabo, $mpho] = Ticket::orderBy('id')->get();
    expect((float) $lead->amount)->toBe(450.0)
        ->and($thabo->holder_name)->toBe('Thabo Molapo')->and($thabo->group_ticket_id)->toBe($lead->id)->and((float) $thabo->amount)->toBe(0.0)
        ->and($mpho->status)->toBe('pending')
        ->and($thabo->client_id)->toBe($lead->client_id);

    // Their pay link is the group's.
    $this->get(route('ticket.pay', $thabo->qr_code))->assertRedirect(route('ticket.pay', $lead->qr_code));

    // Paid online: everyone's ticket is on, the fee covers three people, once.
    $session = PaymentSession::create(['payable_type' => 'ticket', 'payable_id' => $lead->id, 'gateway' => 'paylesotho', 'status' => 'completed', 'amount' => 450, 'payment_method' => 'ecocash', 'organization_id' => $this->org->id, 'client_reference' => 'T1']);
    app(TicketActivationService::class)->activate($lead, TicketActivationService::SOURCE_VENTIQ_ONLINE, 'ecocash', 'MP1', null, $session);

    expect(Ticket::where('status', 'active')->count())->toBe(3)
        ->and(Ticket::where('payment_status', 'completed')->count())->toBe(3)
        ->and(TicketFee::count())->toBe(1)
        ->and(TicketFee::sole())->people->toBe(3)
        ->and((float) TicketFee::sole()->total_fee)->toBe(round(450 * 0.049 + 3 * 7.5, 2))
        ->and($this->tier->fresh()->quantity_sold)->toBe(3);
});

it('lets the group go when its payment window ends', function () {
    ($this->register)(['others' => ['Thabo Molapo']]);
    $lead = Ticket::orderBy('id')->first();
    $lead->update(['status' => 'expired']);

    expect(Ticket::where('status', 'expired')->count())->toBe(2);
});

it('needs a place for everyone', function () {
    $this->tier->update(['quantity_available' => 2]);
    ($this->register)(['others' => ['Thabo Molapo', 'Mpho Molapo']])->assertSessionHas('error', "Sorry, there aren't 3 General tickets left.");
    expect(Ticket::count())->toBe(0);
});

it('makes free tickets for everyone straight away, with the fee for each person', function () {
    $this->tier->update(['price' => 0]);
    ($this->register)(['others' => ['Thabo Molapo']]);

    expect(Ticket::where('status', 'active')->count())->toBe(2)
        ->and(TicketFee::count())->toBe(1)
        ->and(TicketFee::sole()->people)->toBe(2);
});

it('takes only the buyer while it is switched off', function () {
    config(['ventiq.group_purchase' => false]);
    ($this->register)(['others' => ['Thabo Molapo']]);

    expect(Ticket::count())->toBe(1)->and((float) Ticket::sole()->amount)->toBe(150.0);
    $this->get(route('registration.form', [$this->org->slug, $this->event->slug]))->assertInertia(fn ($page) => $page->where('groupMax', 1));
});

it("lists the others on the buyer's ticket, with their links, and only who bought it on theirs", function () {
    $this->tier->update(['price' => 0]);
    ($this->register)(['others' => ['Thabo Molapo', 'Mpho Molapo']]);
    [$lead, $thabo] = Ticket::orderBy('id')->get();

    $this->get(route('ticket.download', $lead->qr_code))->assertInertia(fn ($page) => $page->component('Tickets/Pass')
        ->where('together.count', 3)
        ->where('together.others.0.name', 'Thabo Molapo')
        ->where('together.others.0.link', route('ticket.download', $thabo->qr_code)));

    $this->get(route('ticket.download', $thabo->qr_code))->assertInertia(fn ($page) => $page
        ->where('together.buyer', 'Lerato Molapo')->where('together.others', null));
});
