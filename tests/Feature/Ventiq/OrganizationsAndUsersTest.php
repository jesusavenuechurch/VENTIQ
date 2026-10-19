<?php

use App\Models\{Event, Organization, User};

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->super = User::factory()->create(['organization_id' => null]);
    $this->super->assignRole('super_admin');
    $this->org = Organization::factory()->create(['name' => 'Maseru Youth Network']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Lerato Mokoena']);
    $this->admin->assignRole('org_admin');
});

it('keeps the admin to super admins', function () {
    $this->actingAs($this->admin)->get(route('ventiq.organizations.index'))->assertForbidden();
    $this->get(route('ventiq.users.index'))->assertForbidden();
    $this->put(route('ventiq.organizations.update', $this->org), ['name' => 'Taken', 'email' => 'x@example.com'])->assertForbidden();
});

it('lists and searches organizations', function () {
    Organization::factory()->create(['name' => 'Leribe Choir']);

    $this->actingAs($this->super)->get(route('ventiq.organizations.index', ['q' => 'youth']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Ventiq/Organizations/Index')->has('rows.data', 1)->where('rows.data.0.name', 'Maseru Youth Network'));

    $this->get(route('ventiq.organizations.index', ['filter' => ['active' => 'no']]))->assertInertia(fn ($page) => $page->has('rows.data', 0));
});

it('creates an organization with a free web address, and changes what only VENTIQ changes', function () {
    $this->actingAs($this->super)->post(route('ventiq.organizations.store'), ['name' => 'Maseru Youth Network 2', 'email' => 'hello@myn.ls', 'phone' => '5949 4756'])
        ->assertRedirect()->assertSessionHas('status');
    $made = Organization::where('name', 'Maseru Youth Network 2')->sole();
    expect($made->slug)->toBe('maseru-youth-network-2')->and($made->phone)->toBe('+26659494756');

    $this->put(route('ventiq.organizations.update', $this->org), [
        'name' => 'Maseru Youth Network', 'slug' => 'myn', 'email' => 'team@myn.ls', 'is_active' => false, 'keep_account' => true, 'workshop_enabled' => true,
    ])->assertSessionHas('status');
    $org = $this->org->fresh();
    expect($org->slug)->toBe('myn')->and($org->is_active)->toBeFalsy()->and($org->keep_account)->toBeTrue()->and($org->workshop_enabled)->toBeTruthy();

    $this->put(route('ventiq.organizations.update', $this->org), ['name' => 'Maseru Youth Network 2', 'email' => 'x@example.com'])->assertSessionHasErrors('name');
});

it('deletes only an organization with no events', function () {
    Event::create(['organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 'summit', 'event_date' => now()->addMonth(), 'status' => 'draft']);
    $this->actingAs($this->super)->delete(route('ventiq.organizations.destroy', $this->org))->assertSessionHas('error');
    expect(Organization::find($this->org->id))->not->toBeNull();

    $empty = Organization::factory()->create();
    $member = User::factory()->create(['organization_id' => $empty->id]);
    $this->delete(route('ventiq.organizations.destroy', $empty))->assertRedirect(route('ventiq.organizations.index'));
    expect(Organization::find($empty->id))->toBeNull()->and($member->fresh()->organization_id)->toBeNull();
});

it('creates and fixes accounts, but never changes your own access', function () {
    $this->actingAs($this->super)->post(route('ventiq.users.store'), [
        'name' => 'Thabo Letsie', 'email' => 'thabo@example.com', 'password' => 'Secret-Pass-123', 'password_confirmation' => 'Secret-Pass-123',
        'organization_id' => $this->org->id, 'role' => 'staff',
    ])->assertRedirect();
    $thabo = User::where('email', 'thabo@example.com')->sole();
    expect($thabo->hasRole('staff'))->toBeTrue()->and($thabo->organization_id)->toBe($this->org->id);

    $this->get(route('ventiq.users.index', ['filter' => ['role' => 'staff']]))
        ->assertInertia(fn ($page) => $page->has('rows.data', 1)->where('rows.data.0.email', 'thabo@example.com'));

    $this->put(route('ventiq.users.update', $thabo), ['name' => 'Thabo Letsie', 'email' => 'thabo@example.com', 'organization_id' => $this->org->id, 'role' => 'org_admin']);
    expect($thabo->fresh()->hasRole('org_admin'))->toBeTrue();

    $this->put(route('ventiq.users.update', $this->super), ['name' => 'Me', 'email' => $this->super->email, 'organization_id' => $this->org->id, 'role' => 'viewer']);
    expect($this->super->fresh()->hasRole('super_admin'))->toBeTrue()->and($this->super->fresh()->organization_id)->toBeNull();

    $this->delete(route('ventiq.users.destroy', $this->super))->assertForbidden();
    $this->delete(route('ventiq.users.destroy', $thabo))->assertRedirect(route('ventiq.users.index'));
    expect(User::find($thabo->id))->toBeNull();
});
