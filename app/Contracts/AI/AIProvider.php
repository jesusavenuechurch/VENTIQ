<?php

namespace App\Contracts\AI;

use App\Services\AI\Results\GeneratedContent;

interface AIProvider
{
    /**
     * Generate a single response from a prompt.
     */
    public function generate(string $prompt): GeneratedContent;

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