<?php

namespace App\Services\AI\Prompts;

/** From a session's notes: what people agreed to do, who, by when. Answered as JSON. */
class ActionPointsPrompt extends PromptBuilder
{
    public function build(): string
    {
        $date   = $this->variables['date'] ?? '';
        $title  = $this->variables['title'] ?? '';
        $people = $this->variables['people'] ?? '';
        $notes  = $this->variables['notes'] ?? '';
        $open   = $this->variables['open'] ?? '';
        $openBlock = $open !== '' ? "\nALREADY BEING TRACKED (don't repeat these):\n{$open}\n" : '';

        return <<<PROMPT
{$this->systemContext()}

These are the notes of "{$title}", held on {$date}. Find the action points: things a person or the team agreed to do after the session. Ignore things that were only discussed, decided or already done.

PEOPLE (owners are usually one of them):
{$people}
{$openBlock}
NOTES:
{$notes}

Answer with a JSON array and nothing else. One object per action point:
- "task": what is to be done, a short sentence starting with a verb
- "owner": who will do it, a name from PEOPLE if the notes say who, else ""
- "due": the date it's due as YYYY-MM-DD only if the notes give a day or date for that task (work out "by Friday" or "next week" from the session date), else "". Never guess one.

If there are none, answer []
PROMPT;
    }
}
