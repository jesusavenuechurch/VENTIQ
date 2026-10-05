<?php

use App\Models\{Organization, User};
use App\Services\AccountProvisioningService;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function googleSignup(string $name = 'Thabo Mokoena'): User
{
    // What GoogleAuthController provisions: the person's name, no phone.
    return app(AccountProvisioningService::class)->provision([
        'org_name' => $name, 'org_phone' => '', 'org_district' => '',
        'user_name' => $name, 'user_email' => strtolower(str_replace(' ', '', $name)) . random_int(1, 99999) . '@gmail.com',
        'google_id' => (string) random_int(1, 999999), 'email_verified_at' => now(), 'source' => 'google',
    ]);
}

it('asks a Google sign-up who is hosting before anything else', function () {
    $user = googleSignup();

    $this->actingAs($user)->get(route('organizer.home'))->assertRedirect(route('organizer.setup'));
    $this->get(route('organizer.payments.index'))->assertRedirect(route('organizer.setup'));
    $this->get(route('organizer.setup'))->assertOk()->assertSee('tell us who')->assertSee('Thabo Mokoena', false);
});

it('saves the organization and lets them in', function () {
    $user = googleSignup();

    $this->actingAs($user)->post(route('organizer.setup.store'), [
        'name' => 'Maseru Youth Network', 'phone' => '5949 4756', 'contact_email' => 'hello@myn.ls',
    ])->assertRedirect(route('organizer.home'));

    $org = $user->organization->fresh();
    expect($org->name)->toBe('Maseru Youth Network')
        ->and($org->phone)->toBe('+26659494756')
        ->and($org->contact_email)->toBe('hello@myn.ls');

    $this->get(route('organizer.home'))->assertOk();
});

it('explains when the name is taken or the phone is wrong', function () {
    Organization::factory()->create(['name' => 'Maseru Youth Network']);
    $user = googleSignup();

    $this->actingAs($user)->post(route('organizer.setup.store'), ['name' => 'Maseru Youth Network', 'phone' => '123'])
        ->assertSessionHasErrors(['name', 'phone']);
});

it('gives a second person with the same name their own organization', function () {
    $first = googleSignup('Thabo Mokoena');
    $second = googleSignup('Thabo Mokoena');

    expect($first->organization->name)->toBe('Thabo Mokoena')
        ->and($second->organization->name)->toBe('Thabo Mokoena (2)');
});

it('never stops a super admin acting for an organization', function () {
    $user = googleSignup();
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');

    $this->actingAs($super)->get(route('organizer.act-as.start', $user->organization));
    $this->get(route('organizer.home'))->assertOk();
});
