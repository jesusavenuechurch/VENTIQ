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

        $result = $this->provider->generate($prompt->build(), ['think' => $prompt->think(), 'fast' => $prompt->fast()]);

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
     * The sections of an answer written as headings: "SUMMARY:", then its
     * lines until the next heading. Each section keeps all its lines (a
     * list of decisions, not just the first). Headings may be written
     * KEY_POINTS, KEY POINTS or FOLLOW-UPS (all read as key_points,
     * follow_ups), in bold or after a #, with text on the same line.
     *
     * Given the keys it asks for, only those count as headings, in any
     * case, so a line like "ORS: 400 sachets" stays in its section;
     * otherwise any line in capitals followed by a colon is one.
     */
    public function parseSections(string $content, ?array $keys = null): array
    {
        $key = fn (string $heading) => strtolower(preg_replace('/[\s\-]+/', '_', trim($heading)));
        $wanted = $keys ? array_map($key, $keys) : null;
        $sections = [];
        $current = null;

        foreach (preg_split('/\R/', $content) as $line) {
            if (preg_match('/^\s*(?:#{1,6}\s*)?\*{0,2}([A-Za-z][A-Za-z0-9 _\-]{0,40}?)\*{0,2}\s*:\s*\*{0,2}\s*(.*)$/u', $line, $m)) {
                $heading = $key($m[1]);
                $isHeading = $wanted ? in_array($heading, $wanted, true) : (bool) preg_match('/^[A-Z][A-Z0-9 _\-]*$/', trim($m[1]));
                if ($isHeading) {
                    $current = $heading;
                    $sections[$current] = trim($m[2]) === '' ? [] : [trim($m[2])];
                    continue;
                }
            }
            if ($current !== null) {
                $sections[$current][] = rtrim($line);
            }
        }

        return array_map(fn (array $lines) => trim(implode("\n", $lines)), $sections);
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