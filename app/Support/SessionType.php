<?php

namespace App\Support;

class SessionType
{
    protected static array $types = [
        'presentation' => [
            'label' => 'Presentation', 'segment' => 'Presentation',
            'roles' => [
                'presenter' => ['label' => 'Presenter', 'presenting' => true],
                'judge'     => ['label' => 'Judge',     'presenting' => false],
            ],
            'sections' => [
                'summary'    => ['label' => 'Summary',    'prompt' => 'A 2-3 sentence summary of what this segment covered.'],
                'key_points' => ['label' => 'Key Points', 'prompt' => 'What stood out as substantive content. One per line starting with "•".'],
                'follow_ups' => ['label' => 'Follow-ups', 'prompt' => 'Gaps or things worth following up on. One per line starting with "•".'],
                'questions'  => ['label' => 'Questions',  'prompt' => 'Questions raised. One per line starting with "•".'],
            ],
        ],
        'meeting' => [
            'label' => 'Meeting', 'segment' => 'Agenda Item',
            'roles' => [
                'chairperson' => ['label' => 'Chairperson', 'presenting' => true],
                'secretary'   => ['label' => 'Secretary',   'presenting' => false],
            ],
            'sections' => [
                'summary'      => ['label' => 'Summary',      'prompt' => 'A 2-3 sentence summary of what this agenda item covered.'],
                'decisions'    => ['label' => 'Decisions',    'prompt' => 'Decisions made. One per line starting with "•".'],
                'action_items' => ['label' => 'Action Items', 'prompt' => 'Tasks agreed, with an owner if mentioned. One per line starting with "•".'],
                'follow_ups'   => ['label' => 'Follow-ups',   'prompt' => 'Anything left unresolved. One per line starting with "•".'],
            ],
        ],
        'training' => [
            'label' => 'Training', 'segment' => 'Module',
            'roles' => ['facilitator' => ['label' => 'Facilitator', 'presenting' => true]],
            'sections' => [
                'summary'             => ['label' => 'Summary',             'prompt' => 'A 2-3 sentence summary of what this module covered.'],
                'key_learning_points' => ['label' => 'Key Learning Points', 'prompt' => 'Core takeaways for participants. One per line starting with "•".'],
                'follow_ups'          => ['label' => 'Follow-ups',          'prompt' => 'Gaps or things worth revisiting. One per line starting with "•".'],
            ],
        ],
        'workshop' => [
            'label' => 'Workshop', 'segment' => 'Module',
            'roles' => [
                'facilitator' => ['label' => 'Facilitator', 'presenting' => true],
                'assistant'   => ['label' => 'Assistant',   'presenting' => false],
            ],
            'sections' => [
                'summary'    => ['label' => 'Summary',    'prompt' => 'A 2-3 sentence summary of what this module covered.'],
                'key_points' => ['label' => 'Key Points', 'prompt' => 'Substantive content covered. One per line starting with "•".'],
                'follow_ups' => ['label' => 'Follow-ups', 'prompt' => 'Gaps or things worth revisiting. One per line starting with "•".'],
            ],
        ],
        'brainstorming' => [
            'label' => 'Brainstorming', 'segment' => 'Topic',
            'roles' => [
                'facilitator' => ['label' => 'Facilitator', 'presenting' => true],
                'participant' => ['label' => 'Participant', 'presenting' => false],
            ],
            'sections' => [
                'summary'    => ['label' => 'Summary',    'prompt' => 'A 2-3 sentence summary of what this topic covered.'],
                'ideas'      => ['label' => 'Ideas',      'prompt' => 'Ideas raised. One per line starting with "•".'],
                'follow_ups' => ['label' => 'Follow-ups', 'prompt' => 'Ideas worth exploring further. One per line starting with "•".'],
            ],
        ],
        'interview' => [
            'label' => 'Interview', 'segment' => 'Topic',
            'roles' => [
                'interviewer' => ['label' => 'Interviewer', 'presenting' => true],
                'candidate'   => ['label' => 'Candidate',   'presenting' => false],
                'panelist'    => ['label' => 'Panel Member','presenting' => false],
            ],
            'sections' => [
                'summary'    => ['label' => 'Summary',    'prompt' => 'A 2-3 sentence summary of this interview segment.'],
                'key_points' => ['label' => 'Key Points', 'prompt' => 'Notable responses or observations. One per line starting with "•".'],
                'follow_ups' => ['label' => 'Follow-ups', 'prompt' => 'Points needing clarification or further discussion. One per line starting with "•".'],
            ],
        ],
        'church' => [
            'label' => 'Church Service', 'segment' => 'Service Section',
            'roles' => [
                'speaker'        => ['label' => 'Speaker',        'presenting' => true],
                'mc'             => ['label' => 'MC',              'presenting' => false],
                'worship_leader' => ['label' => 'Worship Leader',  'presenting' => false],
            ],
            'sections' => [
                'summary'    => ['label' => 'Summary',    'prompt' => 'A 2-3 sentence summary of this section.'],
                'key_points' => ['label' => 'Key Points', 'prompt' => 'Main points shared. One per line starting with "•".'],
            ],
        ],
        'board' => [
            'label' => 'Board Meeting', 'segment' => 'Agenda Item',
            'roles' => [
                'chairperson' => ['label' => 'Chairperson',  'presenting' => true],
                'secretary'   => ['label' => 'Secretary',    'presenting' => false],
                'board_member'=> ['label' => 'Board Member', 'presenting' => false],
            ],
            'sections' => [
                'summary'      => ['label' => 'Summary',      'prompt' => 'A 2-3 sentence summary of this agenda item.'],
                'decisions'    => ['label' => 'Decisions',    'prompt' => 'Decisions made. One per line starting with "•".'],
                'action_items' => ['label' => 'Action Items', 'prompt' => 'Tasks agreed, with owner if mentioned. One per line starting with "•".'],
                'follow_ups'   => ['label' => 'Follow-ups',   'prompt' => 'Unresolved items. One per line starting with "•".'],
            ],
        ],
        'committee' => [
            'label' => 'Committee', 'segment' => 'Agenda Item',
            'roles' => [
                'chairperson' => ['label' => 'Chairperson', 'presenting' => true],
                'secretary'   => ['label' => 'Secretary',   'presenting' => false],
            ],
            'sections' => [
                'summary'      => ['label' => 'Summary',      'prompt' => 'A 2-3 sentence summary of this agenda item.'],
                'decisions'    => ['label' => 'Decisions',    'prompt' => 'Decisions made. One per line starting with "•".'],
                'action_items' => ['label' => 'Action Items', 'prompt' => 'Tasks agreed. One per line starting with "•".'],
                'follow_ups'   => ['label' => 'Follow-ups',   'prompt' => 'Unresolved items. One per line starting with "•".'],
            ],
        ],
        'custom' => [
            'label' => 'Custom', 'segment' => 'Segment',
            'roles' => ['presenter' => ['label' => 'Presenter', 'presenting' => true]],
            'sections' => [
                'summary'    => ['label' => 'Summary',    'prompt' => 'A 2-3 sentence summary of what this segment covered.'],
                'key_points' => ['label' => 'Key Points', 'prompt' => 'Substantive content. One per line starting with "•".'],
                'follow_ups' => ['label' => 'Follow-ups', 'prompt' => 'Things worth following up on. One per line starting with "•".'],
            ],
        ],
    ];

    public static function label(?string $type): string
    {
        return static::$types[$type]['label'] ?? 'Session';
    }

    public static function segmentLabel(?string $type): string
    {
        return static::$types[$type]['segment'] ?? 'Segment';
    }

    public static function roles(?string $type): array
    {
        return static::$types[$type]['roles'] ?? ['presenter' => ['label' => 'Presenter', 'presenting' => true]];
    }

    public static function roleIsPresenting(?string $type, ?string $role): bool
    {
        return static::roles($type)[$role]['presenting'] ?? true;
    }

    public static function sections(?string $type): array
    {
        return static::$types[$type]['sections'] ?? [
            'summary'    => ['label' => 'Summary',    'prompt' => 'A 2-3 sentence summary of what this segment covered.'],
            'key_points' => ['label' => 'Key Points', 'prompt' => 'Substantive content. One per line starting with "•".'],
            'follow_ups' => ['label' => 'Follow-ups', 'prompt' => 'Things worth following up on. One per line starting with "•".'],
        ];
    }

    public static function options(): array
    {
        return collect(static::$types)->map(fn ($t) => $t['label'])->toArray();
    }

    public static function keys(): array
    {
        return array_keys(static::$types);
    }
}