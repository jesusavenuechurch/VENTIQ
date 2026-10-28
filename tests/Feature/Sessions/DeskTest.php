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
