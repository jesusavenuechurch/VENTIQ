<?php

use App\Jobs\EmbedMemoryChunks;
use App\Models\{ActionPoint, AssistMessage, MemoryChunk, Organization, Session, User};
use App\Services\AI\AIService;
use App\Services\AI\Results\GeneratedContent;
use App\Services\Memory\{MemoryIndexer, MemorySearch, QuestionDates};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Http, Mail};

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-21 08:00'));
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Maloti Water']);
    $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Palesa Mokoena']);
    $this->admin->assignRole('org_admin');
    $this->actingAs($this->admin);

    $this->meeting = function (Organization $org, string $title, string $date, string $item, string $discussion, string $notes) {
        $session = Session::create(['organization_id' => $org->id, 'type' => 'meeting', 'title' => $title, 'date' => $date, 'status' => 'completed', 'meta' => ['chair' => 'Palesa Mokoena']]);
        $session->segments()->create(['title' => $item, 'presenter_name' => 'Thabo Lebona', 'order' => 0, 'status' => 'completed', 'is_presenting' => true,
            'raw_log' => [['time' => '09:01', 'text' => $notes]],
            'ai_summary' => ['summary' => $discussion, 'decisions' => '• Hire a second truck.', 'action_items' => 'None identified.', 'follow_ups' => 'None identified.']]);
        $session->update(['status' => 'reported']);   // reported: indexed

        return $session;
    };
    $this->ops = ($this->meeting)($this->org, 'Weekly ops', '2026-10-19', 'Logistics', 'It was reported that deliveries to Mafeteng ran three days late.', 'trucks late, fuel shortage in Mafeteng');
    $this->ai = function (string $answer, ?Closure $check = null) {
        $this->mock(AIService::class, fn ($m) => $m->shouldReceive('generate')->andReturnUsing(function ($prompt) use ($answer, $check) {
            $check && $check($prompt->build());

            return GeneratedContent::success($answer, 'test', 0.1);
        }));
    };
});

it('reads the time a question is about from its words', function () {
    $today = Carbon::parse('2026-10-21');
    $range = fn (string $q) => ($r = QuestionDates::parse($q, $today)) ? [$r[0]->toDateString(), $r[1]->toDateString()] : null;

    expect($range('What did we discuss about logistics this month?'))->toBe(['2026-10-01', '2026-10-31'])
        ->and($range('anything last week?'))->toBe(['2026-10-12', '2026-10-18'])
        ->and($range('decisions in March'))->toBe(['2026-03-01', '2026-03-31'])
        ->and($range('decisions in December'))->toBe(['2025-12-01', '2025-12-31'])   // the last one, not next
        ->and($range('since June 2026'))->toBe(['2026-06-01', '2026-10-21'])
        ->and($range('the past 2 weeks'))->toBe(['2026-10-07', '2026-10-21'])
        ->and($range('who leads procurement?'))->toBeNull();
});

it("makes passages of a held meeting's minutes, notes and action points, and keeps them current", function () {
    $chunks = MemoryChunk::where('session_id', $this->ops->id)->get();
    expect($chunks->pluck('source_type')->sort()->values()->all())->toBe(['minutes', 'notes', 'segment'])
        ->and($chunks->firstWhere('source_type', 'segment')->title)->toBe('Weekly ops · 19 Oct 2026 · Logistics')
        ->and($chunks->firstWhere('source_type', 'segment')->body)->toContain('Led by Thabo Lebona.')->toContain('Decided: Hire a second truck.')
        ->and($chunks->firstWhere('source_type', 'minutes')->body)->toContain('Chair: Palesa Mokoena');

    // An action point agreed: its own passage; nothing else redone.
    $segmentId = $chunks->firstWhere('source_type', 'segment')->id;
    ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->ops->id, 'task' => 'Get quotes for a second truck', 'owner_name' => 'Thabo Lebona', 'due_date' => '2026-10-30', 'status' => 'open', 'source' => 'manual']);
    $action = MemoryChunk::where('session_id', $this->ops->id)->where('source_type', 'action')->sole();
    expect($action->body)->toContain('Action: Get quotes for a second truck')->toContain('Owner: Thabo Lebona')->toContain('Due: 30 Oct 2026')->toContain('Status: open')
        ->and(MemoryChunk::where('session_id', $this->ops->id)->where('source_type', 'segment')->value('id'))->toBe($segmentId);

    // Minutes changed by hand: the passage follows.
    $this->ops->update(['minutes' => ['aob' => 'The chair thanked the drivers.']]);
    expect(MemoryChunk::where('session_id', $this->ops->id)->where('title', 'like', '%Any other business')->value('body'))->toBe('The chair thanked the drivers.');

    $this->ops->delete();
    expect(MemoryChunk::where('session_id', $this->ops->id)->count())->toBe(0);
});

