<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LogicException;

final class PlayerTokenUsageCalculator
{
    private const RATE_DECIMALS = 6;

    private const COST_DECIMALS = 12;

    private const COST_SCALE = 1_000_000_000_000;

    /**
     * @param  array<string, mixed>  $rawUsage
     * @return array{
     *     provider:string,
     *     model:string,
     *     currency:string,
     *     pricing_version:string,
     *     pricing_effective_from:?string,
     *     pricing_source:string,
     *     input_price_per_million_usd:string,
     *     output_price_per_million_usd:string,
     *     cache_write_price_per_million_usd:string,
     *     cache_read_price_per_million_usd:string,
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
     *     usage_reported:bool,
     * }
     */
    public function calculate(
        string $model,
        array $rawUsage,
        ?CarbonInterface $occurredAt = null,
    ): array {
        $at = $occurredAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($occurredAt)->utc();
        $modelPricing = $this->modelPricing($model);
        $period = $this->activePeriod($model, $modelPricing['periods'], $at);

        $inputTokens = $this->tokens($rawUsage['prompt_tokens'] ?? 0);
        $outputTokens = $this->tokens($rawUsage['completion_tokens'] ?? 0);
        $cacheWriteTokens = $this->tokens($rawUsage['cache_write_input_tokens'] ?? 0);
        $cacheReadTokens = $this->tokens($rawUsage['cache_read_input_tokens'] ?? 0);
        $reasoningTokens = $this->tokens($rawUsage['thought_tokens'] ?? 0);
        $cachedInputTokens = $cacheWriteTokens + $cacheReadTokens;
        $totalInputTokens = $inputTokens + $cachedInputTokens;

        [$inputRate, $inputCost] = $this->cost($inputTokens, $period['input']);
        [$outputRate, $outputCost] = $this->cost($outputTokens, $period['output']);
        [$cacheWriteRate, $cacheWriteCost] = $this->cost(
            $cacheWriteTokens,
            $period['cache_write'],
        );
        [$cacheReadRate, $cacheReadCost] = $this->cost(
            $cacheReadTokens,
            $period['cache_read'],
        );
        $currency = config('player-token-usage.currency', 'USD');
        if (! is_string($currency)) {
            throw new LogicException('Player token usage currency must be a string.');
        }

        return [
            'provider' => $modelPricing['provider'],
            'model' => $model,
            'currency' => $currency,
            'pricing_version' => $period['version'],
            'pricing_effective_from' => $period['effective_from'],
            'pricing_source' => $period['source'],
            'input_price_per_million_usd' => $inputRate,
            'output_price_per_million_usd' => $outputRate,
            'cache_write_price_per_million_usd' => $cacheWriteRate,
            'cache_read_price_per_million_usd' => $cacheReadRate,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_write_input_tokens' => $cacheWriteTokens,
            'cache_read_input_tokens' => $cacheReadTokens,
            'cached_input_tokens' => $cachedInputTokens,
            'total_input_tokens' => $totalInputTokens,
            'total_tokens' => $totalInputTokens + $outputTokens,
            'reasoning_tokens' => $reasoningTokens,
            'input_cost_usd' => $this->formatCost($inputCost),
            'output_cost_usd' => $this->formatCost($outputCost),
            'cache_write_cost_usd' => $this->formatCost($cacheWriteCost),
            'cache_read_cost_usd' => $this->formatCost($cacheReadCost),
            'total_cost_usd' => $this->formatCost(
                $inputCost + $outputCost + $cacheWriteCost + $cacheReadCost,
            ),
            'usage_reported' => $this->wasReported($rawUsage),
        ];
    }

