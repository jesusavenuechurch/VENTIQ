<?php

namespace App\Services\Memory;

use App\Models\MemoryChunk;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds the passages of one organization's records that answer a
 * question: by meaning (embeddings, when the Mac mini is reachable) and
 * by words (MySQL full-text), the two lists merged. The organization
 * always comes from the person asking, never from the question.
 */
class MemorySearch
{
    public const LIMIT = 8;

    private const STOP = ['the', 'and', 'for', 'what', 'did', 'was', 'were', 'are', 'about', 'with', 'that', 'this', 'from', 'have', 'has', 'our', 'any', 'who', 'how', 'when', 'where', 'which', 'tell', 'say', 'said', 'discuss', 'discussed', 'talk', 'talked', 'meeting', 'meetings', 'last', 'month', 'week', 'year', 'since', 'there', 'they', 'them', 'you', 'all'];

    public function __construct(private Embedder $embedder) {}

    /**
     * @param array{0: Carbon, 1: Carbon}|null $dates
     * @return Collection<int, MemoryChunk> best first
     */
    public function search(int $organizationId, string $question, ?array $dates = null, int $limit = self::LIMIT): Collection
    {
        $scope = fn () => MemoryChunk::where('organization_id', $organizationId)
            ->when($dates, fn ($q) => $q->whereBetween('date', [$dates[0]->toDateString(), $dates[1]->toDateString()]));

        $byWords = $this->byWords($scope(), $question);
        $byMeaning = $this->byMeaning($scope(), $question);

        // Reciprocal rank fusion: a passage high on either list rises.
        $scores = [];
        foreach ([$byWords, $byMeaning] as $list) {
            foreach (array_values($list) as $rank => $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1 / (60 + $rank);
            }
        }
        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $limit);

        // Nothing matched a topic: the most recent records, so "what happened last week" still has something.
        if ($ids === []) {
            return $scope()->whereIn('source_type', ['segment', 'minutes', 'report'])->orderByDesc('date')->limit($limit)->get();
        }

        $chunks = MemoryChunk::with('session')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $chunks[$id] ?? null)->filter()->values();
    }

    /** @return int[] ids, best first */
    private function byWords($query, string $question): array
    {
        $terms = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($question)))
            ->filter(fn ($t) => mb_strlen($t) >= 3 && !in_array($t, self::STOP, true))
            ->unique()->take(12)
            ->map(fn ($t) => $t . '*');
        if ($terms->isEmpty()) {
            return [];
        }
        $against = $terms->implode(' ');

        $ids = (clone $query)->whereRaw('MATCH(title, body) AGAINST (? IN BOOLEAN MODE)', [$against])
            ->orderByRaw('MATCH(title, body) AGAINST (? IN BOOLEAN MODE) DESC', [$against])
            ->limit(20)->pluck('id')->all();
        if ($ids !== []) {
            return $ids;
        }

        // Full-text misses words it ignores, and rows not yet committed:
        // then the words themselves, ranked by how often they appear.
        $words = $terms->map(fn ($t) => rtrim($t, '*'));
        $rows = $query->where(fn ($q) => $words->each(fn ($w) => $q->orWhere('body', 'like', "%{$w}%")->orWhere('title', 'like', "%{$w}%")))
            ->limit(200)->get(['id', 'title', 'body']);

        return $rows->map(fn ($r) => ['id' => $r->id, 'hits' => $words->sum(fn ($w) => substr_count(mb_strtolower("{$r->title} {$r->body}"), $w))])
            ->sortByDesc('hits')->take(20)->pluck('id')->all();
    }

    /** @return int[] ids, best first */
    private function byMeaning($query, string $question): array
    {
        $vector = $this->embedder->embed(["search_query: {$question}"])[0] ?? null;
        if (!$vector) {
            return [];
        }
        $q = array_values(unpack('g*', MemoryChunk::pack($vector)));
        $n = count($q);

        $scored = [];
        $query->where('embedding_model', $this->embedder->model())->whereNotNull('embedding')->select(['id', 'embedding'])
            ->chunkById(500, function ($rows) use (&$scored, $q, $n) {
                foreach ($rows as $row) {
                    $v = unpack('g*', $row->embedding);
                    if (count($v) !== $n) {
                        continue;
                    }
                    $dot = 0.0;
                    foreach ($q as $i => $x) {
                        $dot += $x * $v[$i + 1];
                    }
                    $scored[$row->id] = $dot;
                }
            });
        arsort($scored);

        // Only ones with something in common with the question.
        return array_keys(array_slice(array_filter($scored, fn ($s) => $s > 0.3), 0, 20, true));
    }
}
