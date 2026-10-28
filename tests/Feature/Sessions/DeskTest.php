<?php

use App\Models\{ActionPoint, Organization, Session, User};
use Illuminate\Support\Facades\{Bus, Mail};
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Thabo Lebona']);
    $this->user->assignRole('org_admin');
    $this->actingAs($this->user);
    $this->session = Session::create(['organization_id' => $this->org->id, 'type' => 'meeting', 'title' => 'Board meeting', 'date' => today()->subWeek(), 'status' => 'reported']);
});

it('shows action points due: overdue first, then the coming week, not later ones or other organizations', function () {
    $point = fn (string $task, $due, string $status = 'open', ?int $org = null) => ActionPoint::create([
        'organization_id' => $org ?? $this->org->id, 'session_id' => $this->session->id, 'task' => $task, 'status' => $status, 'due_date' => $due,
    ]);
    $point('Due Friday', today()->addDays(3));
    $point('Late one', today()->subDays(2));
    $point('Next month', today()->addMonth());
    $point('No date', null);
    $point('Already done', today(), 'done');
    $point('Not ours', today(), 'open', Organization::factory()->create()->id);

    $this->get(route('sessions.index'))->assertInertia(fn (Assert $page) => $page
        ->has('dueActions', 2)
        ->where('dueActions.0.task', 'Late one')->where('dueActions.0.overdue', true)
        ->where('dueActions.1.task', 'Due Friday')->where('dueActions.1.session.title', 'Board meeting')
        ->missing('columns.recent'));
});

it('puts a finished session still being written up in Ready to review, ahead of the reports', function () {
    $writing = Session::create(['organization_id' => $this->org->id, 'type' => 'meeting', 'title' => 'Just ended', 'date' => today(), 'status' => 'completed']);

    $this->get(route('sessions.index'))->assertInertia(fn (Assert $page) => $page
        ->has('columns.ready', 2)
        ->where('columns.ready.0.id', $writing->id)->where('columns.ready.0.status', 'completed')
        ->where('columns.ready.1.id', $this->session->id));
});

it('finds sessions by title, item leader and the notes, saying where it matched, only in this organization', function () {
    $this->session->segments()->create(['title' => 'Fuel budget', 'presenter_name' => 'Palesa Mokoena', 'order' => 1, 'status' => 'completed', 'raw_log' => [['time' => '10:02', 'text' => 'agreed to buy a second vehicle for the district']]]);
    $other = Organization::factory()->create();
    Session::create(['organization_id' => $other->id, 'type' => 'meeting', 'title' => 'Board vehicle talk', 'date' => today()]);

    $this->getJson(route('sessions.search', ['q' => 'board']))->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.match', null);
    $this->getJson(route('sessions.search', ['q' => 'palesa']))->assertJsonPath('results.0.match', 'Led by Palesa Mokoena');
    $this->getJson(route('sessions.search', ['q' => 'second vehicle']))->assertJsonPath('results.0.id', $this->session->id)
        ->assertJsonPath('results.0.match', 'agreed to buy a second vehicle for the district');
    $this->getJson(route('sessions.search', ['q' => 'b']))->assertJsonCount(0, 'results');
    $this->getJson(route('sessions.search', ['q' => '100%']))->assertJsonCount(0, 'results');
});

it('shows the week, Monday first, with each day\'s sessions and action points, and moves between weeks', function () {
    $monday = today()->startOfWeek();
    $this->session->update(['date' => $monday->copy()->addDays(2), 'start_time' => '09:30']);
    ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'task' => 'Send quotes', 'status' => 'open', 'due_date' => $monday->copy()->addDays(4)]);

    $this->get(route('sessions.index'))->assertInertia(fn (Assert $page) => $page
        ->where('week.start', $monday->toDateString())
        ->has('week.days', 7)
        ->where('week.days.0.weekday', 'Mon')
        ->where('week.days.2.sessions.0.title', 'Board meeting')->where('week.days.2.sessions.0.time', '09:30')
        ->where('week.days.4.actions.0.task', 'Send quotes'));

    $this->get(route('sessions.index', ['week' => $monday->copy()->addWeek()->addDays(3)->toDateString()]))->assertInertia(fn (Assert $page) => $page
        ->where('week.start', $monday->copy()->addWeek()->toDateString())
        ->where('week.days.2.sessions', []));
    $this->get(route('sessions.index', ['week' => 'not-a-date']))->assertOk();
});

it('shows a repeating meeting as one search result, opening the series, unless only one of its meetings matched', function () {
    $series = \App\Models\SessionSeries::create(['organization_id' => $this->org->id, 'title' => 'Ops meeting', 'type' => 'meeting', 'frequency' => 'weekly', 'starts_on' => today()->subWeeks(2)]);
    $held = Session::create(['organization_id' => $this->org->id, 'series_id' => $series->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => today()->subWeek(), 'status' => 'reported']);
    Session::create(['organization_id' => $this->org->id, 'series_id' => $series->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => today()->addWeek(), 'status' => 'draft']);
    $held->segments()->create(['title' => 'Fleet', 'presenter_name' => 'Mpho Sello', 'order' => 1, 'status' => 'completed']);

    $this->getJson(route('sessions.search', ['q' => 'ops']))->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.title', 'Ops meeting')
        ->assertJsonPath('results.0.series.id', $series->id)->assertJsonPath('results.0.series.meetings', 2);
    $this->getJson(route('sessions.search', ['q' => 'mpho']))->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.id', $held->id)->assertJsonPath('results.0.series', null)
        ->assertJsonPath('results.0.match', 'Led by Mpho Sello');
});
