<?php

namespace App\Services\AI\Prompts;

class SessionSummaryPrompt extends PromptBuilder
{
    public function build(): string
    {
        $title            = $this->variables['title']             ?? 'This session';
        $segmentSummaries = $this->variables['segment_summaries'] ?? '';

        return <<<PROMPT
{$this->systemContext()}

You are producing an overall summary across every presenter in "{$title}", based on their individual summaries below.

INDIVIDUAL SUMMARIES:
{$segmentSummaries}

Extract and return EXACTLY these two sections. If a section has no content, write "None identified." — never leave a section empty.

THEMES:
[The recurring themes or topics across presenters. One per line starting with "•".]

RECOMMENDATIONS:
[Overall recommendations based on everything presented. One per line starting with "•".]
PROMPT;
    }
}