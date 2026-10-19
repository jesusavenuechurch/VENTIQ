<?php

use App\Models\{Client, Event, EventTier, Organization, Ticket, User};
use App\Support\IntentRedirect;
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['phone' => '+26659494756']);
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'published', 'payment_mode' => 'paid',
    ]);
    $this->tier = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'General', 'price' => 200, 'is_active' => true]);
});

it('never deletes an event, ticket type or paid ticket along with its money records', function () {
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Guest', 'phone' => '+26650000001']);
    $ticket = Ticket::create(['event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $this->tier->id, 'status' => 'active', 'payment_status' => 'completed', 'amount' => 200]);

    expect($this->event->delete())->toBeFalse()
        ->and($this->tier->delete())->toBeFalse()
        ->and($ticket->delete())->toBeFalse()
        ->and(Ticket::find($ticket->id))->not->toBeNull();

    // An unpaid, unused ticket can still go, and then its type.
    $pending = Ticket::create(['event_id' => $this->event->id, 'client_id' => $client->id, 'event_tier_id' => $this->tier->id, 'status' => 'pending', 'payment_status' => 'pending', 'amount' => 200]);
    expect($pending->delete())->toBeTrue();

    $empty = EventTier::create(['event_id' => $this->event->id, 'tier_name' => 'Unused', 'price' => 50, 'is_active' => true]);
    expect($empty->delete())->toBeTrue();
});

it('sends a Sessions-only organization to Sessions when no destination is given', function () {
    $sessionsOrg = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $sessionsOrg->id]);
    \Illuminate\Support\Facades\DB::table('capture_sessions')->insert(['organization_id' => $sessionsOrg->id, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($user);

    expect(IntentRedirect::resolve(null))->toBe(route('sessions.index'))
        ->and(IntentRedirect::resolve('host'))->toBe(route('organizer.home'));

    $eventsUser = User::factory()->create(['organization_id' => $this->org->id]);
    $this->actingAs($eventsUser);
    expect(IntentRedirect::resolve(null))->toBe(route('organizer.home'));
});

it('lets an organization without events details reach its team', function () {
    $sessionsOrg = Organization::factory()->create(['phone' => null]);
    $admin = User::factory()->create(['organization_id' => $sessionsOrg->id]);
    $admin->assignRole('org_admin');

    $this->actingAs($admin)->get(route('organizer.team.index'))->assertOk();
    $this->get(route('organizer.home'))->assertRedirect(route('organizer.setup'));
});