    /**
     * @return array{
     *     provider:string,
     *     periods:array<int, array{
     *         version:string,
     *         effective_from:?string,
     *         effective_until:?string,
     *         input:string,
     *         output:string,
     *         cache_write:string,
     *         cache_read:string,
     *         source:string,
     *     }>
     * }
     */
    private function modelPricing(string $model): array
    {
        /** @var array<string, mixed> $models */
        $models = (array) config('player-token-usage.models', []);
        $pricing = $models[$model] ?? null;

        if (! is_array($pricing) || ! is_string($pricing['provider'] ?? null)) {
            throw new LogicException("No player token pricing is configured for model [{$model}].");
        }

        $periods = $pricing['periods'] ?? null;
        if (! is_array($periods) || $periods === []) {
            throw new LogicException("No player token pricing periods are configured for model [{$model}].");
        }

        $normalizedPeriods = [];
        foreach (array_values($periods) as $period) {
            if (! is_array($period)) {
                throw new LogicException("Player token pricing [{$model}] contains an invalid period.");
            }

            /** @var array<string, mixed> $period */
            $version = $this->requiredString($period, 'version', $model);
            $input = $this->requiredString($period, 'input', $model);
            $output = $this->requiredString($period, 'output', $model);
            $cacheWrite = $this->requiredString($period, 'cache_write', $model);
            $cacheRead = $this->requiredString($period, 'cache_read', $model);
            $source = $this->requiredString($period, 'source', $model);

            $effectiveFrom = $period['effective_from'] ?? null;
            $effectiveUntil = $period['effective_until'] ?? null;
            if (($effectiveFrom !== null && ! is_string($effectiveFrom))
                || ($effectiveUntil !== null && ! is_string($effectiveUntil))) {
                throw new LogicException("Player token pricing [{$model}] has an invalid effective period.");
            }

            $normalizedPeriods[] = [
                'version' => $version,
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
                'input' => $input,
                'output' => $output,
                'cache_write' => $cacheWrite,
                'cache_read' => $cacheRead,
                'source' => $source,
            ];
        }

        return [
            'provider' => $pricing['provider'],
            'periods' => $normalizedPeriods,
        ];
    }

    /**
     * @param  array<int, array{
     *     version:string,
     *     effective_from:?string,
     *     effective_until:?string,
     *     input:string,
     *     output:string,
     *     cache_write:string,
     *     cache_read:string,
     *     source:string,
     * }>  $periods
     * @return array{
     *     version:string,
     *     effective_from:?string,
     *     effective_until:?string,
     *     input:string,
     *     output:string,
     *     cache_write:string,
     *     cache_read:string,
     *     source:string,
     * }
     */
    private function activePeriod(string $model, array $periods, CarbonImmutable $at): array
    {
        foreach ($periods as $period) {
            $from = $period['effective_from'] !== null
                ? CarbonImmutable::parse($period['effective_from'], 'UTC')
                : null;
            $until = $period['effective_until'] !== null
                ? CarbonImmutable::parse($period['effective_until'], 'UTC')
                : null;

            if (($from === null || $at->greaterThanOrEqualTo($from))
                && ($until === null || $at->lessThan($until))) {
                return $period;
            }
        }

        throw new LogicException(
            "No player token pricing period for model [{$model}] is active at [{$at->toIso8601String()}].",
        );
    }

    private function tokens(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    /** @param array<string, mixed> $period */
    private function requiredString(array $period, string $key, string $model): string
    {
        $value = $period[$key] ?? null;
        if (! is_string($value)) {
            throw new LogicException("Player token pricing [{$model}] has an invalid [{$key}] value.");
        }

        return $value;
    }

    /**
     * Convert a USD/MTok rate to a 6-decimal integer first. Multiplying that
     * integer by a token count directly produces a USD value scaled to twelve
     * decimal places because the per-million divisor supplies the other 10^6.
     *
     * @return array{0:string,1:int}
     */
    private function cost(int $tokens, string $rate): array
    {
        [$normalizedRate, $scaledRate] = $this->scaledRate($rate);

        return [$normalizedRate, $tokens * $scaledRate];
    }

    /** @return array{0:string,1:int} */
    private function scaledRate(string $rate): array
    {
        if (preg_match('/^\d+(?:\.\d{1,6})?$/', $rate) !== 1) {
            throw new LogicException("Invalid player token price [{$rate}]. Use at most six decimals.");
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $fraction = mb_str_pad($fraction, self::RATE_DECIMALS, '0');
        $normalized = $whole.'.'.$fraction;
        $scaled = ((int) $whole * 1_000_000) + (int) $fraction;

        return [$normalized, $scaled];
    }

    private function formatCost(int $scaledCost): string
    {
        $whole = intdiv($scaledCost, self::COST_SCALE);
        $fraction = $scaledCost % self::COST_SCALE;

        return $whole.'.'.mb_str_pad(
            (string) $fraction,
            self::COST_DECIMALS,
            '0',
            STR_PAD_LEFT,
        );
    }

    /** @param array<string, mixed> $usage */
    private function wasReported(array $usage): bool
    {
        foreach ([
            'prompt_tokens',
            'completion_tokens',
            'cache_write_input_tokens',
            'cache_read_input_tokens',
            'thought_tokens',
        ] as $key) {
            if (array_key_exists($key, $usage)) {
                return true;
            }
        }

        return false;
    }
}
