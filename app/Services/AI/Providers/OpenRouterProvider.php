<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AIProvider;
use App\Services\AI\Results\GeneratedContent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterProvider implements AIProvider
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int    $maxTokens;
    private float  $temperature;

    public function __construct()
    {
        $this->apiKey      = config('ai.openrouter.api_key');
        $this->baseUrl     = config('ai.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $this->model       = config('ai.openrouter.model', 'meta-llama/llama-3.3-70b-instruct:free');
        $this->maxTokens   = (int) config('ai.openrouter.max_tokens', 1024);
        $this->temperature = (float) config('ai.openrouter.temperature', 0.7);
    }

    public function generate(string $prompt): GeneratedContent
    {
        $start = microtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'       => $this->model,
                    'max_tokens'  => $this->maxTokens,
                    'temperature' => $this->temperature,
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('OpenRouter request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return GeneratedContent::failure(
                    "OpenRouter returned status {$response->status()}",
                    $this->name()
                );
            }

            $content = $response->json('choices.0.message.content', '');

            if (empty(trim($content))) {
                return GeneratedContent::failure('Empty response from OpenRouter', $this->name());
            }

            return GeneratedContent::success(
                content:  trim($content),
                provider: $this->name(),
                duration: round(microtime(true) - $start, 2),
            );

        } catch (\Exception $e) {
            Log::error('OpenRouter generate failed', ['error' => $e->getMessage()]);
            return GeneratedContent::failure($e->getMessage(), $this->name());
        }
    }

    public function stream(string $prompt): \Generator
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->withOptions(['stream' => true])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'       => $this->model,
                    'max_tokens'  => $this->maxTokens,
                    'temperature' => $this->temperature,
                    'stream'      => true,
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            $body = $response->getBody();

            while (!$body->eof()) {
                $line = '';
                while (!$body->eof()) {
                    $char = $body->read(1);
                    if ($char === "\n") break;
                    $line .= $char;
                }

                $line = trim($line);
                if (empty($line) || $line === 'data: [DONE]') continue;

                // SSE format: "data: {...}"
                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    $data = json_decode($json, true);
                    $token = $data['choices'][0]['delta']['content'] ?? '';
                    if (!empty($token)) {
                        yield $token;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('OpenRouter stream failed', ['error' => $e->getMessage()]);
            yield '';
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(5)
                ->get("{$this->baseUrl}/models");
            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function name(): string
    {
        return 'openrouter/' . $this->model;
    }
}