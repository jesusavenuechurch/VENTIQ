<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A session that repeats: "Weekly ops meeting, Mondays 9:00". Its
 * meetings are made ahead of time (App\Services\Sessions\SeriesScheduler),
 * each a normal session linked to the one before it.
 */
class SessionSeries extends Model
{
    protected $table = 'session_series';

    public const FREQUENCIES = ['weekly', 'fortnightly', 'monthly_date', 'monthly_weekday'];

    protected $fillable = [
        'organization_id', 'created_by', 'title', 'type', 'custom_type_label', 'start_time',
        'expected_duration', 'track_participants', 'expected_participants', 'people', 'meta',
        'frequency', 'starts_on', 'ends_on', 'scheduled_until', 'ended_at',
    ];

    protected $casts = [
        'people'             => 'array',
        'meta'               => 'array',
        'track_participants' => 'boolean',
        'starts_on'          => 'date',
        'ends_on'            => 'date',
        'scheduled_until'    => 'date',
        'ended_at'           => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'series_id')->orderBy('date')->orderBy('start_time');
    }

    public function isEnded(): bool
    {
        return $this->ended_at !== null;
    }

    /** The date of the series' nth meeting (0 is the first), by its rule. */
    public function occurrence(int $n): Carbon
    {
        $start = $this->starts_on->copy()->startOfDay();

        return match ($this->frequency) {
            'weekly'          => $start->copy()->addWeeks($n),
            'fortnightly'     => $start->copy()->addWeeks($n * 2),
            // The same day of the month; the last day in shorter months.
            'monthly_date'    => (function () use ($start, $n) {
                $month = $start->copy()->startOfMonth()->addMonthsNoOverflow($n);

                return $month->day(min($start->day, $month->daysInMonth));
            })(),
            // The same weekday and week of the month, e.g. the 2nd Tuesday;
            // a 5th one becomes the last.
            'monthly_weekday' => (function () use ($start, $n) {
                $month = $start->copy()->startOfMonth()->addMonthsNoOverflow($n);
                $nth = (int) ceil($start->day / 7);

                return $nth >= 5
                    ? $month->copy()->lastOfMonth($start->dayOfWeek)
                    : $month->copy()->nthOfMonth($nth, $start->dayOfWeek);
            })(),
        };
    }

    /**
     * The dates of the meetings after $after (or from the first one), up to
     * $count of them, not past the end date.
     *
     * @return Carbon[]
     */
    public function datesAfter(?CarbonInterface $after, int $count): array
    {
        $dates = [];
        for ($n = 0; count($dates) < $count && $n < 1000; $n++) {
            $date = $this->occurrence($n);
            if ($this->ends_on && $date->gt($this->ends_on)) {
                break;
            }
            if (!$after || $date->gt($after)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /** "Every Monday", "Every 2 weeks on Monday", "Monthly on the 15th", "Monthly on the 2nd Tuesday". */
    public function describe(): string
    {
        $start = $this->starts_on;
        $day = $start->englishDayOfWeek;
        $nth = (int) ceil($start->day / 7);
        $ordinal = fn (int $n) => $n . (in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));

        $rule = match ($this->frequency) {
            'weekly'          => "Every {$day}",
            'fortnightly'     => "Every 2 weeks on {$day}",
            'monthly_date'    => 'Monthly on the ' . $ordinal($start->day),
            'monthly_weekday' => 'Monthly on the ' . ($nth >= 5 ? 'last' : $ordinal($nth)) . " {$day}",
        };

        return $this->start_time ? $rule . ' at ' . substr($this->start_time, 0, 5) : $rule;
    }
}
