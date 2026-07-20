<?php

namespace App\Support;

// Keeps "Segment" as a developer term only. The UI always shows
// the label appropriate to the session type — Presentation for a
// classroom or conference, Agenda Item for a meeting, etc.
class SessionType
{
    protected static array $types = [
        'meeting'       => ['label' => 'Meeting',        'segment' => 'Agenda Item'],
        'committee'     => ['label' => 'Committee',      'segment' => 'Agenda Item'],
        'board'         => ['label' => 'Board Meeting',  'segment' => 'Agenda Item'],
        'classroom'     => ['label' => 'Class Session',  'segment' => 'Presentation'],
        'conference'    => ['label' => 'Conference',     'segment' => 'Presentation'],
        'church'        => ['label' => 'Service',        'segment' => 'Service Section'],
        'workshop'      => ['label' => 'Workshop',       'segment' => 'Module'],
        'brainstorming' => ['label' => 'Brainstorming',  'segment' => 'Topic'],
    ];

    public static function label(?string $type): string
    {
        return static::$types[$type]['label'] ?? 'Session';
    }

    public static function segmentLabel(?string $type): string
    {
        return static::$types[$type]['segment'] ?? 'Segment';
    }

    public static function options(): array
    {
        return collect(static::$types)->map(fn ($t) => $t['label'])->toArray();
    }
}