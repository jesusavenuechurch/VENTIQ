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
        // A meeting's agenda item: led by someone (or no one), not a presenter's turn.
        $agenda    = (bool) ($this->variables['agenda'] ?? false);

        $roleLine  = $role  ? "Role: {$role}\n"  : '';
        $topicLine = $topic ? "Topic: {$topic}\n" : '';

        if ($agenda) {
            $lead = $this->variables['lead'] ?? null;
            $leadLine = $lead ? "Led by: {$lead}\n" : '';

            $intro = <<<INTRO
You are writing the minutes of one agenda item from a meeting. The notes below were typed live by the secretary while it was discussed; they may be fragmented, shorthand, or incomplete. Write as minutes are written: formal, past tense, third person. Record only what the notes say; don't invent names, figures or decisions. Whoever led the item reported on it: refer to them by name, and give no one a title (Chairperson, Director…) the notes don't give them. Keep each figure with what it counts.

Agenda item: {$topic}
{$leadLine}
INTRO;
        } else {
            $intro = <<<INTRO
You are summarizing one person's segment from a live-captured session. The notes below were typed live while {$presenter} was speaking — they may be fragmented, shorthand, or incomplete.

Presenter: {$presenter}
{$roleLine}{$topicLine}
INTRO;
        }

        $sectionBlocks = collect($sections)
            ->map(fn ($def, $key) => strtoupper($key) . ":\n[{$def['prompt']}]")
            ->implode("\n\n");

        return <<<PROMPT
{$this->systemContext()}

{$intro}RAW NOTES:
{$rawNotes}

Extract and return EXACTLY these sections, in this order. If a section has no content, write "None identified." — never leave a section empty.

{$sectionBlocks}
PROMPT;
    }
}