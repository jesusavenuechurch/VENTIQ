<?php

use App\Jobs\{GenerateSessionReport, TranscribeRecordingPiece};
use App\Models\{Organization, Session, SessionRecordingPiece, User};
use App\Services\Sessions\Recording;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Bus, Http, Mail, Storage};
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Bus::fake();
    Mail::fake();
    Storage::fake('local');
    config(['services.whisper.url' => 'http://whisper.test:8178', 'queue.default' => 'database']);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->user = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Thabo Lebona']);
    $this->user->assignRole('org_admin');
    $this->actingAs($this->user);
    $this->session = Session::create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'type' => 'meeting', 'title' => 'Ops meeting', 'date' => today(), 'status' => 'active']);
    $this->item = $this->session->segments()->create(['title' => 'Fleet', 'presenter_name' => 'Palesa Mokoena', 'order' => 1, 'status' => 'active', 'is_presenting' => true]);
    $this->piece = fn (int $seq, string $recorder = 'phone-a', array $extra = []) => $this->post(route('sessions.recording.piece', $this->session), $extra + [
        'recorder' => $recorder, 'seq' => $seq, 'segment_id' => $this->item->id, 'duration_ms' => 30000,
        'audio' => UploadedFile::fake()->create("piece-{$seq}.webm", 40, 'audio/webm'),
    ], ['Accept' => 'application/json']);
    $this->whisper = fn (string $text) => Http::fake(['whisper.test:8178/*' => Http::response(['text' => $text])]);
    $this->transcribe = fn () => SessionRecordingPiece::where('status', 'pending')->get()->each(fn ($p) => (new TranscribeRecordingPiece($p->id))->handle(app(Recording::class)));
});

it('shows recording only when Whisper is set up', function () {
    $this->get(route('sessions.show', $this->session))->assertInertia(fn (Assert $page) => $page->where('recording.available', true)->where('recording.nextSeq', 0));
    config(['services.whisper.url' => null]);
    $this->get(route('sessions.show', $this->session))->assertInertia(fn (Assert $page) => $page->where('recording.available', false));
    $this->postJson(route('sessions.recording.start', $this->session), ['recorder' => 'phone-a'])->assertStatus(409);
});

it('takes pieces from one phone at a time, keeps a retried piece once, and has Whisper write them down', function () {
    $this->postJson(route('sessions.recording.start', $this->session), ['recorder' => 'phone-a'])->assertOk();
    $this->postJson(route('sessions.recording.start', $this->session), ['recorder' => 'phone-b'])->assertStatus(409);

    ($this->piece)(0)->assertOk();
    ($this->piece)(0)->assertOk()->assertJson(['duplicate' => true]);
    ($this->piece)(1)->assertOk();
    ($this->piece)(2, 'phone-b')->assertStatus(409);
    Bus::assertDispatchedTimes(TranscribeRecordingPiece::class, 2);
    expect(SessionRecordingPiece::count())->toBe(2);
    Storage::disk('local')->assertExists("recordings/session-{$this->session->id}/00000.webm");

    ($this->whisper)(' We agreed to service the truck. We agreed to service the truck. We agreed to service the truck. ');
    ($this->transcribe)();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/inference') && str_contains($r->body(), 'Palesa Mokoena') && str_contains($r->body(), 'Maloti Water'));
    expect(SessionRecordingPiece::pluck('status')->unique()->all())->toBe(['done'])
        ->and($this->item->fresh()->transcript)->toBe('We agreed to service the truck. We agreed to service the truck.');
});

it('treats a piece that is only silence or "Thank you." as nothing said', function () {
    expect(Recording::clean('[BLANK_AUDIO]'))->toBe('')
        ->and(Recording::clean(' Thank you. '))->toBe('')
        ->and(Recording::clean('Item two. (music) The budget is approved.'))->toBe('Item two. The budget is approved.')
        ->and(Recording::clean('If we can. If we can. Next point.'))->toBe('If we can. Next point.');
});

it('writes the report only once the last pieces are written down', function () {
    $this->postJson(route('sessions.recording.start', $this->session), ['recorder' => 'phone-a']);
    ($this->piece)(0);
    $this->postJson(route('sessions.recording.stop', $this->session))->assertOk();
    $this->postJson("/sessions/{$this->session->id}/segments/{$this->item->id}/finish")->assertOk();

    Bus::assertNotDispatched(GenerateSessionReport::class);
    expect($this->session->fresh()->meta['recording']['report_waiting'])->toBeTrue();

    ($this->whisper)('The truck goes in on Monday.');
    ($this->transcribe)();
    Bus::assertDispatched(GenerateSessionReport::class);
    expect($this->item->fresh()->notesText())->toContain('The truck goes in on Monday.');
});

