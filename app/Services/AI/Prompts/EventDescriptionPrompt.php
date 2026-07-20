<?php

namespace App\Services\AI\Prompts;

class EventDescriptionPrompt extends PromptBuilder
{
    public function build(): string
    {
        $name     = $this->variables['name'] ?? 'Untitled Event';
        $category = $this->variables['category'] ?? 'general';
        $date     = $this->variables['date'] ?? 'upcoming';
        $venue    = $this->variables['venue'] ?? '';
        $audience = $this->variables['audience'] ?? 'general public';
        $notes    = $this->variables['notes'] ?? '';
        $tone     = $this->variables['tone'] ?? 'professional';

        $venueText    = $venue ? "Venue: {$venue}" : '';
        $notesText    = $notes ? "Additional notes from organiser: {$notes}" : '';

        return <<<PROMPT
        {$this->systemContext()}

        Generate event content for the following event:

        Event Name: {$name}
        Category: {$category}
        Date: {$date}
        {$venueText}
        Target Audience: {$audience}
        Tone: {$tone}
        {$notesText}

        Please provide the following in this EXACT format with these EXACT headings:

        DESCRIPTION:
        [Write a compelling 3-4 paragraph event description that captures the essence, purpose, and value of attending. Make it engaging and appropriate for the tone specified.]

        TAGLINE:
        [Write one punchy, memorable tagline under 15 words]

        WHATSAPP:
        [Write a WhatsApp invite message under 100 words, conversational, with key details and a call to action]

        FACEBOOK:
        [Write a Facebook post caption with emojis, under 150 words, engaging and shareable]

        HASHTAGS:
        [Write 8-10 relevant hashtags including #MaseruEvents and #Lesotho variants where appropriate]

        TITLES:
        [Suggest 3 alternative creative event title options if the current name could be improved, one per line]

        Keep all content relevant to Lesotho and Southern African context where appropriate.
        PROMPT;
    }
}