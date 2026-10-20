<?php

namespace App\Services\Memory;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Embeddings from the Mac mini's Ollama (config ai.ollama). Only Ollama:
 * the other provider would send the organization's records abroad. When
 * the Mac mini can't be reached this returns null and search falls back
 * to keywords; the passages are embedded later (sessions:embed-memory).
 */
class Embedder
{
    public function model(): string
    {
        return (string) config('ai.ollama.embed_model', 'nomic-embed-text');
    }

    public function enabled(): bool
    {
        return config('ai.default') === 'ollama' && (bool) config('ai.ollama.url');
    }

    /**
     * @param string[] $texts
     * @return array<int, float[]>|null one vector per text, or null when it couldn't be done
     */
    public function embed(array $texts): ?array
    {
        if (!$this->enabled() || $texts === []) {
            return $texts === [] ? [] : null;
        }

        try {
            $response = Http::timeout((int) config('ai.ollama.embed_timeout', 60))
                ->post(rtrim(config('ai.ollama.url'), '/') . '/api/embed', ['model' => $this->model(), 'input' => array_values($texts)]);
            $vectors = $response->successful() ? $response->json('embeddings') : null;

            return is_array($vectors) && count($vectors) === count($texts) ? $vectors : null;
        } catch (\Throwable $e) {
            Log::info('Embedding unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
