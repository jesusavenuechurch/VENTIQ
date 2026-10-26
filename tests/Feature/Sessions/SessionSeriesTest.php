<?php

use App\Models\{Organization, Session, SessionSeries, User};
use App\Services\Sessions\SeriesScheduler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Bus, Mail};
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));   // a Wednesday
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['organization_id' => $this->org->id]);
    $this->user->assignRole('org_admin');
    $this->actingAs($this->user);
    $this->scheduleSeries = fn (array $over = []) => $this->post(route('sessions.store'), $over + [
        'title' => 'Weekly ops meeting', 'type' => 'meeting', 'date' => '2026-10-26', 'start_time' => '09:00', 'repeat' => 'weekly',
        'people' => [['name' => 'Palesa Mokoena', 'role' => 'chairperson', 'presenting' => true], ['name' => 'Thabo Lebona', 'role' => 'secretary', 'presenting' => false]],
    ]);
});

function series(array $attributes): SessionSeries
{
    return new SessionSeries($attributes + ['title' => 'S', 'type' => 'meeting']);
}

it('works out the dates of each kind of repeat', function () {
    $dates = fn (SessionSeries $s, int $n) => collect($s->datesAfter(null, $n))->map->toDateString()->all();

    expect($dates(series(['frequency' => 'weekly', 'starts_on' => '2026-10-26']), 3))->toBe(['2026-10-26', '2026-11-02', '2026-11-09'])
        ->and($dates(series(['frequency' => 'fortnightly', 'starts_on' => '2026-10-26']), 2))->toBe(['2026-10-26', '2026-11-09'])
        // The 31st falls back to the last day of shorter months.
        ->and($dates(series(['frequency' => 'monthly_date', 'starts_on' => '2027-01-31']), 3))->toBe(['2027-01-31', '2027-02-28', '2027-03-31'])
        // The 2nd Tuesday of each month.
        ->and($dates(series(['frequency' => 'monthly_weekday', 'starts_on' => '2026-10-13']), 3))->toBe(['2026-10-13', '2026-11-10', '2026-12-08'])
        // A 5th Thursday becomes the last one.
        ->and($dates(series(['frequency' => 'monthly_weekday', 'starts_on' => '2026-10-29']), 2))->toBe(['2026-10-29', '2026-11-26'])
        ->and($dates(series(['frequency' => 'weekly', 'starts_on' => '2026-10-26', 'ends_on' => '2026-11-03']), 9))->toBe(['2026-10-26', '2026-11-02']);

    expect(series(['frequency' => 'weekly', 'starts_on' => '2026-10-26', 'start_time' => '09:00:00'])->describe())->toBe('Every Monday at 09:00')
        ->and(series(['frequency' => 'monthly_weekday', 'starts_on' => '2026-10-13'])->describe())->toBe('Monthly on the 2nd Tuesday')
        ->and(series(['frequency' => 'monthly_date', 'starts_on' => '2026-10-22'])->describe())->toBe('Monthly on the 22nd');
});

it('schedules the next four meetings of a series, each linked to the one before, without using the plan', function () {
    ($this->scheduleSeries)()->assertRedirect();
    $series = SessionSeries::firstOrFail();
    $meetings = $series->sessions()->get();

    expect($meetings->pluck('date')->map->toDateString()->all())->toBe(['2026-10-26', '2026-11-02', '2026-11-09', '2026-11-16'])
        ->and($meetings[0]->parent_session_id)->toBeNull()
        ->and($meetings[1]->parent_session_id)->toBe($meetings[0]->id)
        ->and($meetings[3]->parent_session_id)->toBe($meetings[2]->id)
        ->and($meetings->pluck('counted_at')->filter())->toBeEmpty()
        ->and($meetings[2]->segments()->pluck('presenter_name')->all())->toBe(['Palesa Mokoena', 'Thabo Lebona']);

    $this->get(route('sessions.series.show', $series))->assertInertia(fn (Assert $page) => $page
        ->component('Sessions/Series')
        ->where('series.rule', 'Every Monday at 09:00')
        ->has('upcoming', 4));
});

