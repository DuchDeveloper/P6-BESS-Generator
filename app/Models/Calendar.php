<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'workdays_per_week',
        'working_days',
        'holidays',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'workdays_per_week' => 'integer',
            'working_days' => 'array',
            'holidays' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /** ISO weekday numbers (1 = Monday … 7 = Sunday) to names. */
    public const WEEKDAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    protected static function booted(): void
    {
        static::saving(function (Calendar $calendar): void {
            if (! empty($calendar->working_days)) {
                $calendar->working_days = $calendar->workingDays();
                $calendar->workdays_per_week = count($calendar->working_days);
            }
        });

        // Only one default calendar.
        static::saved(function (Calendar $calendar): void {
            if ($calendar->is_default && ($calendar->wasChanged('is_default') || $calendar->wasRecentlyCreated)) {
                static::whereKeyNot($calendar->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    // ── Relationships ────────────────────────────────────────

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    // ── Working time ─────────────────────────────────────────

    /**
     * Working ISO weekdays, sorted. Falls back to the first
     * workdays_per_week days from Monday when none are stored.
     *
     * @return list<int>
     */
    public function workingDays(): array
    {
        $days = array_values(array_unique(array_map('intval', $this->working_days ?? [])));
        $days = array_values(array_filter($days, fn (int $d) => $d >= 1 && $d <= 7));
        sort($days);

        return $days !== [] ? $days : range(1, max(1, min(7, $this->workdays_per_week ?? 5)));
    }

    /**
     * Non-working dates, sorted and de-duplicated. Holidays are stored as
     * {date, name} entries; plain date strings from older data are accepted.
     *
     * @return list<CarbonImmutable>
     */
    public function holidayDates(): array
    {
        $dates = collect($this->holidays ?? [])
            ->map(fn ($holiday) => is_array($holiday) ? ($holiday['date'] ?? null) : $holiday)
            ->filter(fn ($date) => is_string($date) && $date !== '')
            ->map(fn (string $date) => CarbonImmutable::parse($date)->startOfDay())
            ->unique(fn (CarbonImmutable $date) => $date->toDateString())
            ->sortBy(fn (CarbonImmutable $date) => $date->toDateString())
            ->values()
            ->all();

        return $dates;
    }

    /** e.g. "Mon–Fri" or "Mon, Tue, Thu". */
    public function workingDaysLabel(): string
    {
        $days = $this->workingDays();
        $short = array_map(fn (int $d) => substr(self::WEEKDAYS[$d], 0, 3), $days);

        $consecutive = $days === range($days[0], $days[count($days) - 1]);

        return $consecutive && count($days) > 2
            ? $short[0] . '–' . $short[count($short) - 1]
            : implode(', ', $short);
    }
}