<?php

use App\Http\Controllers\ProgrammeEnrolmentController;
use App\Jobs\IssueProgrammeCertificates;
use App\Mail\{CertificateIssuedMail, ProgrammeEnrolledMail};
use App\Models\{Certificate, Client, Event, Organization, Participant, ProgrammeEnrolment, Session, User};
use App\Services\Sessions\ProgrammeProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-20 08:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Ministry of Health']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
    $this->admin->assignRole('org_admin');
    $this->actingAs($this->admin);

    $this->programme = Event::create(['organization_id' => $this->org->id, 'name' => 'Nurses training', 'venue' => 'Lesotho Sun', 'event_date' => '2026-10-19', 'is_public' => false, 'is_programme' => true, 'certificates_enabled' => true]);
    // Three days: yesterday (held), today (on now) and tomorrow.
    $this->days = collect(['2026-10-19' => 'reported', '2026-10-20' => 'active', '2026-10-21' => 'draft'])
        ->map(fn ($status, $date) => Session::create(['organization_id' => $this->org->id, 'event_id' => $this->programme->id, 'type' => 'training', 'title' => 'Nurses training', 'date' => $date, 'status' => $status, 'public_token' => 'tok' . substr($date, -2)]))
        ->values();
    $this->progress = app(ProgrammeProgress::class);
    $this->came = function (Client $client, int ...$days) {
        foreach ($days as $d) {
            Participant::create(['organization_id' => $this->org->id, 'event_id' => $this->programme->id, 'session_id' => $this->days[$d]->id, 'client_id' => $client->id, 'attended_at' => now(), 'source' => 'self']);
        }
    };
    $this->person = fn (string $name, ?string $email = null) => $this->progress->enrol($this->programme, ['full_name' => $name, 'email' => $email ?? strtolower(strtok($name, ' ')) . '@example.com'], 'desk')->client;
});

it('enrols people by its link, once each, and can be closed', function () {
    $url = $this->progress->link($this->programme);
    $token = $this->programme->fresh()->enrol_token;
    expect($url)->toEndWith("/enrol/{$token}")->and($this->progress->link($this->programme->fresh()))->toBe($url);

    auth()->logout();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Public/ProgrammeEnrol')
        ->where('programme.name', 'Nurses training')->where('programme.rule', 'every day')->has('programme.days', 3));

    $this->post(route('public.programme-enrol.submit', $token), ['full_name' => 'Mpho Sello', 'email' => 'mpho@example.com', 'institution' => 'Queen II Hospital'])
        ->assertRedirect()->assertSessionHas('enrolled', 'Mpho Sello');
    $this->post(route('public.programme-enrol.submit', $token), ['full_name' => 'Mpho Sello', 'email' => 'mpho@example.com', 'position' => 'Nurse']);

    $enrolment = ProgrammeEnrolment::sole();
    expect($enrolment->source)->toBe('self')->and($enrolment->institution)->toBe('Queen II Hospital')->and($enrolment->position)->toBe('Nurse');
    Mail::assertQueued(ProgrammeEnrolledMail::class, 1);

    $this->programme->update(['enrolment_open' => false]);
    $this->post(route('public.programme-enrol.submit', $token), ['full_name' => 'Lineo Ramoeletsi', 'email' => 'lineo@example.com'])->assertSessionHasErrors('full_name');
    expect(ProgrammeEnrolment::count())->toBe(1);
});

it('puts whoever checks in to a day on the programme, without undoing a withdrawal', function () {
    auth()->logout();
    $this->post(route('public.session-checkin.submit', 'tok20'), ['full_name' => 'Thabo Lebona', 'email' => 'thabo@example.com', 'institution' => 'Scott Hospital']);
    $enrolment = ProgrammeEnrolment::sole();
    expect($enrolment->source)->toBe('checkin')->and($enrolment->institution)->toBe('Scott Hospital');

    $enrolment->update(['withdrawn_at' => now()]);
    $this->post(route('public.session-checkin.submit', 'tok19'), ['full_name' => 'Thabo Lebona', 'email' => 'thabo@example.com']);
    expect($enrolment->fresh()->isWithdrawn())->toBeTrue();

    $this->actingAs($this->admin)->post(route('programmes.enrolments.restore', [$this->programme, $enrolment]))->assertRedirect();
    expect($enrolment->fresh()->isWithdrawn())->toBeFalse();
});

it('adds people by hand or from a pasted list', function () {
    expect(ProgrammeEnrolmentController::parseLine("Mpho Sello\tmpho@example.com\t+266 5800 0001\tQueen II Hospital\tNurse"))
        ->toBe(['full_name' => 'Mpho Sello', 'email' => 'mpho@example.com', 'phone' => '+266 5800 0001', 'institution' => 'Queen II Hospital', 'position' => 'Nurse'])
        ->and(ProgrammeEnrolmentController::parseLine('lineo@example.com, Lineo Ramoeletsi'))->toMatchArray(['full_name' => 'Lineo Ramoeletsi', 'email' => 'lineo@example.com', 'phone' => null])
        ->and(ProgrammeEnrolmentController::parseLine('  '))->toBeNull()
        ->and(ProgrammeEnrolmentController::parseLine('mpho@example.com'))->toBeNull();

    $this->post(route('programmes.enrolments.store', $this->programme), ['full_name' => 'Nthabiseng Letsie', 'phone' => '+26650000009'])->assertRedirect();
    $this->post(route('programmes.enrolments.import', $this->programme), ['lines' => "Mpho Sello, mpho@example.com\n\nLineo Ramoeletsi, lineo@example.com, Maluti Hospital\nNthabiseng Letsie, +26650000009, Ministry of Health"])
        ->assertSessionHas('status', '3 people enrolled.');

    expect(ProgrammeEnrolment::count())->toBe(3)   // Nthabiseng once, found by phone
        ->and(ProgrammeEnrolment::whereHas('client', fn ($q) => $q->where('full_name', 'Nthabiseng Letsie'))->value('institution'))->toBe('Ministry of Health');
    Mail::assertNothingQueued();   // the organizer's own adds aren't emailed
});

