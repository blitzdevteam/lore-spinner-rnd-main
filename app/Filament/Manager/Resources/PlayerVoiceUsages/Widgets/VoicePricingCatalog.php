<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;

final class VoicePricingCatalog extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.manager.resources.player-voice-usages.widgets.pricing-catalog';

    /**
     * @return array<int, array{
     *     provider:string,
     *     provider_label:string,
     *     model:string,
     *     model_label:string,
     *     billing_unit:string,
     *     rate_per_thousand:string,
     *     rate_per_million:string,
     *     version:string,
     *     source:string,
     *     is_active:bool,
     * }>
     */
    public function pricingRows(): array
    {
        $configuredProviders = config('player-voice-usage.providers', []);
        $providers = is_array($configuredProviders) ? $configuredProviders : [];
        $activeProvider = $this->configString('services.tts_provider', 'elevenlabs');
        $activeModels = [
            'elevenlabs' => $this->configString('services.elevenlabs.model_id', 'eleven_v3'),
            'deepgram' => $this->configString('services.deepgram.voice_model', 'aura-2-thalia-en'),
        ];
        $now = CarbonImmutable::now('UTC');
        $rows = [];

        foreach ($providers as $provider => $providerConfig) {
            if (! is_string($provider) || ! is_array($providerConfig)) {
                continue;
            }

            $models = $providerConfig['models'] ?? [];
            if (! is_array($models)) {
                continue;
            }

            $activeModel = $this->configuredModelKey(
                $models,
                $activeModels[$provider] ?? '',
            );
            $providerLabel = $providerConfig['label'] ?? null;

            foreach ($models as $model => $modelConfig) {
                if (! is_string($model) || ! is_array($modelConfig)) {
                    continue;
                }

                $periods = $modelConfig['periods'] ?? [];
                if (! is_array($periods)) {
                    continue;
                }

                $period = $this->activePeriod($periods, $now);
                $billingUnit = $modelConfig['billing_unit'] ?? null;
                $rate = $period['rate_per_thousand'] ?? null;

                if (! is_string($billingUnit) || ! is_string($rate)) {
                    continue;
                }

                $label = $modelConfig['label'] ?? null;
                $rows[] = [
                    'provider' => $provider,
                    'provider_label' => is_string($providerLabel)
                        ? $providerLabel
                        : Str::headline($provider),
                    'model' => $model,
                    'model_label' => is_string($label) ? $label : Str::headline($model),
                    'billing_unit' => $billingUnit,
                    'rate_per_thousand' => $rate,
                    'rate_per_million' => number_format((float) $rate * 1_000, 2, '.', ''),
                    'version' => is_string($period['version'] ?? null) ? $period['version'] : '',
                    'source' => is_string($period['source'] ?? null) ? $period['source'] : '',
                    'is_active' => $provider === $activeProvider
                        && $model === $activeModel,
                ];
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'pricingGroups' => collect($this->pricingRows())->groupBy('provider')->all(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $periods
     * @return null|array{version:mixed,rate_per_thousand:mixed,source:mixed}
     */
    private function activePeriod(array $periods, CarbonImmutable $now): ?array
    {
        foreach ($periods as $period) {
            if (! is_array($period)) {
                continue;
            }

            $effectiveFrom = $period['effective_from'] ?? null;
            $effectiveUntil = $period['effective_until'] ?? null;
            if (($effectiveFrom !== null && ! is_string($effectiveFrom))
                || ($effectiveUntil !== null && ! is_string($effectiveUntil))) {
                continue;
            }

            $from = $effectiveFrom === null
                ? null
                : CarbonImmutable::parse($effectiveFrom, 'UTC');
            $until = $effectiveUntil === null
                ? null
                : CarbonImmutable::parse($effectiveUntil, 'UTC');

            if (($from === null || $now->greaterThanOrEqualTo($from))
                && ($until === null || $now->lessThan($until))) {
                return [
                    'version' => $period['version'] ?? null,
                    'rate_per_thousand' => $period['rate_per_thousand'] ?? null,
                    'source' => $period['source'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $models
     */
    private function configuredModelKey(array $models, string $configuredModel): ?string
    {
        if (array_key_exists($configuredModel, $models)) {
            return $configuredModel;
        }

        $matchedModel = null;
        $longestMatchingPrefix = -1;

        foreach ($models as $model => $modelConfig) {
            if (! is_string($model) || ! is_array($modelConfig)) {
                continue;
            }

            $prefixes = $modelConfig['prefixes'] ?? [];
            if (! is_array($prefixes)) {
                continue;
            }

            foreach ($prefixes as $prefix) {
                if (! is_string($prefix)
                    || ! str_starts_with($configuredModel, $prefix)
                    || mb_strlen($prefix) <= $longestMatchingPrefix) {
                    continue;
                }

                $matchedModel = $model;
                $longestMatchingPrefix = mb_strlen($prefix);
            }
        }

        return $matchedModel;
    }

    private function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }
}