it('gives up on a piece Whisper can never read, and still writes the report', function () {
    ($this->piece)(0);
    $this->postJson("/sessions/{$this->session->id}/segments/{$this->item->id}/finish");
    Http::fake(['whisper.test:8178/*' => Http::response('down', 500)]);
    config(['queue.default' => 'sync']);
    ($this->transcribe)();

    expect(SessionRecordingPiece::first()->status)->toBe('failed');
    Bus::assertDispatched(GenerateSessionReport::class);
});

it('records pauses, stops at the limit, and lets go of the phone when stopped', function () {
    $this->postJson(route('sessions.recording.start', $this->session), ['recorder' => 'phone-a']);
    $this->postJson(route('sessions.recording.pause', $this->session))->assertOk();
    $this->travel(5)->minutes();
    $this->postJson(route('sessions.recording.resume', $this->session))->assertOk();
    $pauses = $this->session->fresh()->meta['recording']['pauses'];
    expect($pauses)->toHaveCount(1)->and($pauses[0]['to'])->not->toBeNull();

    config(['services.whisper.max_seconds' => 30]);
    ($this->piece)(0)->assertOk();
    ($this->piece)(1)->assertStatus(422)->assertJson(['limit' => true]);

    $this->postJson(route('sessions.recording.stop', $this->session));
    $this->postJson(route('sessions.recording.start', $this->session), ['recorder' => 'phone-b'])->assertStatus(422);
    expect($this->session->fresh()->meta['recording']['recorder'])->toBeNull();
});

it('shows the transcript on the report to staff only, and keeps a hand-corrected one', function () {
    $this->item->update(['transcript' => 'The truck goes in on Munday.']);
    $this->session->update(['status' => 'reported', 'session_report' => 'Report']);
    SessionRecordingPiece::create(['session_id' => $this->session->id, 'segment_id' => $this->item->id, 'seq' => 0, 'recorder' => 'a', 'status' => 'done', 'text' => 'The truck goes in on Munday.', 'path' => 'x.webm']);

    $this->get(route('sessions.report', $this->session))->assertInertia(fn (Assert $page) => $page->where('segments.0.transcript', 'The truck goes in on Munday.')->where('recorded', true));
    $this->patch(route('sessions.segments.transcript', [$this->session, $this->item]), ['transcript' => 'The truck goes in on Monday.'])->assertRedirect();
    app(Recording::class)->rebuild($this->item->fresh());
    expect($this->item->fresh()->transcript)->toBe('The truck goes in on Monday.');

    $viewer = User::factory()->create(['organization_id' => $this->org->id]);
    $viewer->assignRole('viewer');
    $this->actingAs($viewer)->get(route('sessions.report', $this->session))->assertInertia(fn (Assert $page) => $page->where('segments.0.transcript', null)->where('recorded', false));
    $this->postJson(route('sessions.recording.start', $this->session), ['recorder' => 'v'])->assertForbidden();
    $this->patch(route('sessions.segments.transcript', [$this->session, $this->item]), ['transcript' => 'x'])->assertForbidden();
});

it('deletes the audio on review only when switched on, keeping the text', function () {
    ($this->piece)(0);
    $this->session->update(['status' => 'reported', 'session_report' => 'Report']);
    $path = SessionRecordingPiece::first()->path;

    config(['services.whisper.delete_audio_on_review' => true]);
    $this->post(route('sessions.report.review', $this->session));
    Storage::disk('local')->assertMissing($path);
    expect(SessionRecordingPiece::first()->path)->toBeNull();
});

it('shows a report that never finished as not written, with Try again, after half an hour', function () {
    $this->session->update(['status' => 'completed']);
    $this->session->queueReportGeneration();
    $this->get(route('sessions.report', $this->session))->assertInertia(fn (Assert $page) => $page->where('session.failed', false));
    $this->get(route('sessions.index'))->assertInertia(fn (Assert $page) => $page->where('columns.ready.0.stuck', false));

    $this->travel(31)->minutes();
    $this->get(route('sessions.report', $this->session))->assertInertia(fn (Assert $page) => $page->where('session.failed', true));
    $this->get(route('sessions.index'))->assertInertia(fn (Assert $page) => $page->where('columns.ready.0.stuck', true));

    // A recorded session still waiting for its pieces isn't stuck.
    \App\Services\Sessions\Recording::remember($this->session->fresh(), ['report_waiting' => true]);
    expect($this->session->fresh()->reportStuck())->toBeFalse();
});

it('spells VENTIQ right however Whisper heard it, and leaves other words alone', function () {
    expect(Recording::clean('Vintig Sessions is a platform. Ventik Assist will help.'))->toBe('Ventiq Sessions is a platform. Ventiq Assist will help.')
        ->and(Recording::clean('An old vintage car, and a ventilator.'))->toBe('An old vintage car, and a ventilator.');
});
