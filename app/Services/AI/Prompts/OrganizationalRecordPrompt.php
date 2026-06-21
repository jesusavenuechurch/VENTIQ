<?php

namespace App\Services\AI\Prompts;

class OrganizationalRecordPrompt extends PromptBuilder
{
    public function build(): string
    {
        $rawInput    = $this->variables['raw_input']    ?? '';
        $recordType  = $this->variables['record_type']  ?? 'meeting';
        $organisation = $this->variables['organisation'] ?? '';
        $eventName   = $this->variables['event_name']   ?? '';
        $date        = $this->variables['date']          ?? '';
        $venue       = $this->variables['venue']         ?? '';

        // Context block — only included if values exist
        $contextBlock = '';
        if ($organisation) $contextBlock .= "Organisation: {$organisation}\n";
        if ($eventName)    $contextBlock .= "Event/Meeting: {$eventName}\n";
        if ($date)         $contextBlock .= "Date: {$date}\n";
        if ($venue)        $contextBlock .= "Venue: {$venue}\n";

        // Tone instruction changes based on record type
        $toneInstruction = match ($recordType) {
            'meeting'       => 'Extract formal meeting structure. Decisions and resolutions should be clearly stated.',
            'brainstorming' => 'This is a brainstorming session. Ideas should be preserved as-is. Decisions may be tentative.',
            'planning'      => 'This is a planning session. Focus on action items and timelines.',
            'report'        => 'This is an operational report. Focus on status updates and outstanding matters.',
            'update'        => 'This is a ministry or department update. Preserve context and tone.',
            'committee'     => 'This is a committee session. Resolutions and voting outcomes are important.',
            default         => 'Extract all relevant organizational information.',
        };

        return <<<PROMPT
{$this->systemContext()}

You are processing a raw organizational {$recordType} record.
The input may be messy, incomplete, shorthand, or copied from WhatsApp or handwritten notes.
Your job is to extract structured organizational meaning — not to rewrite or summarize.

{$toneInstruction}

{$contextBlock}

RAW INPUT:
{$rawInput}

Extract and return EXACTLY these six sections. 
If a section has no content, write "None identified." — never leave a section empty.
Each item in a list should be on its own line starting with "•".
Do not add headings, explanations, or commentary inside sections.

AGENDA:
[What topics or agenda items were discussed? One item per line.]

DISCUSSION_POINTS:
[What was actually said, debated, or noted? One point per line. Preserve names if mentioned.]

DECISIONS:
[What was concluded, approved, or resolved? One decision per line. Be specific.]

ACTION_ITEMS:
[Who must do what? Format each as: "• [Action] — [Person if mentioned] — [Deadline if mentioned]"]

OPEN_ISSUES:
[What remains unresolved or needs follow-up? One issue per line.]

SUGGESTED_TITLE:
[A short professional title for this record, 5–8 words. Only one title, no alternatives.]
PROMPT;
    }
}