it('keeps four meetings ahead as the weeks go by, and never makes a skipped one again', function () {
    ($this->scheduleSeries)();
    $series = SessionSeries::firstOrFail();

    $this->travelTo(Carbon::parse('2026-11-04 08:00'));   // two meetings have passed
    $this->artisan('sessions:top-up-series')->assertSuccessful();
    expect($series->sessions()->whereDate('date', '>=', today())->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-11-09', '2026-11-16', '2026-11-23', '2026-11-30']);

    $skip = $series->sessions()->whereDate('date', '2026-11-16')->first();
    $this->post(route('sessions.skip', $skip))->assertRedirect();
    expect($series->sessions()->whereDate('date', '>=', today())->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-11-09', '2026-11-23', '2026-11-30', '2026-12-07']);
});

it('still keeps four ahead on the day a meeting is held', function () {
    ($this->scheduleSeries)();
    $series = SessionSeries::firstOrFail();

    // Monday 26 Oct: that morning's meeting is held, then the series tops up.
    $this->travelTo(Carbon::parse('2026-10-26 11:00'));
    $series->sessions()->whereDate('date', '2026-10-26')->update(['status' => 'reported']);
    $this->artisan('sessions:top-up-series')->assertSuccessful();

    expect($series->sessions()->where('status', 'draft')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23']);
});

it('changes the meetings ahead when the series changes, not the ones held, and ends it', function () {
    ($this->scheduleSeries)();
    $series = SessionSeries::firstOrFail();
    $held = $series->sessions()->first();
    $held->update(['status' => 'reported']);

    $this->put(route('sessions.series.update', $series), [
        'title' => 'Ops meeting', 'type' => 'meeting', 'start_time' => '10:30', 'people' => [['name' => 'Lineo Ramoeletsi', 'role' => 'chairperson', 'presenting' => true]],
    ])->assertRedirect();

    expect($held->fresh()->title)->toBe('Weekly ops meeting')
        ->and($held->segments()->count())->toBe(2);
    $next = $series->sessions()->where('status', 'draft')->first();
    expect($next->title)->toBe('Ops meeting')
        ->and(substr($next->start_time, 0, 5))->toBe('10:30')
        ->and($next->segments()->pluck('presenter_name')->all())->toBe(['Lineo Ramoeletsi']);

    $this->post(route('sessions.series.end', $series))->assertRedirect();
    expect($series->fresh()->ended_at)->not->toBeNull()
        ->and($series->sessions()->pluck('id')->all())->toBe([$held->id]);
});

it('counts a session against the plan when it starts, once, and stops one with nothing left', function () {
    ($this->scheduleSeries)();
    [$first, $second] = SessionSeries::firstOrFail()->sessions()->take(2)->get();

    $this->postJson(route('sessions.start', $first))->assertOk();
    $this->postJson(route('sessions.start', $first))->assertOk();   // resuming doesn't count again
    expect($first->fresh()->counted_at)->not->toBeNull()
        ->and(app(\App\Services\SessionQuotaService::class)->currentPlan($this->org)->sessions_used)->toBe(1);

    // The free plan has one session a month.
    $this->postJson(route('sessions.start', $second))->assertStatus(402)->assertJsonPath('status', 'quota');
    expect($second->fresh()->status)->toBe('draft');
});

it('shows each series once on the desk: its next meeting, and the series itself', function () {
    ($this->scheduleSeries)();

    $this->get(route('sessions.index'))->assertInertia(fn (Assert $page) => $page
        ->has('columns.upcoming', 1)
        ->where('columns.upcoming.0.series.rule', 'Every Monday at 09:00')
        ->where('series.0.next', 'Mon 26 Oct'));
});