it('shows where each person stands against the rule', function () {
    $all = ($this->person)('Mpho Sello');
    ($this->came)($all, 0, 1, 2);
    $onTrack = ($this->person)('Lineo Ramoeletsi');
    ($this->came)($onTrack, 0, 1);
    $short = ($this->person)('Teboho Mokhele');
    ($this->came)($short, 1);
    ($this->person)('Nthabiseng Letsie');

    $board = collect($this->progress->board($this->programme)['people'])->keyBy('name');
    expect($board['Mpho Sello']['status'])->toBe('qualified')
        ->and($board['Lineo Ramoeletsi']['status'])->toBe('on_track')
        ->and($board['Lineo Ramoeletsi']['days']->all())->toBe(['came', 'came', 'ahead'])
        ->and($board['Teboho Mokhele']['status'])->toBe('short')
        ->and($board['Teboho Mokhele']['days']->all())->toBe(['missed', 'came', 'ahead'])
        ->and($board['Nthabiseng Letsie']['status'])->toBe('short');

    $this->programme->update(['certificate_min_days' => 2]);
    $board = collect($this->progress->board($this->programme)['people'])->keyBy('name');
    expect($board['Lineo Ramoeletsi']['status'])->toBe('qualified')->and($board['Teboho Mokhele']['status'])->toBe('on_track');

    $this->get(route('programmes.show', $this->programme))->assertInertia(fn (Assert $page) => $page
        ->where('board.required', 2)->has('board.people', 4)->where('board.counts.qualified', 2)->where('programme.enrolUrl', fn ($u) => str_contains($u, '/enrol/')));
});

it('issues certificates on its own when a day ends, to those who meet the rule', function () {
    $mpho = ($this->person)('Mpho Sello');
    ($this->came)($mpho, 0, 1);
    $lineo = ($this->person)('Lineo Ramoeletsi');
    ($this->came)($lineo, 0, 1);
    ProgrammeEnrolment::where('client_id', $lineo->id)->update(['withdrawn_at' => now()]);

    // Every day: not yet.
    $this->days[1]->update(['status' => 'completed']);
    expect(Certificate::count())->toBe(0);

    // The last day ends.
    ($this->came)($mpho, 2);
    ($this->came)($lineo, 2);
    $this->days[2]->update(['status' => 'completed']);

    expect(Certificate::pluck('client_id')->all())->toBe([$mpho->id]);   // not the withdrawn one
    Mail::assertQueued(CertificateIssuedMail::class, fn ($m) => $m->hasTo('mpho@example.com'));

    // Ending again doesn't issue twice.
    $this->days[2]->update(['status' => 'reported']);
    expect(Certificate::count())->toBe(1);
});

it('issues when the rule is loosened, each evening for days only checked in to, and not when certificates are off', function () {
    $mpho = ($this->person)('Mpho Sello');
    ($this->came)($mpho, 0);

    $this->patch(route('programmes.settings', $this->programme), ['certificate_min_days' => 1])
        ->assertSessionHas('status', 'Saved. 1 certificate was issued to people who now meet it.');

    $lineo = ($this->person)('Lineo Ramoeletsi');
    ($this->came)($lineo, 0);
    $this->artisan('sessions:issue-certificates')->assertSuccessful();
    expect(Certificate::count())->toBe(2);

    $this->programme->update(['certificates_enabled' => false]);
    $teboho = ($this->person)('Teboho Mokhele');
    ($this->came)($teboho, 0);
    (new IssueProgrammeCertificates($this->programme->id))->handle($this->progress);
    $this->post(route('programmes.certificates.issue', $this->programme))->assertStatus(400);
    expect(Certificate::count())->toBe(2);
});

it('makes the certificate PDF without Imagick', function () {
    $mpho = ($this->person)('Mpho Sello');
    $certificate = Certificate::create(['organization_id' => $this->org->id, 'event_id' => $this->programme->id, 'client_id' => $mpho->id, 'issued_at' => now()]);

    expect(substr($certificate->pdf()->output(), 0, 4))->toBe('%PDF')
        ->and(\App\Support\Qr::dataUri('https://example.com'))->toStartWith('data:image/');
});

it('keeps the programme to its organization', function () {
    $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $other->assignRole('org_admin');
    $enrolment = $this->progress->enrol($this->programme, ['full_name' => 'Mpho Sello', 'email' => 'mpho@example.com'], 'desk');

    $this->actingAs($other);
    $this->post(route('programmes.enrolments.store', $this->programme), ['full_name' => 'X'])->assertForbidden();
    $this->post(route('programmes.enrolments.withdraw', [$this->programme, $enrolment]))->assertForbidden();
    $this->patch(route('programmes.settings', $this->programme), ['certificate_min_days' => 1])->assertForbidden();
});
