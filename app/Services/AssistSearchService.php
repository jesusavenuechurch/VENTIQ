<?php

namespace App\Services;

use App\Models\Session;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\AssistAnswerPrompt;
use App\Services\AI\Prompts\AssistQueryUnderstandingPrompt;
use App\Services\AI\Results\GeneratedContent;
use Illuminate\Support\Collection;

class AssistSearchService
{
    public function __construct(private AIService $ai) {}

    /**
     * Step 1 — turn the free-text question into search filters. Returns
     * null when the AI call itself genuinely failed (provider down,
     * timed out) — the caller should stop there rather than pay for a
     * second slow call that's very likely to fail the same way. A
     * malformed-but-successful response still degrades gracefully to
     * "no filters" (search everything), since the AI did respond.
     */
    public function understandQuestion(string $question): ?array
    {
        $default = ['keywords' => [], 'date_from' => null, 'date_to' => null, 'count_only' => false];

        $prompt = (new AssistQueryUnderstandingPrompt())->with([
            'question' => $question,
            'today'    => now()->toDateString(),
        ]);

        $result = $this->ai->generate($prompt);

        if (!$result->success) {
            return null;
        }

        $json = json_decode($this->extractJson($result->content), true);

        if (!is_array($json)) {
            return $default;
        }

        return array_merge($default, array_intersect_key($json, $default));
    }

    /**
     * Step 2 — the actual database search. $organizationId is always the
     * authenticated user's own org, passed in by the caller — never taken
     * from the filters the model produced. That's what keeps this safe
     * even if the understanding step hallucinates.
     */
    public function search(int $organizationId, array $filters): Collection
    {
        $query = Session::forOrganization($organizationId)
            ->where('status', 'reported')
            ->whereNotNull('session_report');

        if ($filters['date_from'] ?? null) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] ?? null) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $keywords = array_filter($filters['keywords'] ?? []);

        if (!empty($keywords)) {
            $query->whereFullText('session_report', implode(' ', $keywords));
        }

        return $query->orderByDesc('date')->limit(15)->get();
    }

    /**
     * Step 3 — phrase the retrieved rows into a natural answer. The model
     * only ever sees what search() actually found, formatted as plain
     * findings text, never raw DB rows or other orgs' data.
     */
    public function answer(string $question, Collection $sessions, bool $countOnly): GeneratedContent
    {
        $findings = $this->formatFindings($sessions, $countOnly);

        $prompt = (new AssistAnswerPrompt())->with([
            'question' => $question,
            'findings' => $findings,
        ]);

        return $this->ai->generate($prompt);
    }

    private function formatFindings(Collection $sessions, bool $countOnly): string
    {
        if ($sessions->isEmpty()) {
            return 'No matching sessions were found.';
        }

        if ($countOnly) {
            return "Matching session count: {$sessions->count()}\n\n" .
                $sessions->map(fn (Session $s) => "- {$s->resolved_title} ({$s->date?->format('d M Y')})")->implode("\n");
        }

        return $sessions->map(function (Session $s) {
            $excerpt = str($s->session_report)->limit(600);
            return "SESSION: {$s->resolved_title}\nDATE: {$s->date?->format('d M Y')}\nREPORT EXCERPT:\n{$excerpt}";
        })->implode("\n\n---\n\n");
    }

    // Providers occasionally wrap JSON in prose or markdown fences despite
    // instructions — pull out the first {...} block rather than trusting
    // the response is bare JSON.
    private function extractJson(string $content): string
    {
        if (preg_match('/\{.*\}/s', $content, $match)) {
            return $match[0];
        }

        return $content;
    }
}
