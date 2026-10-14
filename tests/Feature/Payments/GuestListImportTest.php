<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, TicketFee, TicketPayment, User};
use Illuminate\Http\UploadedFile;
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
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid',
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]);
});

function guestCsv(array $lines): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'guests') . '.csv';
    file_put_contents($path, implode("\n", $lines) . "\n");

    return new UploadedFile($path, 'guests.csv', 'text/csv', null, true);
}

function checkGuests(object $t, UploadedFile $file, array $extra = [])
{
    return $t->actingAs($t->admin)->post(route('organizer.events.guests.check', $t->event), array_merge([
        'file' => $file, 'event_tier_id' => $t->tier->id, 'mode' => 'complimentary', 'send_whatsapp' => '1',
    ], $extra));
}

it('checks every row before creating anything, and never invents numbers', function () {
    // Someone already holding a ticket, and a client of ANOTHER organization with the same number.
    $existing = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Already In', 'phone' => '+26650000003']);
    Ticket::create(['event_id' => $this->event->id, 'client_id' => $existing->id, 'event_tier_id' => $this->tier->id, 'status' => 'active', 'payment_status' => 'completed', 'amount' => 200]);
    $otherOrg = Organization::factory()->create();
    Client::create(['organization_id' => $otherOrg->id, 'full_name' => 'Someone Else', 'phone' => '+26659494756']);

    $response = checkGuests($this, guestCsv([
        'full_name,phone,email',
        'Lerato Mokoena,5949 4756,lerato@example.com',
        'No Phone,,',
        'Bad Number,12,',
        'Lerato Again,+266 5949-4756,',
        'Already In,50000003,',
        ',50000009,',
        ',,',
    ]));

    $response->assertOk()
        ->assertSee('1 ready')->assertSee('5 with a problem')
        ->assertSee('No phone number')->assertSee('Same number as row 2')->assertSee('Already has a ticket for this event')->assertSee('No name');
    expect(Ticket::count())->toBe(1);

    preg_match('/name="token" value="([^"]+)"/', $response->getContent(), $m);
    $this->post(route('organizer.events.guests.store', $this->event), ['token' => $m[1]])
        ->assertRedirect(route('organizer.events.attendees', $this->event))
        ->assertSessionHas('status', '1 ticket created.');

    $ticket = Ticket::where('is_complimentary', true)->with('client')->sole();
    expect($ticket->client->organization_id)->toBe($this->org->id)
        ->and($ticket->client->full_name)->toBe('Lerato Mokoena')
        ->and($ticket->status)->toBe('active');

    // The other organization's client was left alone.
    expect(Client::where('organization_id', $otherOrg->id)->value('full_name'))->toBe('Someone Else');

    // The check can't be used twice.
    $this->post(route('organizer.events.guests.store', $this->event), ['token' => $m[1]])->assertSessionHasErrors('file');
});

it('records an already-paid list as money paid to the organizer', function () {
    $response = checkGuests($this, guestCsv(['name,mobile', 'Thabo Molefe,59990001', 'Palesa Nthati,59990002']), [
        'mode' => 'paid_direct', 'method' => 'ecocash', 'send_whatsapp' => '0',
    ]);
    preg_match('/name="token" value="([^"]+)"/', $response->getContent(), $m);
    $this->post(route('organizer.events.guests.store', $this->event), ['token' => $m[1]])->assertSessionHas('status', '2 tickets created.');

    $tickets = Ticket::with('payments')->get();
    expect($tickets)->toHaveCount(2)
        ->and($tickets->pluck('status')->unique()->all())->toBe(['active'])
        ->and((float) $tickets->sum('amount_paid'))->toBe(400.0)
        ->and(TicketPayment::where('status', 'approved')->where('source', 'organizer_direct')->where('payment_method', 'ecocash')->count())->toBe(2)
        ->and(TicketFee::where('collection', TicketFee::COLLECT_BY_INVOICE)->count())->toBe(2)
        ->and($this->tier->fresh()->quantity_sold)->toBe(2);
});

it('stops at the tier\'s capacity and says how many were skipped', function () {
    $this->tier->update(['quantity_available' => 1]);
    $response = checkGuests($this, guestCsv(['full_name,phone', 'One,59990001', 'Two,59990002']));
    preg_match('/name="token" value="([^"]+)"/', $response->getContent(), $m);

    $this->post(route('organizer.events.guests.store', $this->event), ['token' => $m[1]])
        ->assertSessionHas('status', fn ($s) => str_starts_with($s, '1 ticket created; 1 skipped'));
});

it('is only for the organization\'s own events and people who can approve', function () {
    $staff = User::factory()->create(['organization_id' => $this->org->id]);
    $staff->assignRole('viewer');
    $this->actingAs($staff)->get(route('organizer.events.guests.create', $this->event))->assertForbidden();

    $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $outsider->assignRole('org_admin');
    $this->actingAs($outsider)->get(route('organizer.events.guests.create', $this->event))->assertNotFound();

    $template = $this->actingAs($this->admin)->get(route('organizer.guests.template'))->assertOk();
    expect($template->streamedContent())->toContain('full_name,phone,email');
});
