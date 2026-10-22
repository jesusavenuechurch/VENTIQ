<?php

namespace App\Contracts\AI;

use App\Services\AI\Results\GeneratedContent;

interface AIProvider
{
    /**
     * Generate a single response from a prompt.
     *
     * @param array{think?: ?string, fast?: bool} $options what the prompt
     *   asks of the model: how hard to think (low, medium, high) for a
     *   model that reasons first, and whether a faster model will do.
     *   A provider ignores what it can't do.
     */
    public function generate(string $prompt, array $options = []): GeneratedContent;

    /**
     * Generate a streaming response — yields chunks.
     */
    public function stream(string $prompt): \Generator;

    /**
     * Check if the provider is available/reachable.
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name for logging/display.
     */
    public function name(): string;
}