<?php

namespace App\Services\AI\Prompts;

abstract class PromptBuilder
{
    protected array $variables = [];

    public function with(array $variables): static
    {
        $this->variables = array_merge($this->variables, $variables);
        return $this;
    }

    abstract public function build(): string;

    protected function inject(string $template): string
    {
        foreach ($this->variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    protected function systemContext(): string
    {
        return "You are Ventiq Assist, an intelligent event management assistant for Ventiq, " .
               "a professional event management platform in Lesotho, Southern Africa. " .
               "You help event organisers create compelling, professional content. " .
               "Always respond in clear, professional English unless asked otherwise. " .
               "Keep responses focused and practical. Do not add unnecessary disclaimers.";
    }
}