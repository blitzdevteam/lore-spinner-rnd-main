<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LogicException;

final class PlayerVoiceUsageCalculator
{
    private const RATE_DECIMALS = 6;

    private const COST_DECIMALS = 12;

    private const COST_SCALE = 1_000_000_000_000;

    /**
     * @return array{
     *     currency:string,
     *     billing_unit:string,
     *     pricing_available:bool,
     *     pricing_version:?string,
     *     pricing_effective_from:?string,
     *     pricing_source:?string,
     *     rate_per_thousand_usd:?string,
     *     estimated_cost_usd:?string,
     * }
     */
    public function calculate(
        string $provider,
        string $model,
        int $billableUnits,
        string $fallbackBillingUnit,
        ?CarbonInterface $occurredAt = null,
    ): array {
        $currency = config('player-voice-usage.currency', 'USD');
        if (! is_string($currency)) {
            throw new LogicException('Player voice usage currency must be a string.');
        }

        $at = $occurredAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($occurredAt)->utc();
        $pricing = $this->modelPricing($provider, $model);
        $period = $pricing === null
            ? null
            : $this->activePeriod($pricing['periods'], $at);

        if ($pricing === null || $period === null) {
            return [
                'currency' => $currency,
                'billing_unit' => $fallbackBillingUnit,
                'pricing_available' => false,
                'pricing_version' => null,
                'pricing_effective_from' => null,
                'pricing_source' => null,
                'rate_per_thousand_usd' => null,
                'estimated_cost_usd' => null,
            ];
        }

        [$rate, $scaledRate] = $this->scaledRate($period['rate_per_thousand']);
        $scaledCost = max(0, $billableUnits) * $scaledRate * 1_000;

        return [
            'currency' => $currency,
            'billing_unit' => $pricing['billing_unit'],
            'pricing_available' => true,
            'pricing_version' => $period['version'],
            'pricing_effective_from' => $period['effective_from'],
            'pricing_source' => $period['source'],
            'rate_per_thousand_usd' => $rate,
            'estimated_cost_usd' => $this->formatCost($scaledCost),
        ];
    }

    /**
     * @return null|array{
     *     billing_unit:string,
     *     periods:array<int, array{
     *         version:string,
     *         effective_from:?string,
     *         effective_until:?string,
     *         rate_per_thousand:string,
     *         source:string,
     *     }>,
     * }
     */
    private function modelPricing(string $provider, string $model): ?array
    {
        /** @var array<string, mixed> $providers */
        $providers = (array) config('player-voice-usage.providers', []);
        $providerConfig = $providers[$provider] ?? null;
        if (! is_array($providerConfig)) {
            return null;
        }

        $models = $providerConfig['models'] ?? null;
        if (! is_array($models)) {
            throw new LogicException("Player voice pricing provider [{$provider}] has no models.");
        }

        $modelConfig = $models[$model] ?? null;
        if (! is_array($modelConfig)) {
            $longestMatchingPrefix = -1;

            foreach ($models as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                $prefixes = $candidate['prefixes'] ?? [];
                if (! is_array($prefixes)) {
                    continue;
                }

                foreach ($prefixes as $prefix) {
                    if (! is_string($prefix)
                        || ! str_starts_with($model, $prefix)
                        || mb_strlen($prefix) <= $longestMatchingPrefix) {
                        continue;
                    }

                    $modelConfig = $candidate;
                    $longestMatchingPrefix = mb_strlen($prefix);
                }
            }
        }

        if (! is_array($modelConfig)) {
            return null;
        }

        $billingUnit = $modelConfig['billing_unit'] ?? null;
        $periods = $modelConfig['periods'] ?? null;
        if (! is_string($billingUnit) || ! is_array($periods) || $periods === []) {
            throw new LogicException("Player voice pricing [{$provider}:{$model}] is invalid.");
        }

        $normalizedPeriods = [];
        foreach (array_values($periods) as $period) {
            if (! is_array($period)) {
                throw new LogicException("Player voice pricing [{$provider}:{$model}] contains an invalid period.");
            }

            $effectiveFrom = $period['effective_from'] ?? null;
            $effectiveUntil = $period['effective_until'] ?? null;
            if (($effectiveFrom !== null && ! is_string($effectiveFrom))
                || ($effectiveUntil !== null && ! is_string($effectiveUntil))) {
                throw new LogicException("Player voice pricing [{$provider}:{$model}] has an invalid effective period.");
            }

            $normalizedPeriods[] = [
                'version' => $this->requiredString($period, 'version', $provider, $model),
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
                'rate_per_thousand' => $this->requiredString($period, 'rate_per_thousand', $provider, $model),
                'source' => $this->requiredString($period, 'source', $provider, $model),
            ];
        }

        return [
            'billing_unit' => $billingUnit,
            'periods' => $normalizedPeriods,
        ];
    }

    /**
     * @param  array<int, array{version:string,effective_from:?string,effective_until:?string,rate_per_thousand:string,source:string}>  $periods
     * @return null|array{version:string,effective_from:?string,effective_until:?string,rate_per_thousand:string,source:string}
     */
    private function activePeriod(array $periods, CarbonImmutable $at): ?array
    {
        foreach ($periods as $period) {
            $from = $period['effective_from'] === null
                ? null
                : CarbonImmutable::parse($period['effective_from'], 'UTC');
            $until = $period['effective_until'] === null
                ? null
                : CarbonImmutable::parse($period['effective_until'], 'UTC');

            if (($from === null || $at->greaterThanOrEqualTo($from))
                && ($until === null || $at->lessThan($until))) {
                return $period;
            }
        }

        return null;
    }

    /** @param array<array-key, mixed> $period */
    private function requiredString(
        array $period,
        string $key,
        string $provider,
        string $model,
    ): string {
        $value = $period[$key] ?? null;
        if (! is_string($value)) {
            throw new LogicException("Player voice pricing [{$provider}:{$model}] has an invalid [{$key}] value.");
        }

        return $value;
    }

    /** @return array{0:string,1:int} */
    private function scaledRate(string $rate): array
    {
        if (preg_match('/^\d+(?:\.\d{1,6})?$/', $rate) !== 1) {
            throw new LogicException("Invalid player voice price [{$rate}]. Use at most six decimals.");
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $fraction = mb_str_pad($fraction, self::RATE_DECIMALS, '0');

        return [
            $whole.'.'.$fraction,
            ((int) $whole * 1_000_000) + (int) $fraction,
        ];
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
}
