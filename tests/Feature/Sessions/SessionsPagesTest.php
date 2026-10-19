<?php

use App\Models\{Event, Organization, Participant, PaymentSession, Session, SessionPackage, User};
use Illuminate\Support\Facades\{Bus, Mail};
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Palesa Mokoena']);
    $this->user->assignRole('org_admin');
    $this->actingAs($this->user);
});

function trackedSession(Organization $org, array $attributes = []): Session
{
    $event = Event::create(['organization_id' => $org->id, 'name' => 'Board meeting', 'event_date' => now(), 'is_public' => false]);

    return Session::create($attributes + [
        'organization_id' => $org->id, 'event_id' => $event->id, 'type' => 'meeting', 'title' => 'Board meeting',
        'date' => now()->toDateString(), 'start_time' => '09:00', 'status' => 'draft',
        'public_token' => \Illuminate\Support\Str::random(32), 'session_code' => 'BRD123',
    ]);
}

it('shows the desk with sessions sorted into their columns', function () {
    trackedSession($this->org);
    trackedSession($this->org, ['title' => 'Q3 review', 'status' => 'reported', 'session_code' => 'Q3R001', 'public_token' => 'x' . \Illuminate\Support\Str::random(31)]);

    $this->get(route('sessions.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Sessions/Index')
        ->has('columns.upcoming', 1)
        ->has('columns.ready', 1)
        ->where('columns.upcoming.0.starts', fn ($s) => str_contains($s, '09:00'))
        ->where('sessionsArea.needsReview', 1)
        ->where('sessionsArea.organization', $this->org->name));
});

it('sends people without an organization to their own home', function () {
    $super = User::factory()->create(['organization_id' => null]);
    $super->assignRole('super_admin');

    $this->actingAs($super)->get(route('sessions.index'))->assertRedirect(route('ventiq.money.index'));
    $this->get(route('programmes.index'))->assertRedirect(route('ventiq.money.index'));
});

it('adds a session scheduled from a programme to that programme', function () {
    $programme = Event::create(['organization_id' => $this->org->id, 'name' => 'Leadership course', 'event_date' => now(), 'is_public' => false, 'is_programme' => true]);

    $this->get(route('sessions.create', ['event' => $programme->id]))
        ->assertInertia(fn (Assert $page) => $page->component('Sessions/Create')->where('programme.id', $programme->id));

    $this->post(route('sessions.store'), [
        'title' => 'Day one', 'type' => 'training', 'date' => now()->addDay()->toDateString(), 'start_time' => '10:30',
        'programme_id' => $programme->id, 'people' => [['name' => 'Thabo Lebona', 'role' => 'facilitator', 'presenting' => true]],
    ])->assertRedirect(route('sessions.index'));

    $session = Session::where('title', 'Day one')->firstOrFail();
    expect($session->event_id)->toBe($programme->id)
        ->and($session->segments()->count())->toBe(1);
});

it('opens the live console with its people and their saved notes, and saves a topic', function () {
    $session = trackedSession($this->org);
    $segment = $session->segments()->create(['presenter_name' => 'Thabo Lebona', 'order' => 0, 'status' => 'upcoming', 'is_presenting' => true, 'raw_log' => [['time' => '09:01:00', 'text' => 'Budget is up']]]);
    $segment->insights()->create(['category' => 'decision', 'content' => 'Budget approved', 'display_order' => 0]);

    $this->get(route('sessions.show', $session))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Sessions/Show')
        ->where('session.checkinUrl', route('public.session-checkin.form', $session->public_token))
        ->where('segments.0.logs.0.text', 'Budget is up')
        ->where('segments.0.insights.0.text', 'Budget approved'));

    $this->patchJson(route('sessions.segments.update', [$session, $segment]), ['title' => 'The budget'])->assertOk();
    expect($segment->fresh()->title)->toBe('The budget');
});

it("keeps one organization out of another's sessions", function () {
    $other = trackedSession(Organization::factory()->create());

    $this->get(route('sessions.show', $other))->assertForbidden();
    $this->get(route('sessions.report', $other))->assertForbidden();
    $this->get(route('sessions.checkin', $other))->assertForbidden();
});

it('shows a report, and checks for it while it is being written', function () {
    $session = trackedSession($this->org, ['status' => 'reported', 'session_report' => 'Summary of the meeting']);

    $this->get(route('sessions.report', $session))->assertInertia(fn (Assert $page) => $page
        ->component('Sessions/Report')->where('session.report', 'Summary of the meeting')->where('session.failed', false));

    $this->get(route('sessions.reports'))->assertInertia(fn (Assert $page) => $page->component('Sessions/Reports')->has('reports.data', 1));
});

