<?php

use App\Models\{ActionPoint, Client, Organization, Participant, Session, SessionSeries, User};
use App\Services\AI\Prompts\SegmentSummaryPrompt;
use App\Services\Sessions\MinutesBuilder;
use App\Support\SessionType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Palesa Mokoena', 'email' => 'palesa@example.com']);
    $this->admin->assignRole('org_admin');
    $this->actingAs($this->admin);

    // A held meeting: two agenda items, one led by someone.
    $this->meeting = function (array $extra = []) {
        $session = Session::create($extra + [
            'organization_id' => $this->org->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => '2026-10-20', 'start_time' => '09:00',
            'location' => 'Boardroom', 'status' => 'reported', 'session_report' => 'Report',
            'meta' => ['chair' => 'Palesa Mokoena', 'secretary' => 'Lineo Ramoeletsi'],
        ]);
        $session->segments()->create(['title' => 'Budget', 'presenter_name' => 'Thabo Lebona', 'order' => 0, 'status' => 'completed', 'is_presenting' => true,
            'raw_log' => [['time' => '09:01', 'text' => 'Budget approved']],
            'ai_summary' => ['summary' => 'The Treasurer presented the budget. Members noted the rise in fuel costs.', 'decisions' => "• The budget was approved.\n• Fuel to be reviewed monthly.", 'action_items' => 'None identified.', 'follow_ups' => 'None identified.']]);
        $session->segments()->create(['title' => 'Logistics', 'presenter_name' => 'Logistics', 'order' => 1, 'status' => 'completed', 'is_presenting' => true,
            'raw_log' => [['time' => '09:20', 'text' => 'Trucks late']],
            'ai_summary' => ['summary' => 'It was reported that deliveries ran late.', 'decisions' => 'None identified.']]);

        return $session;
    };
});

it('knows which sessions are minuted', function () {
    expect(SessionType::takesMinutes('meeting'))->toBeTrue()
        ->and(SessionType::takesMinutes('board'))->toBeTrue()
        ->and(SessionType::takesMinutes('committee'))->toBeTrue()
        ->and(SessionType::takesMinutes('presentation'))->toBeFalse()
        ->and(collect(SessionType::forForm())->firstWhere('key', 'meeting')['minutes'])->toBeTrue();

    $prompt = (new SegmentSummaryPrompt)->with(['topic' => 'Budget', 'raw_notes' => 'x', 'sections' => SessionType::sections('meeting'), 'agenda' => true, 'lead' => 'Thabo Lebona'])->build();
    expect($prompt)->toContain('Agenda item: Budget')->toContain('Led by: Thabo Lebona')->toContain('past tense, third person');
});

it('schedules a meeting with its chair, secretary, venue and agenda', function () {
    $this->post(route('sessions.store'), [
        'title' => 'Board meeting', 'type' => 'board', 'date' => '2026-10-28', 'start_time' => '10:00',
        'chair' => 'Palesa Mokoena', 'secretary' => 'Lineo Ramoeletsi', 'location' => 'Boardroom',
        'people' => [['title' => 'Finance report', 'name' => 'Thabo Lebona'], ['title' => 'Staffing', 'name' => '']],
    ])->assertRedirect();

    $session = Session::where('title', 'Board meeting')->firstOrFail();
    $items = $session->segments()->orderBy('order')->get();
    expect($session->meta['chair'])->toBe('Palesa Mokoena')
        ->and($session->meta['secretary'])->toBe('Lineo Ramoeletsi')
        ->and($session->location)->toBe('Boardroom')
        ->and($items->pluck('title')->all())->toBe(['Finance report', 'Staffing'])
        ->and($items[0]->lead())->toBe('Thabo Lebona')
        ->and($items[1]->lead())->toBeNull()
        ->and($items[1]->presenter_name)->toBe('Staffing');

    $this->get(route('sessions.show', $session))->assertInertia(fn (Assert $page) => $page
        ->where('session.agenda', true)->where('session.chair', 'Palesa Mokoena')
        ->where('segments.0.topic', 'Finance report')->where('segments.0.name', 'Thabo Lebona'));

    // An item added on the day.
    $this->postJson("/sessions/{$session->id}/segments", ['title' => 'Any risks'])
        ->assertOk()->assertJsonPath('segment.topic', 'Any risks')->assertJsonPath('segment.name', 'Any risks');
});

