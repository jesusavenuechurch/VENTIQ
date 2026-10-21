<?php

use App\Models\{ActionPoint, Client, Event, Organization, Participant, Session, User};
use App\Services\Sessions\SessionNumbers;
use Database\Seeders\Demo\LiveDemoSession;
use Database\Seeders\SessionsDemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Palesa Mokoena']);
    $this->admin->assignRole('org_admin');
    $this->actingAs($this->admin);

    // A held workshop with check-in: two parts, five people in.
    $event = Event::create(['organization_id' => $this->org->id, 'kind' => Event::KIND_SESSION, 'name' => 'Data workshop', 'event_date' => '2026-10-20 09:00', 'is_public' => false]);
    $this->session = Session::create([
        'organization_id' => $this->org->id, 'event_id' => $event->id, 'type' => 'workshop', 'title' => 'Data workshop', 'date' => '2026-10-20',
        'start_time' => '09:00', 'status' => 'reported', 'session_report' => 'Report', 'location' => 'Hall',
        'meta' => ['expected_participants' => 8, 'expected_duration_minutes' => 60],
    ]);
    foreach ([['Registers', 'Thabo Lebona', '09:00', '09:40'], ['Review', 'Mpho Sello', '09:40', '10:10']] as $i => [$title, $by, $from, $to]) {
        $this->session->segments()->create(['title' => $title, 'presenter_name' => $by, 'order' => $i, 'status' => 'completed', 'is_presenting' => true,
            'started_at' => "2026-10-20 {$from}", 'ended_at' => "2026-10-20 {$to}", 'raw_log' => [['time' => "{$from}:00", 'text' => 'notes']],
            'ai_summary' => ['summary' => "{$title} were covered. More detail.", 'key_points' => "• One\n• Two", 'follow_ups' => 'None identified.']]);
    }
    foreach ([['self', '08:50', 'Quthing Hospital', 'quthing'], ['self', '08:58', 'Quthing Hospital', 'quthing'], ['scanner', '09:05', 'Mphaki Clinic', 'quthing'], ['walk_in', '09:20', null, 'mafeteng'], ['self', '09:31', 'Mphaki Clinic', 'mafeteng']] as $i => [$how, $at, $from, $district]) {
        $client = Client::create(['organization_id' => $this->org->id, 'full_name' => "Person {$i}", 'email' => "p{$i}@example.com"]);
        Participant::create(['organization_id' => $this->org->id, 'event_id' => $event->id, 'session_id' => $this->session->id, 'client_id' => $client->id,
            'source' => $how, 'attended_at' => "2026-10-20 {$at}", 'institution' => $from, 'district' => $district]);
    }
    // Registered, didn't come.
    Participant::create(['organization_id' => $this->org->id, 'event_id' => $event->id, 'session_id' => $this->session->id,
        'client_id' => Client::create(['organization_id' => $this->org->id, 'full_name' => 'Absent', 'email' => 'absent@example.com'])->id, 'source' => 'pre_registered']);
});

it('counts who came, how, from where and when, and how the time went', function () {
    $n = app(SessionNumbers::class)->for($this->session);

    expect($n['attendance'])->toMatchArray(['checkedIn' => 5, 'expected' => 8, 'rate' => 63, 'noShows' => 1, 'late' => 2])
        ->and($n['attendance']['how'])->toBe([['label' => 'Self (QR or code)', 'count' => 3], ['label' => 'Scanner', 'count' => 1], ['label' => 'Desk', 'count' => 1]])
        ->and($n['attendance']['from'][0])->toBe(['label' => 'Quthing Hospital', 'count' => 2])
        ->and(collect($n['attendance']['from'])->pluck('label'))->toContain('Not given')
        ->and($n['attendance']['districts'])->toBe([['label' => 'Quthing', 'count' => 3], ['label' => 'Mafeteng', 'count' => 2]])
        ->and(collect($n['attendance']['arrivals'])->pluck('count', 'label')->all())->toBe(['08:45' => 2, '09:00' => 1, '09:15' => 1, '09:30' => 1])
        ->and($n['time'])->toMatchArray(['total' => 70, 'planned' => 60, 'started' => '09:00', 'ended' => '10:10'])
        ->and(collect($n['time']['items'])->pluck('minutes')->all())->toBe([40, 30])
        ->and($n['notes'])->toBe(['lines' => 2, 'items' => 2, 'of' => 2])
        ->and($n['programme'])->toBeNull();
});

