<?php

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\{Hash, Mail, RateLimiter};

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('login-code:min:thabo@example.com');
    RateLimiter::clear('login-code:hour:thabo@example.com');
    RateLimiter::clear('login-code:ip:127.0.0.1');
});

it('changes a password only with the current one', function () {
    $user = User::factory()->create(['password' => Hash::make('old-pass-123')]);

    $this->actingAs($user)->get(route('account.edit'))->assertOk()->assertSee('Change your password')->assertSee('Current password');

    $this->put(route('account.password'), ['current_password' => 'wrong', 'password' => 'new-pass-1234', 'password_confirmation' => 'new-pass-1234'])
        ->assertSessionHasErrors('current_password');
    $this->put(route('account.password'), ['current_password' => 'old-pass-123', 'password' => 'new-pass-1234', 'password_confirmation' => 'new-pass-1234'])
        ->assertSessionHas('status');

    expect(Hash::check('new-pass-1234', $user->fresh()->password))->toBeTrue();
});

it('lets Google sign-ups add a password without one', function () {
    $user = User::factory()->create(['google_id' => 'g-1']);

    $this->actingAs($user)->get(route('account.edit'))->assertSee('Add a password')->assertDontSee('Current password');
    $this->put(route('account.password'), ['password' => 'fresh-pass-99', 'password_confirmation' => 'fresh-pass-99'])->assertSessionHas('status');

    expect(Hash::check('fresh-pass-99', $user->fresh()->password))->toBeTrue();
});

it('lets someone who forgot their password reset it after signing in with a code', function () {
    $user = User::factory()->create(['email' => 'thabo@example.com', 'password' => Hash::make('forgotten-123')]);

    $this->postJson(route('login.code.send'), ['email' => 'thabo@example.com']);
    $code = null;
    Mail::assertSent(LoginCodeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
    $this->postJson(route('login.code.verify'), ['email' => 'thabo@example.com', 'code' => $code])->assertOk();

    $this->get(route('account.edit'))->assertDontSee('Current password');
    $this->put(route('account.password'), ['password' => 'remembered-456', 'password_confirmation' => 'remembered-456'])->assertSessionHas('status');

    expect(Hash::check('remembered-456', $user->fresh()->password))->toBeTrue();
});

it('updates the name and needs a login', function () {
    $this->get(route('account.edit'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->actingAs($user)->put(route('account.update'), ['name' => 'Naledi Sello'])->assertSessionHas('status');
    expect($user->fresh()->name)->toBe('Naledi Sello');
});
