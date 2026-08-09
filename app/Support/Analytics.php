<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Central analytics constants and helpers.
 *
 * Every dashboard query must anchor to startDate(). Do not hardcode dates
 * in widgets, pages, or raw SQL — use this class instead.
 */
final class Analytics
{
    public static function startDate(): string
    {
        return (string) config('analytics.start_date', '2026-06-01');
    }

    public static function baseline(): Carbon
    {
        return Carbon::parse(self::startDate())->startOfDay();
    }

    public static function baselineDateString(): string
    {
        return self::baseline()->toDateString();
    }

    public static function baselineDateTimeString(): string
    {
        return self::baseline()->toDateTimeString();
    }

    public static function asOf(): Carbon
    {
        return Carbon::now()->endOfDay();
    }

    public static function asOfDateTimeString(): string
    {
        return self::asOf()->toDateTimeString();
    }

    public static function baselineLabel(): string
    {
        return self::baseline()->format('F j, Y');
    }

    public static function abandonedInactivityDays(): int
    {
        return (int) config('analytics.abandoned_inactivity_days', 14);
    }

    public static function abandonedCutoff(): Carbon
    {
        return Carbon::now()->subDays(self::abandonedInactivityDays());
    }

    public static function abandonedLabel(): string
    {
        return self::abandonedInactivityDays().'+ days';
    }
}