it("counts a meeting's decisions and its action points, agreed and carried in", function () {
    $series = \App\Models\SessionSeries::create(['organization_id' => $this->org->id, 'title' => 'Ops', 'type' => 'meeting', 'frequency' => 'weekly', 'starts_on' => '2026-10-13']);
    $last = Session::create(['organization_id' => $this->org->id, 'series_id' => $series->id, 'type' => 'meeting', 'title' => 'Ops', 'date' => '2026-10-13', 'status' => 'reported']);
    $meeting = Session::create(['organization_id' => $this->org->id, 'series_id' => $series->id, 'parent_session_id' => $last->id, 'type' => 'meeting', 'title' => 'Ops', 'date' => '2026-10-20', 'status' => 'reported']);
    $meeting->segments()->create(['title' => 'Budget', 'presenter_name' => 'Thabo', 'order' => 0, 'status' => 'completed', 'is_presenting' => true,
        'ai_summary' => ['summary' => 'Discussed.', 'decisions' => "• Approved.\n• Review monthly.", 'action_items' => 'None identified.', 'follow_ups' => 'None identified.']]);
    $point = fn (Session $s, array $extra) => ActionPoint::create($extra + ['organization_id' => $this->org->id, 'session_id' => $s->id, 'series_id' => $series->id, 'task' => 'Task', 'status' => 'open', 'source' => 'manual']);
    $point($last, ['due_date' => '2026-10-15']);                                                       // overdue
    $point($last, ['due_date' => '2026-10-30']);                                                       // open
    $point($last, ['status' => 'done', 'done_in_session_id' => $meeting->id, 'done_at' => now()]);      // done here
    $point($meeting, ['owner_name' => 'Thabo Lebona', 'status' => 'proposed']);
    $point($meeting, ['owner_name' => 'Thabo Lebona']);

    $o = app(SessionNumbers::class)->for($meeting)['outcomes'];
    expect($o['decisions'])->toBe(2)
        ->and($o['agreed'])->toBe(['total' => 2, 'proposed' => 1, 'done' => 0, 'overdue' => 0])
        ->and($o['carried'])->toBe(['total' => 3, 'doneHere' => 1, 'open' => 2, 'overdue' => 1])
        ->and($o['owners'])->toBe([['label' => 'Thabo Lebona', 'count' => 2]]);
});

it('shows the numbers on the report, and makes the full report PDF', function () {
    $this->get(route('sessions.report', $this->session))->assertInertia(fn (Assert $page) => $page
        ->where('numbers.attendance.checkedIn', 5)->where('segments.0.topic', 'Registers'));

    $response = $this->get(route('sessions.report.full', $this->session))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and($response->headers->get('content-disposition'))->toContain('full-report-data-workshop-2026-10-20.pdf');

    // Viewers can read it; it's the organization's own; only once held.
    $viewer = User::factory()->create(['organization_id' => $this->org->id]);
    $viewer->assignRole('viewer');
    $this->actingAs($viewer)->get(route('sessions.report.full', $this->session))->assertOk();
    $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $other->assignRole('org_admin');
    $this->actingAs($other)->get(route('sessions.report.full', $this->session))->assertForbidden();
    $this->session->update(['status' => 'active']);
    $this->actingAs($this->admin)->get(route('sessions.report.full', $this->session))->assertNotFound();
});

it('makes a full report of minutes with the register for a minuted, tracked meeting', function () {
    $this->session->update(['type' => 'committee', 'meta' => ['chair' => 'Palesa Mokoena']]);
    $html = view('sessions.full-report-pdf', [
        'session' => $this->session->fresh(['segments', 'organization', 'event']), 'type' => 'Committee',
        'numbers' => app(SessionNumbers::class)->for($this->session->fresh()),
        'minutes' => app(\App\Services\Sessions\MinutesBuilder::class)->build($this->session->fresh()),
        'summary' => [['label' => 'Registers', 'text' => 'Registers were covered. More detail. And more.']],
        'items' => $this->session->segments()->orderBy('order')->get(),
        'register' => app(\App\Services\Sessions\ParticipantList::class)->forSession($this->session->fresh()),
        'logo' => null, 'produced' => '21 October 2026, 08:00', 'by' => 'Palesa Mokoena',
    ])->render();

    expect($html)->toContain('Minutes: Data workshop')->toContain('Attendance register')->toContain('Person 4')
        ->toContain('Registers were covered. More detail.')->not->toContain('And more.')
        ->not->toContain('p1@example.com');   // no emails or phones in the shared report
});

it('makes the demo session happening now again, without doubling it', function () {
    $this->seed(SessionsDemoSeeder::class);
    $trust = Organization::where('email', SessionsDemoSeeder::TRUST_EMAIL)->sole();
    $this->travelTo(Carbon::parse('2026-10-22 14:00'));

    $this->artisan('sessions:demo-live')->assertSuccessful();

    $live = Session::where('organization_id', $trust->id)->where('title', LiveDemoSession::TITLE)->sole();
    expect($live->status)->toBe('active')->and($live->date->toDateString())->toBe('2026-10-22')->and($live->start_time)->toStartWith('13:35')
        ->and($live->segments()->where('status', 'active')->count())->toBe(1)
        ->and(Participant::where('session_id', $live->id)->whereNotNull('attended_at')->count())->toBe(11)
        ->and(Session::withTrashed()->where('title', LiveDemoSession::TITLE)->count())->toBe(1);

    $this->actingAs(User::where('email', 'demo.admin@example.com')->sole())
        ->get(route('sessions.index'))->assertOk();
});
