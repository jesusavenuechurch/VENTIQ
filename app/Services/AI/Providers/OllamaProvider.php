<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AIProvider;
use App\Services\AI\Results\GeneratedContent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaProvider implements AIProvider
{
    private string $baseUrl;
    private string $model;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl = config('ai.ollama.url', 'http://localhost:11434');
        $this->model   = config('ai.ollama.model', 'qwen2.5:7b');
        $this->timeout = config('ai.ollama.timeout', 300);
    }

    public function generate(string $prompt, array $options = []): GeneratedContent
    {
        $start = microtime(true);
        $model = !empty($options['fast']) && config('ai.ollama.fast_model') ? config('ai.ollama.fast_model') : $this->model;

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/generate", array_filter([
                    'model'  => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                    // Only to a model that reasons first; others refuse it.
                    'think'  => !empty($options['think']) && self::thinks($model) ? $options['think'] : null,
                    'options' => array_filter([
                        'temperature' => (float) config('ai.ollama.temperature', 0.7),
                        'top_p'       => 0.9,
                        // How much prompt it may take; Ollama's default is small, and it drops the start of a longer one.
                        'num_ctx'     => config('ai.ollama.num_ctx') ? (int) config('ai.ollama.num_ctx') : null,
                    ], fn ($v) => $v !== null),
                ], fn ($v) => $v !== null));

            if (!$response->successful()) {
                return GeneratedContent::failure(
                    "Ollama returned status {$response->status()}",
                    $this->name()
                );
            }

            $content = $response->json('response', '');

            if (empty($content)) {
                return GeneratedContent::failure('Empty response from Ollama', $this->name());
            }

            return GeneratedContent::success(
                content:  trim($content),
                provider: $this->name(),
                duration: round(microtime(true) - $start, 2),
            );

        } catch (\Exception $e) {
            Log::error('Ollama generate failed', ['error' => $e->getMessage()]);
            return GeneratedContent::failure($e->getMessage(), $this->name());
        }
    }

    /** A model that thinks before answering, and takes a level for it: gpt-oss, unless OLLAMA_THINKING says otherwise. */
    public static function thinks(string $model): bool
    {
        $set = config('ai.ollama.thinking');

        return $set === null || $set === '' ? str_starts_with($model, 'gpt-oss') : filter_var($set, FILTER_VALIDATE_BOOLEAN);
    }

    public function stream(string $prompt): \Generator
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withOptions(['stream' => true])
                ->post("{$this->baseUrl}/api/generate", [
                    'model'  => $this->model,
                    'prompt' => $prompt,
                    'stream' => true,
                ]);

            $body = $response->getBody();

            while (!$body->eof()) {
                $line = '';
                while (!$body->eof()) {
                    $char = $body->read(1);
                    if ($char === "\n") break;
                    $line .= $char;
                }

                if (empty(trim($line))) continue;

                $data = json_decode($line, true);
                if (isset($data['response'])) {
                    yield $data['response'];
                }

                if ($data['done'] ?? false) break;
            }

        } catch (\Exception $e) {
            Log::error('Ollama stream failed', ['error' => $e->getMessage()]);
            yield '';
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/api/tags");
            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function name(): string
    {
        return 'ollama/' . $this->model;
    }
}