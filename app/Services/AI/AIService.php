<?php

namespace App\Services\AI;

use App\Contracts\AI\AIProvider;
use App\Services\AI\Prompts\PromptBuilder;
use App\Services\AI\Results\GeneratedContent;
use Illuminate\Support\Facades\Log;

class AIService
{
    private AIProvider $provider;

    public function __construct(AIProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Generate content from a PromptBuilder instance.
     */
    public function generate(PromptBuilder $prompt): GeneratedContent
    {
        if (!$this->provider->isAvailable()) {
            return GeneratedContent::failure(
                'Ventiq Assist is temporarily unavailable. Please try again shortly.',
                $this->provider->name()
            );
        }

        $result = $this->provider->generate($prompt->build());

        if ($result->success) {
            Log::info('Ventiq Assist generated content', [
                'provider' => $result->provider,
                'duration' => $result->duration,
            ]);
        }

        return $result;
    }

    /**
     * Stream content from a PromptBuilder instance.
     */
    public function stream(PromptBuilder $prompt): \Generator
    {
        if (!$this->provider->isAvailable()) {
            yield 'Ventiq Assist is temporarily unavailable.';
            return;
        }

        yield from $this->provider->stream($prompt->build());
    }

    /**
     * Parse sections from AI response.
     * Looks for HEADING: content patterns.
     */
    public function parseSections(string $content): array
    {
        $sections = [];
        $pattern  = '/^([A-Z_]+):\s*\n(.*?)(?=\n[A-Z_]+:|$)/ms';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key            = strtolower(trim($match[1]));
            $sections[$key] = trim($match[2]);
        }

        return $sections;
    }

    public function isAvailable(): bool
    {
        return $this->provider->isAvailable();
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }
}