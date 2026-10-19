<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, User};
use App\Services\Reports\EventDay;
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');

    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now(), 'status' => 'published', 'payment_mode' => 'paid',
    ]);
    $this->general = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 250, 'is_active' => true]);
    $this->table = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Table of 3', 'price' => 750, 'quantity_per_purchase' => 3, 'is_active' => true]);

    $make = function (EventTier $tier, string $name, array $attrs = []) {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => $name, 'phone' => '+2665' . random_int(1000000, 9999999)]);
        return Ticket::create(array_merge([
            'event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id,
            'status' => 'active', 'payment_status' => 'completed', 'amount' => $tier->price,
            'admissions' => $tier->quantity_per_purchase ?? 1, 'has_whatsapp' => true,
        ], $attrs));
    };

    $make($this->general, 'Lerato')->admit();
    $make($this->general, 'Thabo', ['delivery_status' => 'failed']);
    $make($this->general, 'Palesa', ['whatsapp_delivered_at' => now()]);
    $group = $make($this->table, 'Mokoena family');
    $group->admit();
    $group->fresh()->admit();
    $make($this->general, 'Unpaid', ['status' => 'pending', 'payment_status' => 'pending']);
    $make($this->general, 'Gone', ['status' => 'expired', 'payment_status' => 'pending']);
});

it('counts people in against people expected', function () {
    $s = EventDay::for($this->event)->summary();

    expect($s['expected'])->toBe(6)       // 3 singles + a table of 3
        ->and($s['admitted'])->toBe(3)    // Lerato + 2 of the family
        ->and($s['still_to_come'])->toBe(3)
        ->and($s['percent'])->toBe(50)
        ->and($s['tickets_arrived'])->toBe(2)
        ->and($s['unpaid_people'])->toBe(1);

    $tiers = EventDay::for($this->event)->byTier()->keyBy('name');
    expect($tiers['Table of 3'])->toMatchArray(['expected' => 3, 'admitted' => 2])
        ->and($tiers['General'])->toMatchArray(['expected' => 3, 'admitted' => 1]);

    expect((int) EventDay::for($this->event)->arrivalsByHour()->sum('people'))->toBe(3);
});

it('lists WhatsApp tickets that failed', function () {
    $w = EventDay::for($this->event)->whatsapp();

    expect($w['sent'])->toBe(1)
        ->and($w['failed']->pluck('client.full_name')->all())->toBe(['Thabo'])
        ->and($w['unsent'])->toBe(2);
});

it('shows the page, its live refresh, and only to the event\'s organization', function () {
    $this->actingAs($this->admin)->get(route('organizer.events.day', $this->event))
        ->assertOk()->assertSee('People in')->assertSee('of 6 expected')->assertSee('Mokoena family')
        ->assertSee('Not delivered to');

    $this->get(route('organizer.events.day', [$this->event, 'partial' => 1]))
        ->assertOk()->assertSee('People in')->assertDontSee('<html', false);

    $this->get(route('organizer.home'))->assertSee(route('organizer.events.day', $this->event));

    $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $outsider->assignRole('org_admin');
    $this->actingAs($outsider)->get(route('organizer.events.day', $this->event))->assertNotFound();
});

it('downloads the attendance register as Excel, without released tickets', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.attendance-excel', $this->event));
    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    $rows = (new \App\Exports\AttendanceRegisterExport($this->event))->collection();
    expect($rows)->toHaveCount(5)  // not the expired one
        ->and($rows->pluck(7)->all())->toContain('2 of 3 in');
});
