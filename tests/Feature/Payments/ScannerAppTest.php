<?php

use App\Models\{AppRelease, Organization, User};
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(AppRelease::DISK);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');
});

it('offers the current scanner build on the dashboard, and downloads it as an APK', function () {
    $this->actingAs($this->admin)->get(route('organizer.home'))->assertOk()->assertDontSee('VENTIQ Scanner');

    Storage::disk(AppRelease::DISK)->put('app-releases/old.apk', 'old');
    Storage::disk(AppRelease::DISK)->put('app-releases/new.apk', str_repeat('x', 2048));
    $old = AppRelease::create(['version' => '1.0.0', 'apk_path' => 'app-releases/old.apk']);
    $new = AppRelease::create(['version' => '1.1.0', 'apk_path' => 'app-releases/new.apk']);
    expect($old->fresh()->is_current)->toBeFalse()->and($new->apk_size)->toBe(2048);

    $this->get(route('organizer.home'))->assertOk()->assertSee('VENTIQ Scanner')
        ->assertSee('Download APK · v1.1.0', false)->assertDontSee('Get it on Google Play');

    $this->get(route('scanner-app.download'))->assertOk()
        ->assertHeader('content-type', 'application/vnd.android.package-archive')
        ->assertDownload('VENTIQ-Scanner-1.1.0.apk');

    $new->update(['play_store_url' => 'https://play.google.com/store/apps/details?id=ls.co.ventiq.scanner']);
    $this->get(route('organizer.home'))->assertSee('Get it on Google Play');
});

it('keeps older builds for super admins only', function () {
    Storage::disk(AppRelease::DISK)->put('app-releases/old.apk', 'old');
    $old = AppRelease::create(['version' => '1.0.0', 'apk_path' => 'app-releases/old.apk', 'is_current' => false]);

    $this->actingAs($this->admin)->get(route('scanner-app.download.release', $old))->assertForbidden();
    $this->get(route('scanner-app.download'))->assertNotFound();   // nothing current yet

    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');
    $this->actingAs($super)->get(route('scanner-app.download.release', $old))->assertOk();
    $this->get(\App\Filament\Resources\AppReleaseResource::getUrl('index'))->assertOk()->assertSee('1.0.0');
    $this->get(\App\Filament\Resources\AppReleaseResource::getUrl('create'))->assertOk()->assertSee('APK file');
});

it('needs a sign-in', function () {
    $this->get(route('scanner-app.download'))->assertRedirect();
});
