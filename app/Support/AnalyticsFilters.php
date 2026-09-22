<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Story;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Normalizes and applies the shared manager analytics filters.
 */
final class AnalyticsFilters
{
    public const string RANGE_ALL = 'all';

    public const string RANGE_CUSTOM = 'custom';

    /**
     * @return array<string, string>
     */
    public static function dateRangeOptions(): array
    {
        return [
            self::RANGE_ALL => 'All time',
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            self::RANGE_CUSTOM => 'Custom range',
        ];
    }

    /**
     * @return array{range: string, from: string|null, to: string|null, user_id: int|null, story_id: int|null}
     */
    public static function defaults(): array
    {
        return [
            'range' => self::RANGE_ALL,
            'from' => null,
            'to' => null,
            'user_id' => null,
            'story_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{range: string, from: string|null, to: string|null, user_id: int|null, story_id: int|null}
     */
    public static function normalize(array $filters): array
    {
        $defaults = self::defaults();
        $range = (string) ($filters['range'] ?? $defaults['range']);

        if (! array_key_exists($range, self::dateRangeOptions())) {
            $range = self::RANGE_ALL;
        }

        return [
            'range' => $range,
            'from' => $range === self::RANGE_CUSTOM ? self::normalizeDate($filters['from'] ?? null) : null,
            'to' => $range === self::RANGE_CUSTOM ? self::normalizeDate($filters['to'] ?? null) : null,
            'user_id' => self::normalizeId($filters['user_id'] ?? null),
            'story_id' => self::normalizeId($filters['story_id'] ?? null),
        ];
    }

    /**
     * Resolve the selected range while enforcing the configured baseline and current as-of date.
     *
     * @param  array<string, mixed>  $filters
     * @return array{Carbon, Carbon}
     */
    public static function dateRange(array $filters): array
    {
        $filters = self::normalize($filters);
        $baseline = Analytics::baseline();
        $asOf = Analytics::asOf();

        if ($filters['range'] === self::RANGE_CUSTOM) {
            $from = $filters['from'] !== null
                ? Carbon::parse($filters['from'])->startOfDay()
                : $baseline->copy();
            $to = $filters['to'] !== null
                ? Carbon::parse($filters['to'])->endOfDay()
                : $asOf->copy();
        } else {
            $days = match ($filters['range']) {
                '7' => 7,
                '30' => 30,
                '90' => 90,
                default => null,
            };

            $from = $days === null
                ? $baseline->copy()
                : Carbon::now()->subDays($days - 1)->startOfDay();
            $to = $asOf->copy();
        }

        if ($from->lt($baseline)) {
            $from = $baseline->copy();
        }

        if ($to->gt($asOf)) {
            $to = $asOf->copy();
        }

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function dateRangeLabel(array $filters): string
    {
        $filters = self::normalize($filters);

        if ($filters['range'] === self::RANGE_ALL) {
            return 'All time since '.Analytics::baselineLabel();
        }

        if ($filters['range'] !== self::RANGE_CUSTOM) {
            return self::dateRangeOptions()[$filters['range']];
        }

        [$from, $to] = self::dateRange($filters);

        return $from->toFormattedDateString().' – '.$to->toFormattedDateString();
    }

    /**
     * Add the shared date, user, and story filters to a games query.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function applyToGameQuery(Builder $query, array $filters, string $alias = 'games'): Builder
    {
        $filters = self::normalize($filters);
        [$from, $to] = self::dateRange($filters);

        $query
            ->where("{$alias}.is_preview", false)
            ->whereBetween("{$alias}.created_at", [$from, $to]);

        if ($filters['user_id'] !== null) {
            $query->where("{$alias}.user_id", $filters['user_id']);
        }

        if ($filters['story_id'] !== null) {
            $query->where("{$alias}.story_id", $filters['story_id']);
        }

        return $query;
    }

    /**
     * Add the shared date, user, and story filters to page-view queries.
     *
     * Story attribution is limited to that story's public page paths because
     * page views do not store a story_id foreign key.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function applyToPageViewQuery(Builder $query, array $filters): Builder
    {
        $filters = self::normalize($filters);
        [$from, $to] = self::dateRange($filters);

        $query->whereBetween('view_date', [$from->toDateString(), $to->toDateString()]);

        if ($filters['user_id'] !== null) {
            $query->where('user_id', $filters['user_id']);
        }

        if ($filters['story_id'] !== null) {
            $slug = Story::query()->whereKey($filters['story_id'])->value('slug');

            if (! is_string($slug) || $slug === '') {
                return $query->where('id', 0);
            }

            $paths = [
                "stories/{$slug}",
                "/stories/{$slug}",
            ];

            $query->where(function (Builder $pathQuery) use ($paths): void {
                foreach ($paths as $path) {
                    $pathQuery
                        ->orWhere('path', $path)
                        ->orWhere('path', 'like', $path.'/%');
                }
            });
        }

        return $query;
    }

    private static function normalizeId(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
