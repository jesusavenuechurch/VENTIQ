<?php

namespace App\Services\AI\Prompts;

// Step 2 of Assist search: phrases the already-retrieved database results
// into a natural answer. Deliberately instructed to use nothing beyond what
// was actually found — this is the "humanize the response" step, not a
// second chance for the model to guess.
class AssistAnswerPrompt extends PromptBuilder
{
    public function build(): string
    {
        $question = $this->variables['question'] ?? '';
        $findings = $this->variables['findings'] ?? 'No matching records were found.';

        return <<<PROMPT
You are Ventiq Assist, answering a question for someone inside their own
organization about their organization's Sessions (meetings, workshops,
trainings) that Ventiq has captured.

QUESTION:
{$question}

DATABASE FINDINGS (this is the complete, authoritative result of searching
the organization's records — do not assume anything beyond it):
{$findings}

Write a short, direct answer using ONLY the findings above. If the findings
say nothing was found, say so plainly and suggest rephrasing — never
invent a session, a number, or a detail that isn't in the findings.
Reference session titles/dates when useful. No preamble, no disclaimers.
PROMPT;
    }
}
