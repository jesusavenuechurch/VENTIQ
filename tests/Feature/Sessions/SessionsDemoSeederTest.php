<?php

use App\Models\{ActionPoint, Certificate, MemoryChunk, Organization, Session, SessionInvoice, User};
use App\Services\Sessions\MinutesBuilder;
use App\Services\SessionQuotaService;
use Database\Seeders\SessionsDemoSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

it('seeds six months of Sessions to try everything with, and can be run again', function () {
    $this->seed(SessionsDemoSeeder::class);
    // A demo had: someone asked Ventiq something. Seeding again still works.
    $admin = User::where('email', 'demo.admin@example.com')->sole();
    \App\Models\AssistConversation::create(['organization_id' => $admin->organization_id, 'user_id' => $admin->id, 'title' => 'Fuel'])
        ->messages()->create(['role' => 'user', 'content' => 'What did we decide about fuel?']);
    $this->seed(SessionsDemoSeeder::class);

    $trust = Organization::where('slug', 'maluti-community-health-trust')->sole();
    $meetings = Session::where('organization_id', $trust->id)->where('type', 'meeting');
    expect((clone $meetings)->where('status', 'reported')->count())->toBe(26)
        ->and((clone $meetings)->where('status', 'draft')->count())->toBe(4)
        ->and(Session::where('organization_id', $trust->id)->whereDate('date', today())->where('status', 'draft')->count())->toBe(1)
        ->and(User::where('organization_id', $trust->id)->count())->toBe(4)
        ->and(Certificate::where('organization_id', $trust->id)->count())->toBe(21)
        ->and(ActionPoint::where('organization_id', $trust->id)->where('status', 'proposed')->count())->toBe(2)
        ->and(ActionPoint::where('organization_id', $trust->id)->where('status', 'open')->whereDate('due_date', '<', today())->count())->toBeGreaterThan(0)
        ->and(MemoryChunk::where('organization_id', $trust->id)->count())->toBeGreaterThan(300);

    // This week's meeting: held, waiting for its review; last week's minutes confirmed.
    $latest = (clone $meetings)->where('status', 'reported')->orderByDesc('date')->first();
    expect($latest->reviewed_at)->toBeNull()
        ->and(app(MinutesBuilder::class)->build($latest)['previous']['confirmed'])->toBeTrue()
        ->and($latest->session_report)->toContain('Maluti Community Health Trust');

    // Invoiced each month, the last one overdue.
    $invoices = SessionInvoice::where('organization_id', $trust->id)->orderBy('period_start')->get();
    expect($invoices)->toHaveCount(5)
        ->and($invoices->last()->isOverdue())->toBeTrue()
        ->and($invoices->first()->status)->toBe('paid');

    // The Youth Network: plan used up, one pay-as-you-go session left.
    $youth = Organization::where('slug', 'thaba-bosiu-youth-network')->sole();
    expect(app(SessionQuotaService::class)->remainingSessions($youth))->toBe(1);

    $this->actingAs(User::where('email', 'demo.admin@example.com')->sole())
        ->get(route('sessions.show', $latest))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('session.status', 'reported'));
});
