<?php

use App\Models\{Client, Event, EventTier, Organization, PaymentSession, Ticket, TicketPayment, User};
use App\Services\Payments\TicketActivationService;
use App\Services\Reports\{EventFinance, RegistrationSummaryService, RevenueReportService};
use Illuminate\Support\Facades\{Bus, Mail, Notification};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Notification::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');

    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Maseru Youth Summit', 'slug' => 'summit-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid',
    ]);
    $this->single = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 250, 'is_active' => true]);
    $this->group = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Table of 3', 'price' => 750, 'quantity_per_purchase' => 3, 'is_active' => true]);

    $service = app(TicketActivationService::class);
    $make = function (EventTier $tier, array $attrs = []) {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Guest ' . Str::random(4), 'phone' => '+2665' . random_int(1000000, 9999999)]);
        return Ticket::create(array_merge([
            'event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id,
            'status' => 'pending', 'payment_status' => 'pending', 'amount' => $tier->price,
            'admissions' => $tier->quantity_per_purchase ?? 1,
        ], $attrs));
    };
    $pending = fn (Ticket $t, array $attrs = []) => TicketPayment::create(array_merge([
        'ticket_id' => $t->id, 'amount' => $t->amount, 'status' => 'pending', 'payment_type' => 'full',
    ], $attrs));

    // Paid online through VENTIQ.
    $online = $make($this->single);
    $pending($online);
    $session = PaymentSession::create([
        'payable_type' => 'ticket', 'payable_id' => $online->id, 'gateway' => 'paylesotho', 'client_reference' => 'R' . $online->id,
        'payment_method' => 'ecocash', 'amount' => 250, 'status' => 'completed', 'organization_id' => $this->org->id,
    ]);
    $service->activate($online, TicketActivationService::SOURCE_VENTIQ_ONLINE, 'ecocash', 'TX1', null, $session);

    // Group of 3 paid directly to the organizer; 2 have arrived.
    $group = $make($this->group);
    $pending($group, ['submitted_at' => now(), 'payment_method' => 'ecocash']);
    $service->activate($group, TicketActivationService::SOURCE_ORGANIZER_DIRECT, confirmedBy: $this->admin->id);
    $group->fresh()->admit();
    $group->fresh()->admit();

    // Submitted, waiting for the organizer.
    $waiting = $make($this->single);
    $pending($waiting, ['submitted_at' => now(), 'payment_method' => 'mpesa']);

    // Expired: released, not a sale.
    $make($this->single, ['status' => 'expired']);

    // Marked paid by an old approval that never confirmed a payment row.
    $make($this->single, ['status' => 'active', 'payment_status' => 'completed', 'amount_paid' => 0]);

    // Complimentary.
    $make($this->single, ['status' => 'active', 'payment_status' => 'completed', 'amount' => 0, 'is_complimentary' => true]);
});

it('splits the money by who collected it and counts people', function () {
    $s = EventFinance::for($this->event)->summary();

    expect($s['collected_ventiq'])->toBe(250.0)
        ->and($s['collected_direct'])->toBe(750.0)
        ->and($s['unattributed'])->toBe(250.0)
        ->and($s['collected'])->toBe(1250.0)
        ->and($s['awaiting_confirmation'])->toBe(250.0)
        ->and($s['expected'])->toBe(1500.0)
        ->and($s['outstanding'])->toBe(250.0)
        ->and($s['tickets'])->toBe(5)
        ->and($s['people'])->toBe(7)
        ->and($s['people_admitted'])->toBe(2)
        ->and($s['released_tickets'])->toBe(1)
        ->and($s['comp_tickets'])->toBe(1)
        // 4.9% of 250 + M7.50, only on the online ticket
        ->and($s['ventiq_fee'])->toBe(19.75)
        ->and($s['payout_total'])->toBe(230.25)
        ->and($s['payout_due'])->toBe(230.25)
        ->and($s['payout_settled'])->toBe(0.0);
});

it('breaks the split down per ticket type', function () {
    $tiers = EventFinance::for($this->event)->byTier()->keyBy('name');

    expect($tiers['Table of 3']['tickets'])->toBe(1)
        ->and($tiers['Table of 3']['people'])->toBe(3)
        ->and($tiers['Table of 3']['collected_direct'])->toBe(750.0)
        ->and($tiers['General']['collected_ventiq'])->toBe(250.0)
        ->and($tiers['General']['collected'])->toBe(500.0); // online + legacy
});

it('feeds the same figures to both PDF reports', function () {
    $revenue = (new RevenueReportService($this->event))->buildData();
    $summary = (new RegistrationSummaryService($this->event))->buildData();

    expect($revenue['totalCollected'])->toBe(1250.0)
        ->and($revenue['finance']['collected_direct'])->toBe(750.0)
        ->and($summary['totalCollected'])->toBe(1250.0)
        ->and($summary['totalTickets'])->toBe(7)   // people
        ->and($summary['checkedIn'])->toBe(2);

    $html = view('reports.revenue-report', $revenue)->render();
    expect($html)->toContain('Who Collected the Money')->toContain('Still to be paid out');
});

it('only lets the event\'s own organization download its reports', function () {
    $this->actingAs($this->admin)->get(route('reports.revenue', $this->event))
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $outsider->assignRole('org_admin');
    $this->actingAs($outsider)->get(route('reports.revenue', $this->event))->assertNotFound();
    $this->actingAs($outsider)->get(route('reports.attendance', $this->event))->assertNotFound();
    $this->actingAs($outsider)->get(route('reports.registration-summary', $this->event))->assertNotFound();
});

it('lets a super admin download any organization\'s reports', function () {
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');

    $this->actingAs($super)->get(route('reports.attendance', $this->event))->assertOk();
});

it('shows the organizer the split on the event page and totals on the home page', function () {
    $this->actingAs($this->admin)->get(route('organizer.events.attendees', $this->event))
        ->assertOk()
        ->assertSee('Collected by VENTIQ (online)')
        ->assertSee('M750.00')
        ->assertSee('M1,250.00')
        ->assertSee('Waiting for you to confirm')
        ->assertSee('M230.25')
        ->assertSee(route('reports.revenue', $this->event));

    $this->get(route('organizer.home'))->assertSee('M1,250.00 collected');
});
