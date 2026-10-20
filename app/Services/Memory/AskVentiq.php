<?php

namespace App\Services\Memory;

use App\Models\ActionPoint;
use App\Models\MemoryChunk;
use App\Models\Organization;
use App\Models\Session;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\MemoryAnswerPrompt;
use App\Support\SessionType;
use Illuminate\Support\Collection;

/**
 * Ask Ventiq over an organization's memory: the passages that match the
 * question, a few counts, and one call to the model on the Mac mini to
 * put the answer in words, citing the sessions it came from.
 */
class AskVentiq
{
    public function __construct(private MemorySearch $search, private AIService $ai) {}

    /** @return array{ok: bool, answer: ?string, sources: array, error: ?string} */
    public function answer(Organization $organization, string $question): array
    {
        $dates = QuestionDates::parse($question);
        $chunks = $this->search->search($organization->id, $question, $dates);
        [$sourceText, $sources] = $this->sources($chunks);

        $result = $this->ai->generate((new MemoryAnswerPrompt)->with([
            'question'     => $question,
            'organization' => $organization->name,
            'sources'      => $sourceText ?: 'Nothing was found for this question.',
            'facts'        => $this->facts($organization, $question, $dates),
            'today'        => now()->format('l j F Y'),
        ]));

        if (!$result->success) {
            return ['ok' => false, 'answer' => null, 'sources' => [], 'error' => $result->error];
        }

        // Only the sessions the answer cites (all of them when it cites none).
        preg_match_all('/\[(\d+)\]/', $result->content, $m);
        $cited = array_unique(array_map('intval', $m[1]));
        $shown = $cited ? array_values(array_filter($sources, fn ($s) => in_array($s['n'], $cited, true))) : $sources;

        return ['ok' => true, 'answer' => $result->content, 'sources' => $shown, 'error' => null];
    }

    /**
     * The passages grouped by session, numbered: the text for the model,
     * and the list the answer links to.
     */
    private function sources(Collection $chunks): array
    {
        $groups = $chunks->groupBy('session_id');
        $text = [];
        $list = [];
        $n = 0;
        foreach ($groups as $sessionId => $passages) {
            $session = $passages->first()->session;
            if (!$session) {
                continue;
            }
            $n++;
            $title = $session->resolved_title;
            $date = $session->date?->format('j M Y');
            $text[] = "[{$n}] {$title}" . ($date ? ", {$date}" : '') . ' (' . SessionType::label($session->type) . ")\n"
                // Each passage by its part ("Logistics"), the session and date being in the heading.
                . $passages->map(fn (MemoryChunk $c) => '- ' . trim(preg_replace('/^' . preg_quote("{$title} · ", '/') . '[^·]+ · /u', '', $c->title)) . ":\n" . $c->body)->implode("\n");
            $list[] = ['n' => $n, 'title' => $title, 'date' => $date, 'url' => route('sessions.report', $session->id)];
        }

        return [implode("\n\n", $text), $list];
    }

    /** Counts the passages can't give: how many sessions, and what's still open. */
    private function facts(Organization $organization, string $question, ?array $dates): string
    {
        $held = Session::forOrganization($organization->id)->where('status', 'reported')
            ->when($dates, fn ($q) => $q->whereBetween('date', [$dates[0]->toDateString(), $dates[1]->toDateString()]))
            ->get(['type']);
        $when = $dates ? 'from ' . $dates[0]->format('j M Y') . ' to ' . $dates[1]->format('j M Y') : 'in all';
        $byType = $held->countBy(fn ($s) => SessionType::label($s->type))->map(fn ($n, $type) => "{$n} {$type}")->implode(', ');
        $lines = ["Sessions held {$when}: {$held->count()}" . ($byType ? " ({$byType})" : '') . '.'];

        // Asked about what's to be done: the open action points themselves.
        if (preg_match('/action|task|to.?do|overdue|outstanding|follow.?up|pending|open|owe|assigned|responsible|ketso|mosebetsi/i', $question)) {
            $open = ActionPoint::where('organization_id', $organization->id)->where('status', 'open')->with(['session', 'ownerUser', 'ownerClient'])->orderBy('due_date')->limit(20)->get();
            $lines[] = "Open action points: {$open->count()}" . ($open->count() === 20 ? ' or more' : '') . '.';
            foreach ($open as $a) {
                $lines[] = "- {$a->task} (owner: " . ($a->ownerLabel() ?? 'no one') . ($a->due_date ? ', due ' . $a->due_date->format('j M Y') . ($a->isOverdue() ? ', OVERDUE' : '') : '') . ($a->session ? ", from {$a->session->resolved_title} on {$a->session->date?->format('j M Y')}" : '') . ')';
            }
        }

        return implode("\n", $lines);
    }
}
