<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, TicketPayment, User};
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create(['slug' => 'myn-' . Str::random(4)]);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');

    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Maseru Youth Summit', 'slug' => 'summit-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid', 'is_public' => true,
    ]);
});

it('serves the event QR code as SVG, as a download when asked', function () {
    $this->actingAs($this->admin)->get(route('organizer.events.qr', $this->event))
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml')
        ->assertSee('<svg', false);

    $this->get(route('organizer.events.qr', [$this->event, 'download' => 1]))
        ->assertHeader('content-disposition', 'attachment; filename="' . $this->event->slug . '-qr.svg"');
});

it('keeps other organizations\' QR codes private', function () {
    $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $outsider->assignRole('org_admin');

    $this->actingAs($outsider)->get(route('organizer.events.qr', $this->event))->assertNotFound();
});

it('offers sharing with the short link on the home and event pages', function () {
    $short = route('event.short', [$this->org->slug, $this->event->slug]);
    expect($this->event->share_url)->toBe($short);

    $this->actingAs($this->admin)->get(route('organizer.home'))
        ->assertOk()->assertSee('Share &amp; QR', false)->assertSee('qr.svg', false);
    $this->get(route('organizer.events.attendees', $this->event))
        ->assertOk()->assertSee('Share &amp; QR', false)->assertSee('Download for your poster');
});

it('counts payments waiting on the organizer in the tabs', function () {
    $tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 250, 'is_active' => true]);
    foreach (range(1, 2) as $i) {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => "Guest $i", 'phone' => '+2665000000' . $i]);
        $ticket = Ticket::create(['event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $tier->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 250]);
        TicketPayment::create(['ticket_id' => $ticket->id, 'amount' => 250, 'status' => 'pending', 'payment_type' => 'full', 'submitted_at' => now()]);
    }

    expect(TicketPayment::awaitingDecisionFor($this->org)->count())->toBe(2);

    $this->actingAs($this->admin)->get(route('organizer.accounts.index'))
        ->assertOk()
        ->assertSee('To confirm')       // the phone tab bar
        ->assertSeeInOrder(['Payments to confirm', '2']);
});