it("gives a series' meetings its chair, secretary, venue and standing agenda", function () {
    $this->post(route('sessions.store'), [
        'title' => 'Weekly ops', 'type' => 'meeting', 'date' => '2026-10-26', 'start_time' => '09:00', 'repeat' => 'weekly',
        'chair' => 'Palesa Mokoena', 'secretary' => 'Lineo Ramoeletsi', 'location' => 'Room 2',
        'people' => [['title' => 'Safety', 'name' => 'Thabo Lebona']],
    ])->assertRedirect();

    $series = SessionSeries::firstOrFail();
    expect($series->meta)->toMatchArray(['chair' => 'Palesa Mokoena', 'location' => 'Room 2']);
    $series->sessions->each(fn ($s) => expect($s->meta['secretary'])->toBe('Lineo Ramoeletsi')
        ->and($s->location)->toBe('Room 2')
        ->and($s->segments()->first()->title)->toBe('Safety'));

    $this->put(route('sessions.series.update', $series), [
        'title' => 'Weekly ops', 'type' => 'meeting', 'chair' => 'Thabo Lebona', 'secretary' => 'Lineo Ramoeletsi', 'location' => 'Room 3',
        'people' => [['title' => 'Safety', 'name' => ''], ['title' => 'Fleet', 'name' => 'Lineo Ramoeletsi']],
    ])->assertRedirect();

    $next = $series->sessions()->first();
    expect($next->meta['chair'])->toBe('Thabo Lebona')
        ->and($next->location)->toBe('Room 3')
        ->and($next->segments()->orderBy('order')->pluck('title')->all())->toBe(['Safety', 'Fleet']);
});

it('assembles the minutes from the notes, the action points and the series', function () {
    $series = SessionSeries::create(['organization_id' => $this->org->id, 'title' => 'Ops meeting', 'type' => 'meeting', 'frequency' => 'weekly', 'starts_on' => '2026-10-13', 'scheduled_until' => '2026-10-27']);
    $previous = Session::create(['organization_id' => $this->org->id, 'series_id' => $series->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => '2026-10-13', 'status' => 'reported', 'reviewed_at' => now()]);
    $session = ($this->meeting)(['series_id' => $series->id, 'parent_session_id' => $previous->id]);
    Session::create(['organization_id' => $this->org->id, 'series_id' => $series->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => '2026-10-27', 'start_time' => '09:00', 'status' => 'draft']);
    $carried = ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $previous->id, 'series_id' => $series->id, 'task' => 'Fix the gate', 'status' => 'open', 'source' => 'manual']);
    ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $session->id, 'series_id' => $series->id, 'task' => 'Send the quotes', 'owner_name' => 'Thabo Lebona', 'status' => 'open', 'source' => 'ai']);
    $event = \App\Models\Event::create(['organization_id' => $this->org->id, 'kind' => \App\Models\Event::KIND_SESSION, 'name' => 'Ops meeting', 'event_date' => '2026-10-20 09:00', 'is_public' => false]);
    $session->update(['event_id' => $event->id]);
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Mpho Sello']);
    Participant::create(['organization_id' => $this->org->id, 'event_id' => $event->id, 'session_id' => $session->id, 'client_id' => $client->id, 'attended_at' => now()]);

    $minutes = app(MinutesBuilder::class)->build($session->fresh());

    expect($minutes['heading'])->toMatchArray(['organization' => 'Maloti Water', 'venue' => 'Boardroom', 'chair' => 'Palesa Mokoena', 'time' => '09:00', 'date' => 'Tuesday, 20 October 2026'])
        ->and($minutes['present'])->toBe(['Palesa Mokoena (Chair)', 'Lineo Ramoeletsi (Secretary)', 'Thabo Lebona', 'Mpho Sello'])
        ->and($minutes['previous'])->toMatchArray(['id' => $previous->id, 'confirmed' => true])
        ->and(collect($minutes['arising'])->pluck('id')->all())->toBe([$carried->id])
        ->and($minutes['items'][0])->toMatchArray(['title' => 'Budget', 'lead' => 'Thabo Lebona', 'discussion' => 'The Treasurer presented the budget. Members noted the rise in fuel costs.', 'decisions' => ['The budget was approved.', 'Fuel to be reviewed monthly.']])
        ->and($minutes['items'][1])->toMatchArray(['title' => 'Logistics', 'lead' => null, 'decisions' => []])
        ->and(collect($minutes['actions'])->pluck('task')->all())->toBe(['Send the quotes'])
        ->and($minutes['next'])->toBe('Tuesday, 27 October 2026 at 09:00')
        ->and($minutes['confirmed'])->toBeNull();

    $this->get(route('sessions.report', $session))->assertInertia(fn (Assert $page) => $page
        ->component('Sessions/Report')->where('session.takesMinutes', true)->where('minutes.items.0.title', 'Budget')
        ->where('segments.0.name', 'Budget')->where('segments.0.role', 'Led by Thabo Lebona'));
});

