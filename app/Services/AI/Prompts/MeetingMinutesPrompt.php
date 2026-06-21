<?php

namespace App\Services\AI\Prompts;

class MeetingMinutesPrompt extends PromptBuilder
{
    public function build(): string
    {
        $eventName    = $this->variables['event_name'] ?? 'Meeting';
        $date         = $this->variables['date'] ?? 'Unknown date';
        $venue        = $this->variables['venue'] ?? '';
        $attendees    = $this->variables['attendees'] ?? '';
        $keyPoints    = $this->variables['key_points'] ?? '';
        $organisation = $this->variables['organisation'] ?? '';

        return <<<PROMPT
        {$this->systemContext()}

        Generate formal meeting minutes for the following:

        Organisation: {$organisation}
        Meeting/Event Name: {$eventName}
        Date: {$date}
        Venue: {$venue}
        Attendees: {$attendees}

        Key points, discussions and decisions from the meeting:
        {$keyPoints}

        Please generate complete formal meeting minutes in this EXACT format:

        MINUTES:
        [Full formatted meeting minutes including:
        - Header with organisation, meeting name, date, venue, attendees
        - Agenda items based on the key points provided
        - Discussion summaries for each item
        - Decisions made
        - Action items with responsible parties where mentioned]

        ACTION_ITEMS:
        [List each action item on a new line in format: "• [Action] — [Responsible party if mentioned] — [Deadline if mentioned]"]

        SUMMARY:
        [Executive summary of the meeting in 2-3 sentences]

        Use formal corporate language appropriate for official records.
        PROMPT;
    }
}