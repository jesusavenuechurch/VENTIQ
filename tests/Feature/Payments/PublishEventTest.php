<?php

use App\Models\{Event, Organization, User};
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');
    $this->event = Event::create([
        'organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 's-' . Str::random(6),
        'event_date' => now()->addMonth(), 'status' => 'draft', 'payment_mode' => 'free', 'is_public' => true,
    ]);
});

it('offers Publish on a draft, in the list and on its pages, and puts it live', function () {
    $publish = route('organizer.events.publish', $this->event);

    $this->actingAs($this->admin)->get(route('organizer.home'))->assertOk()->assertSee($publish, false);
    $this->get(route('organizer.events.attendees', $this->event))->assertOk()
        ->assertSee("Draft: attendees can't see this event", false)->assertSee($publish, false);

    $this->post($publish)->assertRedirect();
    expect($this->event->fresh()->status)->toBe('published');

    $this->get(route('organizer.events.attendees', $this->event))->assertOk()->assertDontSee($publish, false);
});

it("doesn't publish another organization's event", function () {
    $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $other->assignRole('org_admin');

    $this->actingAs($other)->post(route('organizer.events.publish', $this->event))->assertNotFound();
    expect($this->event->fresh()->status)->toBe('draft');
});