it('keeps what is changed by hand, and only for its own items', function () {
    $session = ($this->meeting)();
    $other = ($this->meeting)(['title' => 'Other']);
    $budget = $session->segments()->where('title', 'Budget')->first();

    $this->patch(route('sessions.minutes.update', $session), ['apologies' => "Mpho Sello\nNthabiseng Letsie", 'venue' => 'Hall B'])->assertRedirect();
    $this->patch(route('sessions.minutes.update', $session), ['items' => [
        $budget->id => ['decisions' => '• The budget was approved as amended.'],
        $other->segments()->first()->id => ['discussion' => 'Not mine'],
    ]])->assertRedirect();
    $this->patch(route('sessions.minutes.update', $session), ['aob' => 'The chair thanked members.', 'next' => ''])->assertRedirect();

    $minutes = app(MinutesBuilder::class)->build($session->fresh());
    expect($minutes['apologies'])->toBe(['Mpho Sello', 'Nthabiseng Letsie'])
        ->and($minutes['heading']['venue'])->toBe('Hall B')
        ->and($minutes['items'][0]['decisions'])->toBe(['The budget was approved as amended.'])
        ->and($minutes['items'][0]['discussion'])->toStartWith('The Treasurer')   // untouched: still the write-up
        ->and($minutes['aob'])->toBe('The chair thanked members.')
        ->and($minutes['next'])->toBeNull()
        ->and(array_keys($session->fresh()->minutes['items']))->toBe([$budget->id])
        ->and($other->fresh()->minutes)->toBeNull();

    // Not for a presentation, and not for a viewer.
    $talk = Session::create(['organization_id' => $this->org->id, 'type' => 'presentation', 'title' => 'Talks', 'date' => '2026-10-20', 'status' => 'reported']);
    $this->patch(route('sessions.minutes.update', $talk), ['aob' => 'x'])->assertStatus(409);
    $viewer = User::factory()->create(['organization_id' => $this->org->id]);
    $viewer->assignRole('viewer');
    $this->actingAs($viewer)->patch(route('sessions.minutes.update', $session), ['aob' => 'x'])->assertForbidden();
});

it('confirms the minutes in the name of whoever reviews them, and downloads them as a PDF', function () {
    $session = ($this->meeting)();

    $this->post(route('sessions.report.review', $session))->assertRedirect();
    $minutes = app(MinutesBuilder::class)->build($session->fresh());
    expect($minutes['confirmed'])->toBe(['by' => 'Palesa Mokoena', 'date' => '21 October 2026']);

    $response = $this->get(route('sessions.report.pdf', $session))->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('minutes-ops-meeting-2026-10-20.pdf');

    $html = view('sessions.minutes-pdf', ['minutes' => $minutes])->render();
    expect($html)->toContain('Minutes: Ops meeting')->toContain('The budget was approved.')->toContain('Led by Thabo Lebona')->toContain('Confirmed by Palesa Mokoena');
});
