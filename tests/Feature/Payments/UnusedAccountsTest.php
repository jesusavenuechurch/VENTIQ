<?php

use App\Models\{Event, Organization, User};
use App\Notifications\Accounts\UnusedAccountWarning;
use App\Services\AccountProvisioningService;
use Illuminate\Support\Facades\{Mail, Notification};
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    // A normal sign-up: organization, admin, and the free packages.
    $this->signUp = function (string $name) {
        $user = app(AccountProvisioningService::class)->provision([
            'org_name' => $name, 'user_name' => "$name Admin", 'user_email' => Str::slug($name) . '@example.com', 'user_password' => 'secret123',
        ]);
        $user->forceFill(['last_active_at' => now()])->save();

        return $user->fresh();
    };
});

it('warns an untouched account a week ahead, then removes it', function () {
    $user = ($this->signUp)('Quiet Org');
    $org = $user->organization;

    $this->travel(54)->days();
    $this->artisan('accounts:remove-unused');
    Notification::assertSentTo($user, UnusedAccountWarning::class);
    expect($org->fresh()->removal_warned_at)->not->toBeNull();

    $this->travel(3)->days();
    $this->artisan('accounts:remove-unused');
    expect(Organization::find($org->id))->not->toBeNull();   // not yet

    $this->travel(4)->days();
    $this->artisan('accounts:remove-unused');
    expect(Organization::find($org->id))->toBeNull()->and(User::find($user->id))->toBeNull();
});

it('keeps an account that signs in, uses the link, or has created something', function () {
    $signsIn = ($this->signUp)('Signs In Org');
    $usesLink = ($this->signUp)('Link Org');
    $hasEvent = ($this->signUp)('Event Org');
    Event::create(['organization_id' => $hasEvent->organization_id, 'name' => 'Draft', 'slug' => 'd-' . Str::random(5), 'event_date' => now()->addYear(), 'status' => 'draft']);
    $kept = ($this->signUp)('Kept Org');
    $kept->organization->update(['keep_account' => true]);

    $this->travel(54)->days();
    $this->artisan('accounts:remove-unused');
    Notification::assertSentTo([$signsIn, $usesLink], UnusedAccountWarning::class);
    Notification::assertNotSentTo([$hasEvent, $kept], UnusedAccountWarning::class);

    // Signing in (any page while signed in) and the email link both count.
    $this->actingAs($signsIn)->get('/');
    $link = null;
    Notification::assertSentTo($usesLink, UnusedAccountWarning::class, function ($n) use ($usesLink, &$link) {
        $link = $n->keepUrl($usesLink);
        return true;
    });
    auth()->logout();
    $this->get($link)->assertOk()->assertSee('Your account stays');

    $this->travel(8)->days();
    $this->artisan('accounts:remove-unused');
    expect(User::whereIn('id', [$signsIn->id, $usesLink->id, $hasEvent->id, $kept->id])->count())->toBe(4);
});

it('never touches super admins, and lists without changing anything on a dry run', function () {
    $super = User::factory()->create(['organization_id' => null, 'last_active_at' => now()]);
    $super->assignRole('super_admin');
    $orphan = User::factory()->create(['organization_id' => null, 'last_active_at' => now()]);

    $this->travel(54)->days();
    $this->artisan('accounts:remove-unused --dry-run')->expectsOutputToContain("Would warn: {$orphan->email}");
    Notification::assertNothingSent();

    $this->artisan('accounts:remove-unused');
    Notification::assertSentTo($orphan, UnusedAccountWarning::class);
    Notification::assertNotSentTo($super, UnusedAccountWarning::class);

    $this->travel(7)->days();
    $this->artisan('accounts:remove-unused');
    expect(User::find($orphan->id))->toBeNull()->and(User::find($super->id))->not->toBeNull();
});
