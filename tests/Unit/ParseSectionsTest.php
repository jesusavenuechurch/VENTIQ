<?php

use App\Contracts\AI\AIProvider;
use App\Services\AI\AIService;

beforeEach(function () {
    $this->ai = new AIService(Mockery::mock(AIProvider::class));
});

it('keeps every line of a section, not just the first', function () {
    expect($this->ai->parseSections("SUMMARY:\nIt was covered.\nMembers agreed.\n\nDECISIONS:\n• Hire a driver.\n• Buy the Toyota.\n\nFOLLOW-UPS:\nNone identified."))
        ->toBe(['summary' => "It was covered.\nMembers agreed.", 'decisions' => "• Hire a driver.\n• Buy the Toyota.", 'follow_ups' => 'None identified.']);
});

it('reads headings as the prompts write them, and as models write them back', function () {
    $answer = "Here are the sections.\n\n**SUMMARY:** The module covered registers.\n\nKEY_POINTS:\n• Date every line.\n\n## Action Items:\n• Thabo to print registers.\n\nFOLLOW UPS:\nNone identified.";

    expect($this->ai->parseSections($answer, ['summary', 'key_points', 'action_items', 'follow_ups']))->toBe([
        'summary'      => 'The module covered registers.',
        'key_points'   => '• Date every line.',
        'action_items' => '• Thabo to print registers.',
        'follow_ups'   => 'None identified.',
    ]);
});

it('given the keys, leaves a line like "ORS: 400 sachets" in its section', function () {
    expect($this->ai->parseSections("KEY_POINTS:\n• ORS: 400 sachets\nZINC: 10 days", ['summary', 'key_points']))
        ->toBe(['key_points' => "• ORS: 400 sachets\nZINC: 10 days"]);
});
