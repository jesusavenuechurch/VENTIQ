<?php

use App\Models\{Organization, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Old Name', 'phone' => '+26659494756']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');
});

it('lets an admin edit an existing organization, keeping its web address once it has events', function () {
    \App\Models\Event::create(['organization_id' => $this->org->id, 'name' => 'Summit', 'slug' => 'summit', 'event_date' => now()->addMonth(), 'status' => 'published', 'is_public' => true]);
    $slug = $this->org->slug;

    $this->actingAs($this->admin)->get(route('organizer.organization.edit'))->assertOk()->assertSee('Old Name')->assertSee('Save changes');
    $this->get(route('organizer.team.index'))->assertSee(route('organizer.organization.edit'));

    $this->put(route('organizer.organization.update'), [
        'name' => 'Maseru Youth Network', 'phone' => '5800 0000', 'contact_email' => 'hello@myn.ls',
        'website' => 'https://myn.ls', 'tagline' => 'Young leaders', 'description' => 'We gather.',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertRedirect(route('organizer.organization.edit'))->assertSessionHas('status');

    $org = $this->org->fresh();
    expect($org->name)->toBe('Maseru Youth Network')
        ->and($org->phone)->toBe('+26658000000')
        ->and($org->slug)->toBe($slug)
        ->and($org->logo)->toBe($org->logo_path);
    Storage::disk('public')->assertExists($org->logo_path);

    // The logo now shows to attendees (public pages read ->logo).
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $this->actingAs($super)->get(route('public.events', $slug))->assertSee(Storage::url($org->logo_path));
    $this->actingAs($this->admin);

    $this->put(route('organizer.organization.update'), ['name' => 'Maseru Youth Network', 'phone' => '58000000', 'remove_logo' => '1']);
    expect($this->org->fresh()->logo_path)->toBeNull();
});

it('lets the rest of the team see the details but not change them', function () {
    $staff = User::factory()->create(['organization_id' => $this->org->id]);
    $staff->assignRole('staff');

    $this->actingAs($staff)->get(route('organizer.organization.edit'))->assertOk()->assertSee('Only an admin')->assertDontSee('Save changes');
    $this->put(route('organizer.organization.update'), ['name' => 'Taken over', 'phone' => '58000000'])->assertForbidden();
    expect($this->org->fresh()->name)->toBe('Old Name');
});

it('checks the details the same way as at setup', function () {
    Organization::factory()->create(['name' => 'Taken Name']);

    $this->actingAs($this->admin)->put(route('organizer.organization.update'), ['name' => 'Taken Name', 'phone' => '12', 'website' => 'not a url'])
        ->assertSessionHasErrors(['name', 'phone', 'website']);
});

it('lets the web address follow the name before there are any events', function () {
    $this->actingAs($this->admin)->put(route('organizer.organization.update'), ['name' => 'Brand New Name', 'phone' => '58000000']);

    expect($this->org->fresh()->slug)->toBe('brand-new-name');
});

it('keeps the organization\'s own events page to super admins for now', function () {
    $this->actingAs($this->admin)->get(route('public.events', $this->org->slug))->assertNotFound();
    $this->get(route('organizer.organization.edit'))->assertDontSee(route('public.events', $this->org->slug));

    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $this->actingAs($super)->get(route('public.events', $this->org->slug))->assertOk();
});
