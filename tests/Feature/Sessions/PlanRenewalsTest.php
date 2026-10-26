<?php

use App\Mail\SessionPlanRenewalMail;
use App\Models\{Organization, PaymentSession, SessionPackage, User};
use App\Services\SessionPackageService;
use App\Services\SessionQuotaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-11-02 09:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'email' => 'palesa@example.com']);
    $this->admin->assignRole('org_admin');
    $this->buy = fn (float $price = 500) => app(SessionPackageService::class)->changePlan($this->org->id, 'team', 3, 50, 50, $price);
    $this->plan = fn () => app(SessionQuotaService::class)->currentPlan($this->org->fresh());
    $this->stages = fn () => Mail::queued(SessionPlanRenewalMail::class)->map->stage->all();
});

it('asks to renew a few days before the month ends, once', function () {
    ($this->buy)();
    expect(($this->plan)())->paid_until->toDateString()->toBe('2026-12-02');

    $this->travelTo(Carbon::parse('2026-11-28 08:15'));
    $this->artisan('sessions:renewals')->assertSuccessful();
    expect(($this->stages)())->toBe([]);

    $this->travelTo(Carbon::parse('2026-11-29 08:15'));
    $this->artisan('sessions:renewals');
    $this->artisan('sessions:renewals');
    expect(($this->stages)())->toBe(['due']);
    Mail::assertQueued(SessionPlanRenewalMail::class, fn ($m) => $m->hasTo('palesa@example.com')
        && str_contains($m->envelope()->subject, 'renews on 2 Dec'));
});

it('runs an unpaid month on grace, then goes back to Free with nothing deleted', function () {
    ($this->buy)();
    ($this->plan)()->update(['sessions_used' => 2]);

    // The month ends unpaid: still Team, with this month's sessions, for three days.
    $this->travelTo(Carbon::parse('2026-12-03 08:15'));
    $this->artisan('sessions:renewals');
    $plan = ($this->plan)();
    expect($plan->tier)->toBe('team')->and($plan->sessions_used)->toBe(0)->and($plan->isPaid())->toBeFalse()
        ->and($plan->graceEndsAt()->toDateString())->toBe('2026-12-05')
        ->and(($this->stages)())->toBe(['grace']);

    $this->actingAs($this->admin)->get(route('sessions.plan'))
        ->assertInertia(fn (Assert $page) => $page->where('plan.renewal.stage', 'grace')->where('plan.renewal.by', '5 Dec 2026'));

    // Past it: Free, and they're told.
    $this->travelTo(Carbon::parse('2026-12-05 09:30'));
    $this->artisan('sessions:renewals');
    expect(($this->plan)()->tier)->toBe('free')
        ->and(SessionPackage::where('tier', 'team')->sole()->status)->toBe('lapsed')
        ->and(($this->stages)())->toBe(['grace', 'lapsed']);
    // Each email reads right.
    $team = SessionPackage::where('tier', 'team')->sole();
    expect((new SessionPlanRenewalMail($team, $this->org, 'lapsed'))->render())->toContain('Nothing has been deleted')
        ->and((new SessionPlanRenewalMail($team, $this->org, 'due'))->render())->toContain('M500.00')->toContain('within 24 hours');
});

it('keeps the plan when the month is paid on grace, from where it was paid to', function () {
    ($this->buy)();
    $this->travelTo(Carbon::parse('2026-12-04 10:00'));
    ($this->plan)();
    ($this->buy)();

    $plan = ($this->plan)();
    expect(SessionPackage::where('tier', 'team')->count())->toBe(1)
        ->and($plan->paid_until->toDateString())->toBe('2027-01-02')
        ->and($plan->isPaid())->toBeTrue()
        ->and((float) $plan->price_paid)->toBe(1000.0);

    $this->travelTo(Carbon::parse('2026-12-10 08:15'));
    $this->artisan('sessions:renewals');
    expect(($this->plan)()->tier)->toBe('team');
});

it('takes a month ahead, and needs nothing at the end of this one', function () {
    ($this->buy)();
    ($this->buy)();
    $this->travelTo(Carbon::parse('2026-12-03 08:15'));
    $this->artisan('sessions:renewals');

    expect(($this->plan)())->tier->toBe('team')->and(($this->plan)()->isPaid())->toBeTrue()
        ->and(($this->stages)())->toBe([]);
});

it('keeps the plan while a bank transfer for it waits to be confirmed', function () {
    ($this->buy)();
    PaymentSession::create([
        'payable_type' => 'session_package', 'payable_id' => $this->org->id, 'organization_id' => $this->org->id, 'gateway' => 'bank',
        'client_reference' => 'VQ0001-TEAM', 'amount' => 500, 'status' => 'pending', 'payment_method' => 'bank_transfer',
        'purchase_meta' => ['type' => 'plan', 'tier' => 'team'],
    ]);

    $this->travelTo(Carbon::parse('2026-12-08 08:15'));
    $this->artisan('sessions:renewals');
    expect(($this->plan)()->tier)->toBe('team');
    $this->actingAs($this->admin)->get(route('sessions.plan'))->assertInertia(fn (Assert $page) => $page->where('plan.renewal.waiting', true));
});

it('just carries on a plan VENTIQ gave at no charge', function () {
    ($this->buy)(0);
    $this->travelTo(Carbon::parse('2027-02-10 08:15'));
    $this->artisan('sessions:renewals');

    expect(($this->plan)())->tier->toBe('team')->and(($this->plan)()->renews())->toBeFalse()
        ->and(($this->plan)()->period_start->toDateString())->toBe('2027-02-02')
        ->and(($this->stages)())->toBe([]);
});
