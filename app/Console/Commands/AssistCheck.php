<?php

namespace App\Console\Commands;

use App\Jobs\EmbedMemoryChunks;
use App\Jobs\ExtractActionPoints;
use App\Models\MemoryChunk;
use App\Models\Organization;
use App\Models\Session;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\ActionPointsPrompt;
use App\Services\AI\Prompts\SegmentInsightPrompt;
use App\Services\AI\Prompts\SegmentSummaryPrompt;
use App\Services\Memory\AskVentiq;
use App\Services\Memory\Embedder;
use App\Support\SessionType;
use Database\Seeders\SessionsDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Ventiq Assist, tried for real against the AI it is set up with (the Mac
 * mini's Ollama): each thing it does in Sessions, on the demo data,
 * timed, with what came back. Nothing is saved but the embeddings.
 *
 *   php artisan sessions:assist-check
 *   php artisan sessions:assist-check --ask="Who leads procurement?"
 */
class AssistCheck extends Command
{
    protected $signature = 'sessions:assist-check
        {--organization= : An organization id (the Sessions demo organization when left out)}
        {--ask=* : Your own question for Ask Ventiq (more than one allowed)}
        {--only= : One part: embed, notes, writeup, actions or ask}';

    protected $description = 'Try each part of Ventiq Assist against the real AI, on the demo data';

    private const QUESTIONS = [
        'Why did we buy a second vehicle?',
        'What did we decide about fuel?',
        'What is still open for Teboho?',
        'How did the CHW training in Mafeteng go?',
    ];

    private array $results = [];

    public function handle(AIService $ai, Embedder $embedder, AskVentiq $ask): int
    {
        $org = $this->option('organization')
            ? Organization::find($this->option('organization'))
            : Organization::where('email', SessionsDemoSeeder::TRUST_EMAIL)->first();
        if (!$org) {
            $this->error('No organization. Seed the demo first: php artisan db:seed --class=SessionsDemoSeeder');

            return self::FAILURE;
        }

        $this->line("Ventiq Assist on <info>{$ai->providerName()}</info>, for {$org->name}");
        if (!$ai->isAvailable()) {
            $this->error('The AI does not answer. Run `php artisan ai:status` to see why.');

            return self::FAILURE;
        }

        $only = $this->option('only');
        $run = fn (string $part) => !$only || $only === $part;
        $meeting = Session::where('organization_id', $org->id)->where('status', 'reported')->whereIn('type', ['meeting', 'board', 'committee'])
            ->whereHas('segments', fn ($q) => $q->whereNotNull('raw_log'))->orderByDesc('date')->first();

        $run('embed') && $this->embed($org, $embedder);
        $run('notes') && $this->stickyNotes($ai);
        $run('writeup') && $meeting && $this->writeUp($ai, $meeting);
        $run('actions') && $meeting && $this->actionPoints($ai, $meeting, $org);
        if ($run('ask')) {
            foreach ($this->option('ask') ?: self::QUESTIONS as $question) {
                $this->askVentiq($ask, $org, $question);
            }
        }

        $this->newLine();
        $this->table(['Part', 'Result', 'Time'], collect($this->results)->map(fn ($r) => [$r[0], $r[1], $r[2] === '' ? '' : "{$r[2]}s"])->all());
        $failed = collect($this->results)->contains(fn ($r) => str_contains($r[1], 'FAILED'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Passages turned into vectors on the Mac mini, so Ask Ventiq searches by meaning as well as words. */
    private function embed(Organization $org, Embedder $embedder): void
    {
        $this->section('Embeddings (' . $embedder->model() . ')');
        if (!$embedder->enabled()) {
            $this->results[] = ['Embeddings', 'FAILED: not set up (OLLAMA_URL, OLLAMA_EMBED_MODEL)', ''];

            return;
        }
        $waiting = MemoryChunk::where('organization_id', $org->id)->whereNull('embedding')->count();
        $this->line("{$waiting} of " . MemoryChunk::where('organization_id', $org->id)->count() . ' passages to embed…');
        [$seconds] = $this->timed(fn () => EmbedMemoryChunks::dispatchSync($org->id));
        $left = MemoryChunk::where('organization_id', $org->id)->whereNull('embedding')->count();
        $this->line($left ? "<error>{$left} still without a vector.</error> Is `ollama pull {$embedder->model()}` done?" : 'All embedded.');
        $this->results[] = ['Embeddings', $left ? "FAILED: {$left} left" : 'ok, ' . ($waiting - $left) . ' embedded', $seconds];
    }

    /** The live console's sticky notes: a line typed, and whether it's a theme, decision, action or question. */
    private function stickyNotes(AIService $ai): void
    {
        $this->section('Sticky notes while typing');
        $lines = [
            'road to Mount Moorosi washed out again on Saturday',
            'agreed: pre-position a month of ORS at every clinic beyond the river',
            'Teboho to ask the council to grade the Mphaki road by Friday',
            'tea break',
        ];
        $recent = [];
        $found = 0;
        $total = 0.0;
        foreach ($lines as $line) {
            [$seconds, $result] = $this->timed(fn () => $ai->generate((new SegmentInsightPrompt)->with([
                'line' => $line, 'presenter' => 'Teboho Mokhele', 'topic' => 'Rainy season preparedness', 'recent_lines' => implode("\n", $recent),
            ])));
            $total += $seconds;
            $recent[] = $line;
            if (!$result->success) {
                $this->results[] = ['Sticky notes', 'FAILED: ' . Str::limit((string) $result->error, 60), $seconds];

                return;
            }
            $tag = SegmentInsightPrompt::parse($result->content);
            $tag['category'] !== 'none' && $found++;
            $this->line(sprintf('  %-70s → %s', Str::limit($line, 68), $tag['category'] === 'none' ? '<comment>no note</comment>' : "<info>{$tag['category']}</info>: {$tag['text']}"));
        }
        $this->results[] = ['Sticky notes', "ok, {$found} of " . count($lines) . ' lines made a note', round($total, 1)];
    }

    /** An agenda item written up from its notes, as the minutes have it. */
    private function writeUp(AIService $ai, Session $meeting): void
    {
        // The item with the most notes, in this or the meetings before it.
        $segment = \App\Models\SessionSegment::whereIn('session_id', Session::where('organization_id', $meeting->organization_id)->where('type', $meeting->type)
            ->where('status', 'reported')->orderByDesc('date')->limit(6)->pluck('id'))
            ->whereNotNull('raw_log')->get()->sortByDesc(fn ($s) => count($s->raw_log))->first();
        $meeting = $segment->session;
        $this->section("Write-up: {$meeting->resolved_title}, {$meeting->date->format('j M')}, {$segment->title}");
        $notes = collect($segment->raw_log)->pluck('text')->implode("\n");
        $this->line('<comment>Notes:</comment> ' . str_replace("\n", ' / ', $notes));

        [$seconds, $result] = $this->timed(fn () => $ai->generate((new SegmentSummaryPrompt)->with([
            'presenter' => $segment->presenter_name, 'role' => $segment->role, 'topic' => $segment->title, 'raw_notes' => $notes,
            'sections' => SessionType::sections($meeting->type), 'agenda' => SessionType::takesMinutes($meeting->type), 'lead' => $segment->lead(),
        ])));
        if (!$result->success) {
            $this->results[] = ['Write-up', 'FAILED: ' . Str::limit((string) $result->error, 60), $seconds];

            return;
        }
        $parsed = $ai->parseSections($result->content, array_keys(SessionType::sections($meeting->type)));
        foreach (SessionType::sections($meeting->type) as $key => $def) {
            $this->line("<info>{$def['label']}:</info> " . ($parsed[$key] ?? '<error>(missing)</error>'));
        }
        $missing = collect(array_keys(SessionType::sections($meeting->type)))->reject(fn ($k) => isset($parsed[$k]));
        $this->results[] = ['Write-up', $missing->isEmpty() ? 'ok, every section' : 'FAILED: missing ' . $missing->implode(', '), $seconds];
    }

    /** The meeting's action points picked out of its notes. */
    private function actionPoints(AIService $ai, Session $meeting, Organization $org): void
    {
        $this->section("Action points: {$meeting->resolved_title}, {$meeting->date->format('j M')}");
        $notes = $meeting->segments->map(fn ($s) => ($lines = collect($s->raw_log ?? [])->pluck('text')->implode("\n")) === '' ? null : "## {$s->title}\n{$lines}")->filter()->implode("\n\n");
        $people = User::where('organization_id', $org->id)->pluck('name')->merge($meeting->segments->pluck('presenter_name'))->unique();

        [$seconds, $result] = $this->timed(fn () => $ai->generate((new ActionPointsPrompt)->with([
            'title' => $meeting->resolved_title, 'date' => $meeting->date->format('l j F Y'),
            'people' => $people->map(fn ($n) => "- {$n}")->implode("\n"), 'notes' => $notes, 'open' => '',
        ])));
        if (!$result->success) {
            $this->results[] = ['Action points', 'FAILED: ' . Str::limit((string) $result->error, 60), $seconds];

            return;
        }
        $points = ExtractActionPoints::parse($result->content);
        foreach ($points as $p) {
            $this->line("  • {$p['task']}" . ($p['owner'] ? " <comment>({$p['owner']})</comment>" : '') . ($p['due'] ? " by {$p['due']}" : ''));
        }
        $points || $this->line('<comment>None found.</comment> Raw answer: ' . Str::limit($result->content, 300));
        $this->results[] = ['Action points', $points ? 'ok, ' . count($points) . ' found' : 'ok, none found (check the raw answer)', $seconds];
    }

    private function askVentiq(AskVentiq $ask, Organization $org, string $question): void
    {
        $this->section("Ask Ventiq: {$question}");
        [$seconds, $answer] = $this->timed(fn () => $ask->answer($org, $question));
        if (!$answer['ok']) {
            $this->results[] = ['Ask: ' . Str::limit($question, 34), 'FAILED: ' . Str::limit((string) $answer['error'], 50), $seconds];

            return;
        }
        $this->line($answer['answer']);
        foreach ($answer['sources'] as $s) {
            $this->line("  <comment>[{$s['n']}]</comment> {$s['title']}" . (!empty($s['date']) ? ", {$s['date']}" : ''));
        }
        $cited = preg_match('/\[\d+\]/', $answer['answer']);
        $this->results[] = ['Ask: ' . Str::limit($question, 34), 'ok, ' . count($answer['sources']) . ' sources' . ($cited ? '' : ', none cited'), $seconds];
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<fg=blue;options=bold>── {$title}</>");
    }

    /** @return array{0: float, 1: mixed} seconds taken, and what came back */
    private function timed(\Closure $do): array
    {
        $start = microtime(true);
        $result = $do();

        return [round(microtime(true) - $start, 1), $result];
    }
}