it('checks someone in by hand with an email, the same way they would themselves', function () {
    $session = trackedSession($this->org);

    $this->post(route('sessions.checkin.store', $session), ['full_name' => 'Lineo Ramoeletsi', 'email' => 'lineo@example.com', 'institution' => 'NUL'])
        ->assertRedirect(route('sessions.checkin', $session));

    $this->get(route('sessions.checkin', $session))->assertInertia(fn (Assert $page) => $page
        ->component('Sessions/Checkin')
        ->where('participants.0.email', 'lineo@example.com')
        ->where('participants.0.institution', 'NUL'));
});

it('lets someone without a scanner join by the code on the pass', function () {
    $session = trackedSession($this->org);
    auth()->logout();

    $this->get(route('public.session-join', ['code' => 'brd123']))->assertRedirect(route('public.session-checkin.form', $session->public_token));
    $this->get(route('public.session-join', ['code' => 'NOPE99']))
        ->assertInertia(fn (Assert $page) => $page->component('Public/SessionJoin')->where('error', fn ($e) => str_contains($e, 'NOPE99')));
});

it('checks people in themselves, and updates them on a second go with the same email', function () {
    $session = trackedSession($this->org);
    auth()->logout();
    $form = route('public.session-checkin.form', $session->public_token);

    $this->post(route('public.session-checkin.submit', $session->public_token), ['full_name' => 'Lineo R', 'email' => 'lineo@example.com'])
        ->assertRedirect($form);
    $this->get($form)->assertInertia(fn (Assert $page) => $page->component('Public/SessionCheckin')->where('checkedIn', 'Lineo R'));

    $this->post(route('public.session-checkin.submit', $session->public_token), ['full_name' => 'Lineo Ramoeletsi', 'email' => 'lineo@example.com', 'position' => 'Treasurer']);

    expect(Participant::where('session_id', $session->id)->count())->toBe(1)
        ->and(Participant::where('session_id', $session->id)->first()->position)->toBe('Treasurer');

    // No lookup of other people's details by phone or email.
    $this->get($form . '?search=lineo@example.com')->assertInertia(fn (Assert $page) => $page->missing('existing'));
});

it('keeps programmes for the Business plan and shows one with its sessions', function () {
    $this->get(route('programmes.index'))->assertInertia(fn (Assert $page) => $page->component('Programmes/Index')->where('canCreate', false));
    $this->get(route('programmes.create'))->assertRedirect(route('programmes.index'));

    app(\App\Services\SessionPackageService::class)->changePlan(organizationId: $this->org->id, tier: 'business', sessionsIncluded: 6, whatsappIncluded: 150, smsIncluded: 150, pricePaid: 1200);

    $this->post(route('programmes.store'), ['name' => 'Leadership course', 'start_date' => '2026-11-02', 'end_date' => '2026-11-04', 'certificates_enabled' => true])
        ->assertRedirect();
    $programme = Event::where('name', 'Leadership course')->firstOrFail();

    $this->get(route('programmes.show', $programme))->assertInertia(fn (Assert $page) => $page
        ->component('Programmes/Show')->where('programme.dates', '02 Nov – 04 Nov 2026')->where('programme.certificates', true));
    $this->get(route('programmes.report', $programme))->assertInertia(fn (Assert $page) => $page
        ->component('Programmes/Report')->where('reported', 0)->where('programme.generating', false));
});

it('shows the plan, and a payment page for a bigger plan or more sessions', function () {
    $this->get(route('sessions.plan'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Sessions/Plan')
        ->where('plan.tier', 'free')
        ->where('tiers', fn ($t) => collect($t)->pluck('key')->all() === ['team', 'business']));

    $this->get(route('organization.session-plan.payment', ['type' => 'plan', 'tier' => 'team']))
        ->assertInertia(fn (Assert $page) => $page->component('Sessions/PlanPayment')->where('purchase.amount', 500));
    $this->get(route('organization.session-plan.payment', ['type' => 'payg', 'quantity' => 3]))
        ->assertInertia(fn (Assert $page) => $page->where('purchase.amount', 400));
    $this->get(route('organization.session-plan.payment', ['type' => 'plan', 'tier' => 'enterprise']))->assertNotFound();
});

it("doesn't tell another organization how a plan payment went", function () {
    $payment = PaymentSession::create([
        'payable_type' => 'session_package', 'payable_id' => $this->org->id, 'organization_id' => $this->org->id,
        'method' => 'ecocash', 'amount' => 500, 'status' => 'pending', 'mobile_number' => '+26658000000',
    ]);

    $this->getJson(route('paylesotho.status', $payment))->assertOk()->assertJson(['status' => 'pending']);
    $this->actingAs(User::factory()->create(['organization_id' => Organization::factory()->create()->id]))
        ->getJson(route('paylesotho.status', $payment))->assertNotFound();
});
