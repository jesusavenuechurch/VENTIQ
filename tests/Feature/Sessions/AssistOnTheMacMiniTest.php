<?php

use App\Jobs\{ExtractActionPoints, GenerateProgrammeReport, GenerateSessionReport};
use App\Models\{Client, Event, MemoryChunk, Organization, Participant, Session, User};
use App\Services\AI\AIService;
use App\Services\AI\Prompts\{MemoryAnswerPrompt, SegmentInsightPrompt};
use App\Services\Memory\MemoryIndexer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Http, Mail};

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    config(['ai.default' => 'ollama', 'ai.ollama.url' => 'http://mac-mini:11434', 'ai.ollama.model' => 'gpt-oss:20b', 'ai.ollama.fast_model' => null, 'ai.ollama.num_ctx' => 16384, 'ai.ollama.thinking' => null]);
    Http::fake([
        'mac-mini:11434/api/tags' => Http::response(['models' => []]),
        'mac-mini:11434/api/generate' => Http::response(['response' => "CATEGORY: decision\nTEXT: Buy the Toyota"]),
    ]);
    $this->ask = fn ($prompt) => app()->make(AIService::class)->generate($prompt);
    $this->sent = fn () => collect(Http::recorded())->map(fn ($r) => $r[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/api/generate'))->last();
});

it('asks gpt-oss to think little for a sticky note, and lets it think for the rest', function () {
    ($this->ask)((new SegmentInsightPrompt)->with(['line' => 'agreed to buy the Toyota']));
    expect(($this->sent)()['think'])->toBe('low')->and(($this->sent)()['model'])->toBe('gpt-oss:20b')
        ->and(($this->sent)()['options']['num_ctx'])->toBe(16384);

    ($this->ask)((new MemoryAnswerPrompt)->with(['question' => 'Why?', 'sources' => '', 'facts' => '', 'organization' => 'X', 'today' => 'today']));
    expect(($this->sent)()->data())->not->toHaveKey('think');
});

it('uses the faster model for sticky notes when there is one, and never sends a thinking level to a model that refuses it', function () {
    config(['ai.ollama.fast_model' => 'qwen2.5:7b']);
    ($this->ask)((new SegmentInsightPrompt)->with(['line' => 'agreed']));
    expect(($this->sent)()['model'])->toBe('qwen2.5:7b')->and(($this->sent)()->data())->not->toHaveKey('think');

    ($this->ask)((new MemoryAnswerPrompt)->with(['question' => 'Why?', 'sources' => '', 'facts' => '', 'organization' => 'X', 'today' => 'today']));
    expect(($this->sent)()['model'])->toBe('gpt-oss:20b');
});

it('gives the slow AI jobs time, and the queue longer still before it thinks them lost', function () {
    expect((new GenerateSessionReport('x', 1, 1))->timeout)->toBe(900)
        ->and((new GenerateProgrammeReport('x', 1))->timeout)->toBe(900)
        ->and((new ExtractActionPoints(1))->timeout)->toBe(300)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(900)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan(900);
});

it("remembers who came to a programme's day, and how the programme stands", function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $org = Organization::factory()->create();
    $programme = Event::create(['organization_id' => $org->id, 'name' => 'CHW training', 'event_date' => '2026-10-19', 'is_programme' => true, 'certificates_enabled' => true, 'certificate_min_days' => 1, 'is_public' => false]);
    $day = Session::create(['organization_id' => $org->id, 'event_id' => $programme->id, 'type' => 'training', 'title' => 'CHW training', 'date' => '2026-10-19', 'start_time' => '09:00', 'status' => 'reported', 'meta' => ['expected_participants' => 4]]);
    foreach (['Mpho', 'Lineo', 'Thabo'] as $i => $name) {
        $client = Client::create(['organization_id' => $org->id, 'full_name' => $name, 'email' => "{$name}@example.com"]);
        \App\Models\ProgrammeEnrolment::create(['organization_id' => $org->id, 'event_id' => $programme->id, 'client_id' => $client->id, 'source' => 'desk']);
        Participant::create(['organization_id' => $org->id, 'event_id' => $programme->id, 'session_id' => $day->id, 'client_id' => $client->id, 'source' => 'self',
            'attended_at' => '2026-10-19 08:5' . $i, 'institution' => 'Mafeteng Hospital', 'district' => 'mafeteng']);
    }
    (new \App\Jobs\IssueProgrammeCertificates($programme->id))->handle(app(\App\Services\Sessions\ProgrammeProgress::class));
    app(MemoryIndexer::class)->indexSession($day->fresh());

    $passage = MemoryChunk::where('session_id', $day->id)->where('source_type', 'numbers')->sole();
    expect($passage->title)->toBe('CHW training · 19 Oct 2026 · Attendance')
        ->and($passage->body)->toContain('Checked in: 3 of 4 expected (75%).')->toContain('Mafeteng Hospital 3')
        ->toContain('Day 1 of 1 of the programme CHW training')->toContain('Enrolled on the programme: 3.')->toContain('Certificates issued: 3.');
});
