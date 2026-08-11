<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\PlayerVoiceUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class PlayerVoiceUsageInsights
{
    /**
     * @param  Builder<PlayerVoiceUsage>  $query
     * @return array{
     *     requests:int,
     *     input_characters:int,
     *     billable_units:int,
     *     audio_bytes:int,
     *     total_cost_usd:string,
     *     unpriced_requests:int,
     *     average_billable_units:int,
     *     average_cost_usd:float,
     *     average_latency_ms:int,
     * }
     */
    public function overview(Builder $query): array
    {
        $totals = DB::query()
            ->fromSub($this->filteredRows($query, [
                'input_characters',
                'billable_units',
                'audio_bytes',
                'latency_ms',
                'estimated_cost_usd',
                'pricing_available',
            ]), 'usage')
            ->selectRaw(implode(', ', [
                'COUNT(*) AS requests',
                'COALESCE(SUM(input_characters), 0) AS input_characters',
                'COALESCE(SUM(billable_units), 0) AS billable_units',
                'COALESCE(SUM(audio_bytes), 0) AS audio_bytes',
                'COALESCE(SUM(latency_ms), 0) AS latency_ms',
                'COALESCE(SUM(estimated_cost_usd), 0) AS total_cost_usd',
                'COALESCE(SUM(CASE WHEN pricing_available = false THEN 1 ELSE 0 END), 0) AS unpriced_requests',
            ]))
            ->first();

        $requests = $this->integer($totals?->requests);

        return [
            'requests' => $requests,
            'input_characters' => $this->integer($totals?->input_characters),
            'billable_units' => $this->integer($totals?->billable_units),
            'audio_bytes' => $this->integer($totals?->audio_bytes),
            'total_cost_usd' => $this->decimal($totals?->total_cost_usd),
            'unpriced_requests' => $this->integer($totals?->unpriced_requests),
            'average_billable_units' => $requests > 0
                ? (int) round($this->integer($totals?->billable_units) / $requests)
                : 0,
            'average_cost_usd' => $requests > 0
                ? (float) $this->decimal($totals?->total_cost_usd) / $requests
                : 0.0,
            'average_latency_ms' => $requests > 0
                ? (int) round($this->integer($totals?->latency_ms) / $requests)
                : 0,
        ];
    }

    /**
     * @param  Builder<PlayerVoiceUsage>  $query
     * @return array{
     *     labels:array<int, string>,
     *     requests:array<int, int>,
     *     billable_units:array<int, int>,
     *     estimated_cost_usd:array<int, float>,
     * }
     */
    public function dailyUsage(Builder $query, int $limit = 30): array
    {
        $dateExpression = 'DATE(usage.occurred_at)';
        $rows = DB::query()
            ->fromSub($this->filteredRows($query, [
                'occurred_at',
                'billable_units',
                'estimated_cost_usd',
            ]), 'usage')
            ->selectRaw(implode(', ', [
                "{$dateExpression} AS usage_date",
                'COUNT(*) AS requests',
                'COALESCE(SUM(billable_units), 0) AS billable_units',
                'COALESCE(SUM(estimated_cost_usd), 0) AS estimated_cost_usd',
            ]))
            ->groupByRaw($dateExpression)
            ->orderByDesc('usage_date')
            ->limit(max(1, $limit))
            ->get()
            ->reverse()
            ->values();

        return [
            'labels' => $rows->map(fn (stdClass $row): string => CarbonImmutable::parse(
                $this->text($row->usage_date),
            )->format('M j, Y'))->all(),
            'requests' => $rows->map(
                fn (stdClass $row): int => $this->integer($row->requests),
            )->all(),
            'billable_units' => $rows->map(
                fn (stdClass $row): int => $this->integer($row->billable_units),
            )->all(),
            'estimated_cost_usd' => $rows->map(
                fn (stdClass $row): float => (float) $this->decimal($row->estimated_cost_usd),
            )->all(),
        ];
    }

    /**
     * @param  Builder<PlayerVoiceUsage>  $query
     * @return array<int, array{
     *     provider:string,
     *     label:string,
     *     requests:int,
     *     average_billable_units:int,
     *     average_cost_usd:float,
     *     average_latency_ms:int,
     *     pricing_coverage:float,
     * }>
     */
    public function providerAverages(Builder $query): array
    {
        $rows = DB::query()
            ->fromSub($this->filteredRows($query, [
                'provider',
                'billable_units',
                'latency_ms',
                'estimated_cost_usd',
                'pricing_available',
            ]), 'usage')
            ->selectRaw(implode(', ', [
                'provider',
                'COUNT(*) AS requests',
                'COALESCE(SUM(billable_units), 0) AS billable_units',
                'COALESCE(SUM(latency_ms), 0) AS latency_ms',
                'COALESCE(SUM(estimated_cost_usd), 0) AS estimated_cost_usd',
                'COALESCE(SUM(CASE WHEN pricing_available = true THEN 1 ELSE 0 END), 0) AS priced_requests',
            ]))
            ->groupBy('provider')
            ->orderBy('provider')
            ->get();

        return $rows->map(function (stdClass $row): array {
            $requests = max(1, $this->integer($row->requests));
            $provider = $this->text($row->provider, 'unknown');

            return [
                'provider' => $provider,
                'label' => $this->providerLabel($provider).' · '.number_format($requests).' req',
                'requests' => $requests,
                'average_billable_units' => (int) round(
                    $this->integer($row->billable_units) / $requests,
                ),
                'average_cost_usd' => (float) $this->decimal($row->estimated_cost_usd) / $requests,
                'average_latency_ms' => (int) round(
                    $this->integer($row->latency_ms) / $requests,
                ),
                'pricing_coverage' => ($this->integer($row->priced_requests) / $requests) * 100,
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<PlayerVoiceUsage>  $query
     * @param  array<int, string>  $columns
     */
    private function filteredRows(Builder $query, array $columns): QueryBuilder
    {
        return (clone $query)
            ->reorder()
            ->select(array_map(
                static fn (string $column): string => "player_voice_usages.{$column}",
                $columns,
            ))
            ->toBase();
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function decimal(mixed $value): string
    {
        return is_numeric($value) ? (string) $value : '0';
    }

    private function text(mixed $value, string $fallback = ''): string
    {
        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : $fallback;
    }

    private function providerLabel(string $provider): string
    {
        return match (mb_strtolower($provider)) {
            'elevenlabs' => 'ElevenLabs',
            'deepgram' => 'Deepgram',
            default => Str::headline($provider),
        };
    }
}
