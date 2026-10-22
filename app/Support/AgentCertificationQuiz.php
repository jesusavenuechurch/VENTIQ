<?php

namespace App\Support;

/**
 * The sales agent certification: four short modules, a question or more
 * after each, and four statements to confirm. Answers are checked here,
 * never sent to the page. 75% passes.
 *
 * Modules 1 and 3 and questions 1, 5 to 8 describe per-event packages and
 * a commission on an organization's first paid package, from before
 * packages were dropped (see docs/inertia-migration.md, open questions).
 */
class AgentCertificationQuiz
{
    public const PASS_PERCENT = 75;

    /** @return list<array{title: string, body: list<string>, questions: list<array{key: string, text: string, options: array<string, string>}>}> */
    public static function modules(): array
    {
        return [
            [
                'title' => 'What is VENTIQ?',
                'body'  => [
                    'VENTIQ is an Event Access & Participation Intelligence platform. It helps organizations manage event registrations, control event access, track attendance and capture structured event data.',
                    'VENTIQ is not limited to entertainment events. It is built for workshops, corporate trainings, seminars, private events and public events.',
                    'Organizations purchase packages per event, not monthly subscriptions.',
                ],
                'questions' => [
                    ['key' => 'q1', 'text' => 'VENTIQ operates on:', 'options' => ['A' => 'Monthly subscriptions', 'B' => 'Per-event packages']],
                    ['key' => 'q2', 'text' => 'VENTIQ is designed for:', 'options' => ['A' => 'Only concerts', 'B' => 'Corporate, private & entertainment events']],
                    ['key' => 'q3', 'text' => 'What is VENTIQ primarily focused on?', 'options' => ['A' => 'Selling tickets only', 'B' => 'Event access, registration & participation tracking']],
                ],
            ],
            [
                'title' => 'Who you should target',
                'body'  => [
                    'VENTIQ works best with organizations that host structured events, need attendance tracking, want better access control, and require event data reporting.',
                    'Primary targets: corporate HR departments, NGOs, training institutions, universities, conference organizers. Secondary: entertainment promoters.',
                    'Focus on organizations that host recurring or structured events.',
                ],
                'questions' => [
                    ['key' => 'q4', 'text' => 'Which is a strong primary target?', 'options' => ['A' => 'Corporate HR department', 'B' => 'Random social gatherings']],
                    ['key' => 'q5', 'text' => 'Why is targeting structured organizations important?', 'options' => ['A' => 'They are more likely to convert to paid packages', 'B' => "It doesn't matter who you target"]],
                ],
            ],
            [
                'title' => 'Commission & bonuses',
                'body'  => [
                    "You earn 20% commission once per organization's first paid package.",
                    'Commission applies only to new organizations registered through your unique referral link. Payments are processed at the end of each month.',
                    'Milestone bonuses: 5 paying organizations, M300; 10 in total, a further M320. Commission is not recurring.',
                ],
                'questions' => [
                    ['key' => 'q6', 'text' => 'When do you earn commission?', 'options' => ['A' => 'Every time the organization hosts an event', 'B' => 'Once on their first paid package']],
                    ['key' => 'q7', 'text' => 'Commission is paid:', 'options' => ['A' => 'Immediately after registration', 'B' => 'After payment verification and month-end processing']],
                    ['key' => 'q8', 'text' => 'Does commission recur monthly?', 'options' => ['A' => 'Yes', 'B' => 'No']],
                ],
            ],
            [
                'title' => 'The 30-second pitch',
                'body'  => [
                    '"We help organizations manage event access, registrations, and attendance tracking without manual lists or traditional ticket systems."',
                    'Keep it simple. Do not overpromise. Do not modify pricing. Do not misrepresent commission. Your role is to introduce organizations to VENTIQ.',
                ],
                'questions' => [],
            ],
        ];
    }

    /** @return array<string, string> */
    public static function acknowledgments(): array
    {
        return [
            'ack1' => "I understand commission is paid once per organization's first paid package.",
            'ack2' => 'I understand commission applies only to organizations registered through my referral link.',
            'ack3' => 'I understand VENTIQ reserves the right to audit suspicious referrals.',
            'ack4' => 'I agree to represent VENTIQ professionally.',
        ];
    }

    /** @return array<string, string> */
    private static function answers(): array
    {
        return ['q1' => 'B', 'q2' => 'B', 'q3' => 'B', 'q4' => 'A', 'q5' => 'A', 'q6' => 'B', 'q7' => 'B', 'q8' => 'B'];
    }

    /** @return array{score: int, total: int, percent: int, passed: bool} */
    public static function grade(array $given): array
    {
        $answers = self::answers();
        $score = count(array_filter($answers, fn ($right, $key) => ($given[$key] ?? null) === $right, ARRAY_FILTER_USE_BOTH));
        $percent = (int) floor($score / count($answers) * 100);

        return ['score' => $score, 'total' => count($answers), 'percent' => $percent, 'passed' => $percent >= self::PASS_PERCENT];
    }
}
