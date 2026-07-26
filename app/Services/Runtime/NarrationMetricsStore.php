<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use Illuminate\Support\Facades\Cache;

/**
 * Durable-enough turn latency samples for Cloud exports.
 * File logs are invisible on Laravel Cloud Commands; this lives in the
 * shared cache store (database/redis) so `php artisan narration:export`
 * can print what the web instances recorded.
 */
final class NarrationMetricsStore
{
    public const string CACHE_KEY = 'narration.latency_samples';

    private const int MAX_SAMPLES = 100;

    /**
     * @param  array<string, mixed>  $sample
     */
    public static function record(array $sample): void
    {
        $sample['recorded_at'] = now()->toIso8601String();

        $samples = Cache::get(self::CACHE_KEY, []);
        if (! is_array($samples)) {
            $samples = [];
        }

        array_unshift($samples, $sample);
        Cache::forever(self::CACHE_KEY, array_slice($samples, 0, self::MAX_SAMPLES));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $samples = Cache::get(self::CACHE_KEY, []);

        return is_array($samples) ? array_values($samples) : [];
    }

    public static function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
