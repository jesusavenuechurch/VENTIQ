<?php

namespace App\Services\Memory;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The time a question is about, read from its words ("this month", "last
 * week", "in March", "since June", "the last 3 months") without asking
 * the model: one call fewer to the Mac mini, and no guessing.
 */
class QuestionDates
{
    private const MONTHS = ['january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    /** @return array{0: Carbon, 1: Carbon}|null from and to, both inclusive */
    public static function parse(string $question, ?CarbonInterface $today = null): ?array
    {
        $today = Carbon::instance($today ?? now())->startOfDay();
        $q = mb_strtolower($question);

        return match (true) {
            str_contains($q, 'today')                     => [$today->copy(), $today->copy()],
            str_contains($q, 'yesterday')                 => [$today->copy()->subDay(), $today->copy()->subDay()],
            str_contains($q, 'this week')                 => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()->startOfDay()],
            str_contains($q, 'last week')                 => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()->startOfDay()],
            str_contains($q, 'this month')                => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->startOfDay()],
            str_contains($q, 'last month')                => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            str_contains($q, 'this year')                 => [$today->copy()->startOfYear(), $today->copy()->endOfYear()->startOfDay()],
            str_contains($q, 'last year')                 => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()->startOfDay()],
            (bool) preg_match('/(?:last|past) (\d{1,3}) (day|week|month)s?/', $q, $m) => [self::back($today, (int) $m[1], $m[2]), $today->copy()],
            (bool) preg_match('/since (' . self::monthPattern() . ')\b(?: (\d{4}))?/', $q, $m) => [self::monthStart($m[1], $m[2] ?? null, $today), $today->copy()],
            (bool) preg_match('/\b(?:in|during|for) (' . self::monthPattern() . ')\b(?: (\d{4}))?/', $q, $m) => (function () use ($m, $today) {
                $start = self::monthStart($m[1], $m[2] ?? null, $today);

                return [$start, $start->copy()->endOfMonth()->startOfDay()];
            })(),
            default => null,
        };
    }

    private static function back(Carbon $today, int $n, string $unit): Carbon
    {
        return match ($unit) {
            'day'   => $today->copy()->subDays($n),
            'week'  => $today->copy()->subWeeks($n),
            default => $today->copy()->subMonthsNoOverflow($n),
        };
    }

    /** The month named, in the year given or else the latest one not after today. */
    private static function monthStart(string $name, ?string $year, Carbon $today): Carbon
    {
        $month = self::MONTHS[$name];
        $start = Carbon::create($year ? (int) $year : $today->year, $month, 1)->startOfDay();

        return !$year && $start->gt($today) ? $start->subYear() : $start;
    }

    private static function monthPattern(): string
    {
        return implode('|', array_keys(self::MONTHS));
    }
}
