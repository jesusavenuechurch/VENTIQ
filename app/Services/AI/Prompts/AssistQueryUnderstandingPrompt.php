<?php

namespace App\Services\AI\Prompts;

// Step 1 of Assist search: turns a free-text question into structured
// filters the database can actually run. Never sees other orgs' data and
// never writes the final answer — its only job is to decide what to look
// up. The org scope for that lookup is applied afterward by
// AssistSearchService, never taken from this model's output.
class AssistQueryUnderstandingPrompt extends PromptBuilder
{
    public function build(): string
    {
        $question = $this->variables['question'] ?? '';
        $today    = $this->variables['today']    ?? now()->toDateString();

        return <<<PROMPT
You turn a question about an organization's past meetings into a strict JSON
search filter. Today's date is {$today}.

QUESTION:
{$question}

Return ONLY a single JSON object, no other text, no markdown fences, in
exactly this shape:

{"keywords": ["..."], "date_from": "YYYY-MM-DD" or null, "date_to": "YYYY-MM-DD" or null, "count_only": true or false}

Rules:
- "keywords": topic words worth searching for in meeting notes (e.g. a
  question about "procurement" → ["procurement"]). Empty array if the
  question is purely a count/date question with no topic.
- "date_from"/"date_to": resolve relative ranges like "this year", "last
  quarter", "this month" against today's date. null/null if no date range
  is implied.
- "count_only": true if the question is asking "how many" rather than
  "what happened" or "tell me about".

Respond with the JSON object only.
PROMPT;
    }
}
