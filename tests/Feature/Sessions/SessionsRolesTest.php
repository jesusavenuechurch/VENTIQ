<?php

use App\Models\{Event, Organization, Session, User};
use Illuminate\Support\Facades\{Bus, Mail};
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');
    $event = Event::create(['organization_id' => $this->org->id, 'name' => 'Board meeting', 'event_date' => now(), 'is_public' => false]);
    $this->session = Session::create([
        'organization_id' => $this->org->id, 'event_id' => $event->id, 'type' => 'meeting', 'title' => 'Board meeting',
        'date' => now()->toDateString(), 'status' => 'reported', 'session_report' => 'Notes', 'public_token' => str_repeat('a', 32), 'session_code' => 'ROLE01',
    ]);
    $this->member = function (?string $role) {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        if ($role) $user->assignRole($role);
        return $user;
    };
});

it('lets the door check people in, and nothing else', function () {
    $this->actingAs(($this->member)('scanner'));

    $this->get(route('sessions.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sessionsArea.can', ['desk' => true, 'checkin' => true, 'view' => false, 'run' => false, 'plan' => false]));
    $this->get(route('sessions.checkin', $this->session))->assertOk();
    $this->post(route('sessions.checkin.store', $this->session), ['full_name' => 'Lineo R', 'email' => 'lineo@example.com'])->assertRedirect();

    $this->get(route('sessions.report', $this->session))->assertRedirect(route('sessions.index'))->assertSessionHas('error');
    $this->get(route('sessions.show', $this->session))->assertRedirect(route('sessions.index'));
    $this->get(route('sessions.create'))->assertRedirect(route('sessions.index'));
    $this->get(route('programmes.index'))->assertRedirect(route('sessions.index'));
    $this->postJson(route('sessions.start', $this->session))->assertForbidden();
});

it('lets a viewer read reports but not change them or check people in', function () {
    $this->actingAs(($this->member)('viewer'));

    $this->get(route('sessions.reports'))->assertOk();
    $this->get(route('sessions.report', $this->session))->assertOk();
    $this->get(route('programmes.index'))->assertOk();

    $this->post(route('sessions.report.review', $this->session))->assertForbidden();
    $this->patchJson(route('sessions.report.update', $this->session), ['session_report' => 'Changed'])->assertForbidden();
    $this->get(route('sessions.checkin', $this->session))->assertRedirect(route('sessions.index'));
    expect($this->session->fresh()->session_report)->toBe('Notes');
});

it('lets staff run sessions but keeps buying a plan to admins', function () {
    $this->actingAs(($this->member)('staff'));

    $this->get(route('sessions.create'))->assertOk();
    $this->post(route('sessions.report.review', $this->session))->assertRedirect();
    $this->get(route('sessions.plan'))->assertRedirect(route('sessions.index'));
    $this->postJson(route('paylesotho.session-package.initiate'), ['type' => 'payg', 'quantity' => 1, 'method' => 'ecocash', 'mobile_number' => '+26658000000'])
        ->assertForbidden();

    $this->actingAs($this->admin)->get(route('sessions.plan'))->assertOk();
});

it('treats someone without a role as a viewer, unless the organization has no admin', function () {
    $this->actingAs(($this->member)(null))->get(route('sessions.create'))->assertRedirect(route('sessions.index'));

    $this->admin->syncRoles([]);
    $this->get(route('sessions.create'))->assertOk();
});
