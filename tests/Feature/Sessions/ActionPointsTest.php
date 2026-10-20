<?php

use App\Jobs\ExtractActionPoints;
use App\Mail\ActionPointsReminderMail;
use App\Models\{ActionPoint, Client, Organization, Participant, Session, SessionSeries, User};
use App\Services\AI\AIService;
use App\Services\AI\Results\GeneratedContent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Bus, Mail};
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Palesa Mokoena', 'email' => 'palesa@example.com']);
    $this->admin->assignRole('org_admin');
    $this->thabo = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Thabo Lebona', 'email' => 'thabo@example.com']);
    $this->thabo->assignRole('staff');
    $this->actingAs($this->admin);

    $this->session = Session::create([
        'organization_id' => $this->org->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => '2026-10-20', 'status' => 'reported', 'session_report' => 'Report',
    ]);
    $this->session->segments()->create(['presenter_name' => 'Palesa Mokoena', 'order' => 0, 'status' => 'completed', 'is_presenting' => true,
        'raw_log' => [['time' => '09:01', 'text' => 'Thabo to send venue quotes to finance by Friday'], ['time' => '09:05', 'text' => 'Budget approved']]]);
    $this->ai = function (string $content, bool $ok = true) {
        $this->mock(AIService::class, fn ($m) => $m->shouldReceive('generate')->andReturn($ok ? GeneratedContent::success($content, 'test', 0.1) : GeneratedContent::failure('Mac mini offline', 'test')));
    };
});

it("reads the model's answer, even wrapped in other text", function () {
    expect(ExtractActionPoints::parse("Here you go:\n```json\n[{\"task\":\"Send quotes\",\"owner\":\"Thabo\",\"due\":\"2026-10-23\"}]\n```"))
        ->toBe([['task' => 'Send quotes', 'owner' => 'Thabo', 'due' => '2026-10-23']])
        ->and(ExtractActionPoints::parse('No action points.'))->toBe([])
        ->and(ExtractActionPoints::parse('[{"owner":"x"}]'))->toBe([]);

    $owners = [['name' => 'Thabo Lebona', 'user_id' => 1], ['name' => 'Thabo Mokete', 'client_id' => 2], ['name' => 'Lineo Ramoeletsi', 'user_id' => 3]];
    expect(ExtractActionPoints::matchOwner('Thabo Lebona', $owners)['user_id'])->toBe(1)
        ->and(ExtractActionPoints::matchOwner('Lineo', $owners)['user_id'])->toBe(3)
        ->and(ExtractActionPoints::matchOwner('Thabo', $owners))->toBeNull();   // two Thabos: don't guess
});

it('proposes action points from the notes, with the owner and due date where it can', function () {
    ($this->ai)('[{"task":"Send the venue quotes to finance","owner":"Thabo","due":"2026-10-23"},{"task":"Book the hall","owner":"Someone else","due":"2020-01-01"}]');

    (new ExtractActionPoints($this->session->id))->handle(app(AIService::class));

    $points = $this->session->actionPoints()->orderBy('id')->get();
    expect($points)->toHaveCount(2)
        ->and($points[0]->status)->toBe('proposed')
        ->and($points[0]->owner_user_id)->toBe($this->thabo->id)
        ->and($points[0]->due_date->toDateString())->toBe('2026-10-23')
        ->and($points[1]->owner_name)->toBe('Someone else')
        ->and($points[1]->due_date)->toBeNull()   // before the meeting: a guess, dropped
        ->and($this->session->fresh()->meta['action_points'])->toBe('done');

    // Run again: nothing doubled.
    (new ExtractActionPoints($this->session->id))->handle(app(AIService::class));
    expect($this->session->actionPoints()->count())->toBe(2);
});

it("tries again later when the AI can't be reached", function () {
    ($this->ai)('', false);

    expect(fn () => (new ExtractActionPoints($this->session->id))->handle(app(AIService::class)))->toThrow(RuntimeException::class);
    (new ExtractActionPoints($this->session->id))->failed(null);
    expect($this->session->fresh()->meta['action_points'])->toBe('failed');
});

it('shows them on the report to keep, change or remove, and reviewing keeps the rest', function () {
    $a = ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'task' => 'Send quotes', 'status' => 'proposed', 'source' => 'ai']);
    $b = ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'task' => 'Wrong one', 'status' => 'proposed', 'source' => 'ai']);
    $c = ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'task' => 'Book the hall', 'status' => 'proposed', 'source' => 'ai']);

    $this->get(route('sessions.report', $this->session))->assertInertia(fn (Assert $page) => $page->has('actionPoints', 3)->where('owners.0.label', 'Palesa Mokoena'));

    $this->patch(route('sessions.actions.update', $a), ['task' => 'Send the quotes', 'owner' => "user:{$this->thabo->id}", 'due_date' => '2026-10-30'])->assertRedirect();
    $this->delete(route('sessions.actions.destroy', $b))->assertRedirect();
    $this->post(route('sessions.report.review', $this->session));

    expect($a->fresh()->status)->toBe('open')->and($a->fresh()->owner_user_id)->toBe($this->thabo->id)
        ->and(ActionPoint::find($b->id))->toBeNull()
        ->and($c->fresh()->status)->toBe('open');

    $this->post(route('sessions.actions.store', $this->session), ['task' => 'Call the plumber', 'owner_name' => 'Lineo'])->assertRedirect();
    expect(ActionPoint::where('task', 'Call the plumber')->value('owner_name'))->toBe('Lineo');
});

