<?php

namespace App\Services\AI\Prompts;

class SegmentSummaryPrompt extends PromptBuilder
{
    public function build(): string
    {
        $presenter = $this->variables['presenter'] ?? 'This person';
        $role      = $this->variables['role']      ?? null;
        $topic     = $this->variables['topic']      ?? '';
        $rawNotes  = $this->variables['raw_notes']  ?? '';
        $sections  = $this->variables['sections']   ?? [];

        $roleLine  = $role  ? "Role: {$role}\n"  : '';
        $topicLine = $topic ? "Topic: {$topic}\n" : '';

        $sectionBlocks = collect($sections)
            ->map(fn ($def, $key) => strtoupper($key) . ":\n[{$def['prompt']}]")
            ->implode("\n\n");

        return <<<PROMPT
{$this->systemContext()}

You are summarizing one person's segment from a live-captured session. The notes below were typed live while {$presenter} was speaking — they may be fragmented, shorthand, or incomplete.

Presenter: {$presenter}
{$roleLine}{$topicLine}
RAW NOTES:
{$rawNotes}

Extract and return EXACTLY these sections, in this order. If a section has no content, write "None identified." — never leave a section empty.

{$sectionBlocks}
PROMPT;
    }
}