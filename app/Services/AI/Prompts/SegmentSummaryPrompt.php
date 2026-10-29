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
            $quick = (bool) ($this->variables['quick'] ?? false);
            $leadLine = $lead ? ($quick ? "Organised by: {$lead}\n" : "Led by: {$lead}\n") : '';

            $intro = <<<INTRO
You are writing the minutes of one agenda item from a meeting. The notes below were captured while it was discussed: typed live by the secretary, a transcript of the recording written down by machine, or both. Typed notes may be fragmented or shorthand; a transcript may mishear names and words, so prefer the spelling of names given in the agenda and the typed notes.

Write as minutes are written: formal, past tense, impersonal. Report what was discussed, noted and agreed, not who said it: "The meeting discussed…", "It was noted that…", "It was agreed that…", "The discussion focused on…". Name a person only where the notes themselves say who said or will do something; a recording doesn't tell you who was speaking, and whoever led or organised the item did not necessarily say it all. Never write he, she, his or her, and don't stand in for a person with "the presenter" or "the speaker": write "It was explained that…" instead. Record only what the notes say; don't invent names, figures or decisions, and give no one a title (Chairperson, Director…) the notes don't give them. Keep each figure with what it counts.

Agenda item: {$topic}
{$leadLine}
INTRO;
        } else {
            $intro = <<<INTRO
You are summarizing one person's segment from a live-captured session. The notes below were captured while {$presenter} was speaking: typed live, a transcript of the recording written down by machine, or both. They may be fragmented or shorthand, and a transcript may mishear names and words.

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