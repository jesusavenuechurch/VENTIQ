<?php

use App\Mail\OrganizationInviteMail;
use App\Models\{Organization, OrganizationInvite, User};
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Naledi']);
    $this->admin->assignRole('org_admin');
    $this->staff = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Teboho']);
    $this->staff->assignRole('staff');
});

it('invites someone with a role, and they join with it', function () {
    $this->actingAs($this->admin)->get(route('organizer.team.index'))
        ->assertOk()->assertSee('Teboho')->assertSee('Invite someone');

    $this->post(route('organizer.team.invite'), ['email' => 'Door@Example.com', 'role' => 'scanner'])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'as Door'));

    $invite = OrganizationInvite::firstOrFail();
    expect($invite->email)->toBe('door@example.com')->and($invite->role)->toBe('scanner');
    Mail::assertQueued(OrganizationInviteMail::class);

    auth()->logout();
    $this->post(route('organization.invite.submit', $invite->token), [
        'name' => 'Door Person', 'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
    ])->assertRedirect();

    $joined = User::where('email', 'door@example.com')->firstOrFail();
    expect($joined->organization_id)->toBe($this->org->id)
        ->and($joined->hasRole('scanner'))->toBeTrue();
});

it('changes roles and removes people, but never locks the organization out', function () {
    $this->actingAs($this->admin)->put(route('organizer.team.role', $this->staff), ['role' => 'org_admin']);
    expect($this->staff->fresh()->hasRole('org_admin'))->toBeTrue();

    // Two admins: one can be removed.
    $this->delete(route('organizer.team.remove', $this->staff))->assertSessionHas('status');
    $gone = $this->staff->fresh();
    expect($gone->organization_id)->toBeNull()->and($gone->roles)->toBeEmpty();

    // Nobody changes their own access.
    $this->put(route('organizer.team.role', $this->admin), ['role' => 'viewer'])->assertForbidden();

    // The last admin can't be demoted by another admin either.
    $other = User::factory()->create(['organization_id' => $this->org->id]);
    $other->assignRole('org_admin');
    $this->admin->syncRoles(['staff']);
    $this->actingAs($this->admin->fresh());
    // staff can't manage the team at all
    $this->put(route('organizer.team.role', $other), ['role' => 'viewer'])->assertForbidden();
});

it('refuses to demote or remove the only admin', function () {
    $second = User::factory()->create(['organization_id' => $this->org->id]);
    $second->assignRole('org_admin');
    $this->admin->syncRoles(['staff']);
    $this->admin->givePermissionTo('manage_staff');

    $this->actingAs($this->admin->fresh())->put(route('organizer.team.role', $second), ['role' => 'viewer'])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'only Admin'));
    $this->delete(route('organizer.team.remove', $second))
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'only Admin'));
    expect($second->fresh()->hasRole('org_admin'))->toBeTrue();
});

it('lets staff see the team but not change it, and keeps teams apart', function () {
    $this->actingAs($this->staff)->get(route('organizer.team.index'))
        ->assertOk()->assertSee('Naledi')->assertDontSee('Invite someone');
    $this->post(route('organizer.team.invite'), ['email' => 'x@example.com', 'role' => 'staff'])->assertForbidden();

    $stranger = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $this->actingAs($this->admin)->delete(route('organizer.team.remove', $stranger))->assertNotFound();
});

it('won\'t invite an email that already has an account', function () {
    $this->actingAs($this->admin)->post(route('organizer.team.invite'), ['email' => $this->staff->email, 'role' => 'staff'])
        ->assertSessionHasErrors(['email' => 'Teboho is already on your team.']);
});