it('embeds passages on the Mac mini, and waits when it is off', function () {
    config(['ai.ollama.url' => 'http://mac-mini:11434']);
    // Off the first time, back the second.
    $calls = 0;
    Http::fake(['mac-mini:11434/api/embed' => function ($request) use (&$calls) {
        return ++$calls === 1 ? Http::response([], 500) : Http::response(['embeddings' => array_map(fn () => [3.0, 4.0], $request['input'])]);
    }]);

    (new EmbedMemoryChunks($this->org->id))->handle(app(\App\Services\Memory\Embedder::class));
    expect(MemoryChunk::whereNotNull('embedding')->count())->toBe(0);

    (new EmbedMemoryChunks($this->org->id))->handle(app(\App\Services\Memory\Embedder::class));
    $chunk = MemoryChunk::first();
    expect(MemoryChunk::whereNull('embedding')->count())->toBe(0)
        ->and($chunk->embedding_model)->toBe('nomic-embed-text')
        ->and(array_map(fn ($v) => round($v, 2), $chunk->vector()))->toBe([0.6, 0.8]);   // normalised
    Http::assertSent(fn ($r) => str_starts_with($r['input'][0], 'search_document: '));
});

it("finds the organization's own passages by words and by meaning, within the dates asked about", function () {
    $other = Organization::factory()->create();
    ($this->meeting)($other, 'Their ops', '2026-10-19', 'Logistics', 'Their deliveries to Mafeteng were late too.', 'trucks late');
    $old = ($this->meeting)($this->org, 'Weekly ops', '2026-03-02', 'Budget', 'The fuel budget was approved.', 'fuel budget');

    $search = app(MemorySearch::class);
    $found = $search->search($this->org->id, 'What happened with the trucks to Mafeteng?');
    expect($found->pluck('organization_id')->unique()->all())->toBe([$this->org->id])
        ->and($found->first()->session_id)->toBe($this->ops->id);

    $inMarch = $search->search($this->org->id, 'fuel', QuestionDates::parse('fuel in March'));
    expect($inMarch->pluck('session_id')->unique()->all())->toBe([$old->id]);

    // By meaning: the passage whose embedding is closest, though no word matches.
    config(['ai.ollama.url' => 'http://mac-mini:11434']);
    MemoryChunk::where('organization_id', $this->org->id)->get()->each(fn ($c) => $c->forceFill([
        'embedding' => MemoryChunk::pack($c->session_id === $old->id ? [1.0, 0.0] : [0.0, 1.0]), 'embedding_model' => 'nomic-embed-text',
    ])->save());
    Http::fake(['mac-mini:11434/api/embed' => Http::response(['embeddings' => [[0.9, 0.1]]])]);
    expect($search->search($this->org->id, 'money matters')->first()->session_id)->toBe($old->id);
});

it('answers from the records, citing the meetings, and links to them', function () {
    ActionPoint::create(['organization_id' => $this->org->id, 'session_id' => $this->ops->id, 'task' => 'Get quotes for a second truck', 'owner_name' => 'Thabo Lebona', 'due_date' => '2026-10-15', 'status' => 'open', 'source' => 'manual']);
    $other = Organization::factory()->create();
    ($this->meeting)($other, 'Their ops', '2026-10-19', 'Logistics', 'A secret about Mafeteng logistics.', 'secret');

    ($this->ai)('Deliveries to Mafeteng ran three days late, and a second truck is to be hired [1]. Thabo Lebona is getting quotes, now overdue [1].', function (string $prompt) {
        expect($prompt)->toContain('[1] Weekly ops, 19 Oct 2026 (Meeting)')
            ->toContain("- Logistics:\nLed by Thabo Lebona.")
            ->toContain('deliveries to Mafeteng ran three days late')
            ->toContain('Sessions held from 1 Oct 2026 to 31 Oct 2026: 1 (1 Meeting).')
            ->toContain('Get quotes for a second truck (owner: Thabo Lebona, due 15 Oct 2026, OVERDUE')
            ->not->toContain('secret');
    });

    $this->postJson(route('assist.ask'), ['text' => 'What did we discuss about logistics this month, and which actions are open?'])->assertOk();

    $reply = AssistMessage::where('role', 'assistant')->sole();
    expect($reply->status)->toBe('completed')
        ->and($reply->sources)->toBe([['n' => 1, 'title' => 'Weekly ops', 'date' => '19 Oct 2026', 'url' => route('sessions.report', $this->ops->id)]]);

    $this->getJson(route('assist.messages'))->assertJsonPath('messages.1.sources.0.title', 'Weekly ops')
        ->assertJsonPath('messages.1.html', fn ($html) => str_contains($html, '<a href="' . route('sessions.report', $this->ops->id) . '" class="cite">1</a>'));
});

it("doesn't search reports for a role that can't read them", function () {
    $door = User::factory()->create(['organization_id' => $this->org->id]);
    $door->assignRole('scanner');
    $this->mock(AIService::class, fn ($m) => $m->shouldNotReceive('generate'));

    $this->actingAs($door)->postJson(route('assist.ask'), ['text' => 'What did we discuss about logistics?'])->assertOk()
        ->assertJsonPath('messages.1.html', fn ($html) => str_contains($html, "doesn't include reading reports"));
});
