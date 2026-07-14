<?php

namespace App\Services\AI\Prompts;

class SegmentSummaryPrompt extends PromptBuilder
{
    public function build(): string
    {
        $presenter = $this->variables['presenter'] ?? 'The presenter';
        $topic     = $this->variables['topic']     ?? '';
        $rawNotes  = $this->variables['raw_notes'] ?? '';

        $topicLine = $topic ? "Topic: {$topic}\n" : '';

        return <<<PROMPT
{$this->systemContext()}

You are summarizing one presenter's segment from a live-captured presentation session. The notes below were typed live while {$presenter} was presenting — they may be fragmented, shorthand, or incomplete.

Presenter: {$presenter}
{$topicLine}
RAW NOTES:
{$rawNotes}

Extract and return EXACTLY these four sections. If a section has no content, write "None identified." — never leave a section empty.

SUMMARY:
[A 2-3 sentence summary of what this presenter covered.]

KEY POINTS:
[What stood out as the substantive content of this presentation? One point per line starting with "•".]

FOLLOW-UPS:
[Gaps, unclear points, or things worth following up on. One point per line starting with "•".]

QUESTIONS:
[Questions raised during or about this presentation. One per line starting with "•".]
PROMPT;
    }
}