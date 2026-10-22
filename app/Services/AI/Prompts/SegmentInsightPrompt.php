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

Classify the newest line into exactly ONE category:
- theme: identifies a topic, subject area, or focus being discussed
- decision: something concluded, approved, or resolved
- action: something someone needs to do
- question: a question raised, by the presenter or an observer
- none: filler, transition, or not substantive enough to surface

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