it("brings a series' open points to its next meeting, ticked off there", function () {
    $series = SessionSeries::create(['organization_id' => $this->org->id, 'title' => 'Ops meeting', 'type' => 'meeting', 'frequency' => 'weekly', 'starts_on' => '2026-10-20']);
    $this->session->update(['series_id' => $series->id]);
    $next = Session::create(['organization_id' => $this->org->id, 'series_id' => $series->id, 'parent_session_id' => $this->session->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => '2026-10-27', 'status' => 'draft']);
    $point = ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'series_id' => $series->id, 'task' => 'Send quotes', 'status' => 'open']);

    $this->get(route('sessions.show', $next))->assertInertia(fn (Assert $page) => $page->where('carried.0.task', 'Send quotes'));

    $this->postJson(route('sessions.actions.done', $point), ['session_id' => $next->id])->assertOk();
    expect($point->fresh()->status)->toBe('done')->and($point->fresh()->done_in_session_id)->toBe($next->id);
    // Still listed on that meeting's report, as done there.
    $this->get(route('sessions.report', $next))->assertInertia(fn (Assert $page) => $page->where('carried.0.status', 'done'));
});

it('lists them across the organization, and keeps them to it', function () {
    ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'task' => 'Late one', 'status' => 'open', 'due_date' => '2026-10-10', 'owner_user_id' => $this->admin->id]);
    ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'task' => 'Later one', 'status' => 'open', 'due_date' => '2026-11-10']);
    $other = Organization::factory()->create();
    $theirs = ActionPoint::create(['organization_id' => $other->id, 'session_id' => Session::create(['organization_id' => $other->id, 'type' => 'meeting', 'title' => 'X', 'date' => '2026-10-20'])->id, 'task' => 'Not yours', 'status' => 'open']);

    $this->get(route('sessions.actions.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Sessions/Actions')->has('points.data', 2)->where('points.data.0.task', 'Late one')
        ->where('counts', ['open' => 2, 'overdue' => 1, 'mine' => 1, 'proposed' => 0])
        ->where('sessionsArea.overdueActions', 1));
    $this->post(route('sessions.actions.done', $theirs))->assertNotFound();

    $viewer = User::factory()->create(['organization_id' => $this->org->id]);
    $viewer->assignRole('viewer');
    $this->actingAs($viewer)->get(route('sessions.actions.index'))->assertOk();
    $this->post(route('sessions.actions.done', ActionPoint::first()))->assertForbidden();
});

it('reminds owners two days before, on the day, and weekly when overdue, in one email each', function () {
    $make = fn (string $task, string $due, array $owner) => ActionPoint::create($owner + ['organization_id' => $this->org->id, 'session_id' => $this->session->id, 'task' => $task, 'status' => 'open', 'due_date' => $due]);
    $make('Due in two days', '2026-10-23', ['owner_user_id' => $this->thabo->id]);
    $make('Due today', '2026-10-21', ['owner_user_id' => $this->thabo->id]);
    $make('Overdue', '2026-10-15', ['owner_user_id' => $this->admin->id]);
    $make('Due in a week', '2026-10-28', ['owner_user_id' => $this->admin->id]);
    $make('No email', '2026-10-21', ['owner_name' => 'Lineo']);
    $client = Client::create(['organization_id' => $this->org->id, 'full_name' => 'Mpho Nkosi', 'email' => 'mpho@example.com']);
    $make('Attendee owns it', '2026-10-21', ['owner_client_id' => $client->id]);

    $this->artisan('sessions:action-reminders')->assertSuccessful();
    Mail::assertQueued(ActionPointsReminderMail::class, 3);
    Mail::assertQueued(ActionPointsReminderMail::class, fn ($m) => $m->hasTo('thabo@example.com') && count($m->points) === 2 && $m->link);
    Mail::assertQueued(ActionPointsReminderMail::class, fn ($m) => $m->hasTo('mpho@example.com') && $m->link === null);

    // Not twice in a day; the overdue one again only a week later.
    $this->artisan('sessions:action-reminders');
    Mail::assertQueued(ActionPointsReminderMail::class, 3);
    $this->travelTo(Carbon::parse('2026-10-28 08:00'));
    $this->artisan('sessions:action-reminders');
    Mail::assertQueued(ActionPointsReminderMail::class, fn ($m) => $m->hasTo('palesa@example.com') && collect($m->points)->pluck('task')->sort()->values()->all() === ['Due in a week', 'Overdue']);
});
