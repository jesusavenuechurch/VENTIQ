<?php

use App\Mail\{LoginCodeMail, NoAccountMail};
use App\Models\{LoginCode, Organization, User};
use Illuminate\Support\Facades\{Mail, RateLimiter};

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('login-code:min:naledi@example.com');
    RateLimiter::clear('login-code:hour:naledi@example.com');
    RateLimiter::clear('login-code:ip:127.0.0.1');
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->org = Organization::factory()->create(['phone' => '+26659494756']);
    $this->google = User::factory()->create(['organization_id' => $this->org->id, 'email' => 'naledi@example.com', 'google_id' => 'g-123']);
    $this->google->assignRole('org_admin');
    $this->withPassword = User::factory()->create(['organization_id' => $this->org->id, 'email' => 'thabo@example.com', 'password' => bcrypt('secret-pass-1')]);
    $this->withPassword->assignRole('org_admin');
});

/** The code from the last sign-in email. */
function sentCode(): string
{
    $code = null;
    Mail::assertSent(LoginCodeMail::class, function ($mail) use (&$code) { $code = $mail->code; return true; });

    return $code;
}

it('sends Google sign-ups a code and signs them in with it', function () {
    $this->postJson(route('login.start'), ['email' => 'Naledi@Example.com', 'intent' => 'host'])
        ->assertOk()->assertJson(['next' => 'code']);

    $this->postJson(route('login.code.verify'), ['email' => 'naledi@example.com', 'code' => sentCode(), 'intent' => 'host'])
        ->assertOk()->assertJson(['redirect' => route('organizer.home')]);

    $this->assertAuthenticatedAs($this->google);
});

it('asks password accounts for their password, with a code as the fallback', function () {
    $this->postJson(route('login.start'), ['email' => 'thabo@example.com'])->assertJson(['next' => 'password']);
    Mail::assertNothingSent();

    $this->postJson(route('login.submit'), ['email' => 'thabo@example.com', 'password' => 'secret-pass-1'])->assertOk();
    $this->assertAuthenticatedAs($this->withPassword);

    auth()->logout();
    $this->postJson(route('login.code.send'), ['email' => 'thabo@example.com'])->assertJson(['next' => 'code']);
    Mail::assertSent(LoginCodeMail::class, fn ($m) => $m->hasTo('thabo@example.com'));
});

it('answers the same for an email with no account, and tells that inbox', function () {
    $this->postJson(route('login.start'), ['email' => 'nobody@example.com'])->assertOk()->assertJson(['next' => 'code']);

    Mail::assertSent(NoAccountMail::class, fn ($m) => $m->hasTo('nobody@example.com'));
    Mail::assertNotSent(LoginCodeMail::class);
    expect(LoginCode::count())->toBe(0);
});

it('rejects wrong, used, expired and over-tried codes', function () {
    $this->postJson(route('login.start'), ['email' => 'naledi@example.com']);
    $code = sentCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    $this->postJson(route('login.code.verify'), ['email' => 'naledi@example.com', 'code' => $wrong])->assertStatus(422);
    $this->assertGuest();

    // Five wrong tries and even the right code stops working.
    foreach (range(1, 4) as $i) {
        $this->postJson(route('login.code.verify'), ['email' => 'naledi@example.com', 'code' => $wrong]);
    }
    $this->postJson(route('login.code.verify'), ['email' => 'naledi@example.com', 'code' => $code])->assertStatus(422);

    // A fresh code works once, and not after it expires.
    RateLimiter::clear('login-code:min:naledi@example.com');
    Mail::fake();
    $this->postJson(route('login.code.send'), ['email' => 'naledi@example.com']);
    $fresh = sentCode();
    LoginCode::latest('id')->first()->update(['expires_at' => now()->subMinute()]);
    $this->postJson(route('login.code.verify'), ['email' => 'naledi@example.com', 'code' => $fresh])->assertStatus(422);
    $this->assertGuest();
});

it('signs in from the emailed link on a tap, once', function () {
    $this->postJson(route('login.start'), ['email' => 'naledi@example.com', 'intent' => 'host']);
    $link = null;
    Mail::assertSent(LoginCodeMail::class, function ($m) use (&$link) { $link = $m->link; return true; });
    $path = parse_url($link, PHP_URL_PATH);

    // Opening the link (as a mail scanner would) doesn't sign in or use it up.
    $this->get($path)->assertOk()->assertSee('Sign in as naledi@example.com');
    $this->assertGuest();

    $this->post($path)->assertRedirect(route('organizer.home'));
    $this->assertAuthenticatedAs($this->google);

    auth()->logout();
    $this->post($path)->assertRedirect(route('login'));
    $this->assertGuest();
    $this->get($path)->assertSee('no longer works');
});

it('only lets the newest code work and limits how often codes are sent', function () {
    $this->postJson(route('login.code.send'), ['email' => 'naledi@example.com'])->assertOk();
    $this->postJson(route('login.code.send'), ['email' => 'naledi@example.com'])->assertStatus(429);
    Mail::assertSent(LoginCodeMail::class, 1);

    $first = sentCode();
    RateLimiter::clear('login-code:min:naledi@example.com');
    Mail::fake();
    $this->postJson(route('login.code.send'), ['email' => 'naledi@example.com']);
    $second = sentCode();

    if ($first !== $second) {
        $this->postJson(route('login.code.verify'), ['email' => 'naledi@example.com', 'code' => $first])->assertStatus(422);
    }
    $this->postJson(route('login.code.verify'), ['email' => 'naledi@example.com', 'code' => $second])->assertOk();
});

it('marks the email verified when someone signs in with a code', function () {
    $this->google->forceFill(['email_verified_at' => null])->save();

    $this->postJson(route('login.start'), ['email' => 'naledi@example.com']);
    $this->postJson(route('login.code.verify'), ['email' => 'naledi@example.com', 'code' => sentCode()])->assertOk();

    expect($this->google->fresh()->email_verified_at)->not->toBeNull();
});
