<?php

use App\Models\{Organization, Session, SessionSegment, User};
use Illuminate\Support\Facades\{Bus, Mail};

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Thabo Lebona']);
    $this->user->assignRole('org_admin');
    $this->actingAs($this->user);
});

it('starts a quick catch-up now: a meeting chaired by whoever started it, live, counted once', function () {
    $this->post(route('sessions.quick'))->assertRedirect();

    $session = Session::sole();
    expect($session)->type->toBe('meeting')->status->toBe('active')->counted_at->not->toBeNull()
        ->and($session->meta['chair'] ?? null)->toBe('Thabo Lebona')
        ->and($session->date->isToday())->toBeTrue();
    $item = $session->segments()->sole();
    expect($item)->title->toBe('Catch-up')->presenter_name->toBe('Thabo Lebona')->status->toBe('active');
    $this->get(route('sessions.show', $session))->assertOk();
});

it('takes the sticky note away with the line it came from', function () {
    $this->post(route('sessions.quick'));
    $item = SessionSegment::sole();
    $base = "/sessions/{$item->session_id}/segments/{$item->id}";

    $this->postJson("{$base}/log", ['text' => 'Teboho to call the council'])->assertOk();
    $item->insights()->create(['category' => 'action', 'content' => 'Teboho to call the council', 'is_ai_generated' => true, 'display_order' => 0]);
    $this->postJson("{$base}/log/undo")->assertOk();

    expect($item->insights()->count())->toBe(0);
});
