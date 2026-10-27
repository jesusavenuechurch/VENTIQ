<?php

namespace App\Services\AI\Prompts;

class SegmentInsightPrompt extends PromptBuilder
{
    // A sticky note has to keep up with the typing: a quick, small call.
    public function think(): ?string
    {
        return 'low';
    }

    public function fast(): bool
    {
        return true;
    }

    public function build(): string
    {
        $line     = $this->variables['line']         ?? '';
        $presenter= $this->variables['presenter']     ?? '';
        $topic    = $this->variables['topic']         ?? '';
        $recent   = $this->variables['recent_lines']  ?? '';

        $context = '';
        if ($presenter) $context .= "Presenter: {$presenter}\n";
        if ($topic)     $context .= "Topic: {$topic}\n";
        if ($recent)    $context .= "Recent notes so far:\n{$recent}\n";

        return <<<PROMPT
{$this->systemContext()}

You are watching live notes being typed during a presentation, one line at a time. Decide whether the newest line is worth surfacing as a tagged insight, and if so, classify it. Most lines are not worth surfacing — only flag something genuinely substantive.

{$context}
NEWEST LINE:
{$line}

Classify the newest line into exactly ONE category. Be strict: when unsure, answer none.
- decision: the meeting agreed, decided, approved or resolved something ("agreed to…", "we'll go with…", "approved…"). A description of how things are, an opinion or a feature that already exists is NOT a decision.
- action: a task someone is to do from now on, with or without a name ("Teboho to call the council", "need to send the report by Friday", "follow up with…"). Something that can already be done, or was done, is NOT an action.
- question: the line asks something or raises an open question (a "?", or "whether/how/what/why…" left unanswered). A statement is NOT a question.
- theme: a topic or concern being discussed that is worth remembering (a problem, a risk, an important point), when it isn't a decision, action or question.
- none: everything else: greetings, opinions ("I love it"), descriptions, filler, transitions, or anything you'd have to guess about.

Only restate what the newest line itself says. Never add anything that isn't in it, and never describe the notes themselves.

Examples:
"agreed: a month of ORS at every clinic" -> CATEGORY: decision / TEXT: A month of ORS at every clinic
"Teboho to ask the council to grade the road by Friday" -> CATEGORY: action / TEXT: Teboho to ask the council to grade the road by Friday
"can we get a second vehicle before the rains?" -> CATEGORY: question / TEXT: A second vehicle before the rains?
"we can add multiple agendas, I love it" -> CATEGORY: none
"road to Mount Moorosi washed out again" -> CATEGORY: theme / TEXT: Road to Mount Moorosi washed out again

Respond in EXACTLY this format, nothing else, no extra commentary:
CATEGORY: [theme|decision|action|question|none]
TEXT: [a short, standalone restatement in under 12 words, or leave blank if none]
PROMPT;
    }

    /** The model's answer: CATEGORY and TEXT, or category none. */
    public static function parse(string $content): array
    {
        preg_match('/CATEGORY:\s*(\w+)/i', $content, $catMatch);
        preg_match('/TEXT:\s*(.+)/i', $content, $textMatch);

        $category = strtolower(trim($catMatch[1] ?? 'none'));
        if (!in_array($category, ['theme', 'decision', 'action', 'question'])) {
            $category = 'none';
        }

        return [
            'category' => $category,
            'text'     => trim($textMatch[1] ?? ''),
        ];
    }
}
