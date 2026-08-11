<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\PlayerTokenUsage;
use Illuminate\Database\Eloquent\Builder;

final class PlayerTokenUsageSummary
{
    /** @return array<string, int|string> */
    public function forGame(string $gameId): array
    {
        return $this->summarize(
            PlayerTokenUsage::query()->where('game_id', $gameId),
        );
    }

    /** @return array<string, int|string> */
    public function forSession(
        string $gameId,
        int $storyCycleNumber,
        int $sessionNumber,
    ): array {
        return $this->summarize(
            PlayerTokenUsage::query()->forSession(
                $gameId,
                $storyCycleNumber,
                $sessionNumber,
            ),
        );
    }

    /** @return array<string, int|string> */
    public function forMoment(int $playableMomentId): array
    {
        return $this->summarize(
            PlayerTokenUsage::query()->where('playable_moment_id', $playableMomentId),
        );
    }

    /** @return array<string, int|string> */
    public function forEvent(int $eventId): array
    {
        return $this->summarize(
            PlayerTokenUsage::query()->forEvent($eventId),
        );
    }

    /** @return array<string, int|string> */
    public function forChapter(int $chapterId): array
    {
        return $this->summarize(
            PlayerTokenUsage::query()->forChapter($chapterId),
        );
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array{
     *     requests:int,
     *     input_tokens:int,
     *     output_tokens:int,
     *     cache_write_input_tokens:int,
     *     cache_read_input_tokens:int,
     *     cached_input_tokens:int,
     *     total_input_tokens:int,
     *     total_tokens:int,
     *     reasoning_tokens:int,
     *     input_cost_usd:string,
     *     output_cost_usd:string,
     *     cache_write_cost_usd:string,
     *     cache_read_cost_usd:string,
     *     total_cost_usd:string,
     *     unreported_requests:int,
     * }
     */
    private function summarize(Builder $query): array
    {
        $totals = (clone $query)
            ->toBase()
            ->selectRaw(implode(', ', [
                'COUNT(*) AS requests',
                'COALESCE(SUM(input_tokens), 0) AS input_tokens',
                'COALESCE(SUM(output_tokens), 0) AS output_tokens',
                'COALESCE(SUM(cache_write_input_tokens), 0) AS cache_write_input_tokens',
                'COALESCE(SUM(cache_read_input_tokens), 0) AS cache_read_input_tokens',
                'COALESCE(SUM(cached_input_tokens), 0) AS cached_input_tokens',
                'COALESCE(SUM(total_input_tokens), 0) AS total_input_tokens',
                'COALESCE(SUM(total_tokens), 0) AS total_tokens',
                'COALESCE(SUM(reasoning_tokens), 0) AS reasoning_tokens',
                'COALESCE(SUM(input_cost_usd), 0) AS input_cost_usd',
                'COALESCE(SUM(output_cost_usd), 0) AS output_cost_usd',
                'COALESCE(SUM(cache_write_cost_usd), 0) AS cache_write_cost_usd',
                'COALESCE(SUM(cache_read_cost_usd), 0) AS cache_read_cost_usd',
                'COALESCE(SUM(total_cost_usd), 0) AS total_cost_usd',
                'COALESCE(SUM(CASE WHEN usage_reported = false THEN 1 ELSE 0 END), 0) AS unreported_requests',
            ]))
            ->first();

        return [
            'requests' => $this->integer($totals?->requests),
            'input_tokens' => $this->integer($totals?->input_tokens),
            'output_tokens' => $this->integer($totals?->output_tokens),
            'cache_write_input_tokens' => $this->integer($totals?->cache_write_input_tokens),
            'cache_read_input_tokens' => $this->integer($totals?->cache_read_input_tokens),
            'cached_input_tokens' => $this->integer($totals?->cached_input_tokens),
            'total_input_tokens' => $this->integer($totals?->total_input_tokens),
            'total_tokens' => $this->integer($totals?->total_tokens),
            'reasoning_tokens' => $this->integer($totals?->reasoning_tokens),
            'input_cost_usd' => $this->decimal($totals?->input_cost_usd),
            'output_cost_usd' => $this->decimal($totals?->output_cost_usd),
            'cache_write_cost_usd' => $this->decimal($totals?->cache_write_cost_usd),
            'cache_read_cost_usd' => $this->decimal($totals?->cache_read_cost_usd),
            'total_cost_usd' => $this->decimal($totals?->total_cost_usd),
            'unreported_requests' => $this->integer($totals?->unreported_requests),
        ];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function decimal(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '0';
    }
}
