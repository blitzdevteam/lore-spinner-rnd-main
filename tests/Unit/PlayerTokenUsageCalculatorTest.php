<?php

declare(strict_types=1);

use App\Services\ChaosEngineService;
use App\Services\Runtime\PlayerTokenUsageCalculator;
use Carbon\CarbonImmutable;

it('calculates uncached output and both cache price subdivisions exactly', function () {
    $calculated = app(PlayerTokenUsageCalculator::class)->calculate(
        'claude-sonnet-4-6',
        [
            'prompt_tokens' => 1_000,
            'completion_tokens' => 500,
            'cache_write_input_tokens' => 2_000,
            'cache_read_input_tokens' => 10_000,
            'thought_tokens' => 100,
        ],
        CarbonImmutable::parse('2026-08-10T12:00:00Z'),
    );

    expect($calculated)
        ->provider->toBe('anthropic')
        ->input_tokens->toBe(1_000)
        ->output_tokens->toBe(500)
        ->cache_write_input_tokens->toBe(2_000)
        ->cache_read_input_tokens->toBe(10_000)
        ->cached_input_tokens->toBe(12_000)
        ->total_input_tokens->toBe(13_000)
        ->total_tokens->toBe(13_500)
        ->reasoning_tokens->toBe(100)
        ->input_cost_usd->toBe('0.003000000000')
        ->output_cost_usd->toBe('0.007500000000')
        ->cache_write_cost_usd->toBe('0.012000000000')
        ->cache_read_cost_usd->toBe('0.003000000000')
        ->total_cost_usd->toBe('0.025500000000')
        ->usage_reported->toBeTrue();
});

it('retains sub-microdollar precision for low cached-input rates', function () {
    $calculated = app(PlayerTokenUsageCalculator::class)->calculate(
        'gpt-5.4-mini',
        ['cache_read_input_tokens' => 1],
        CarbonImmutable::parse('2026-08-10T12:00:00Z'),
    );

    expect($calculated['cache_read_price_per_million_usd'])->toBe('0.075000')
        ->and($calculated['cache_read_cost_usd'])->toBe('0.000000075000')
        ->and($calculated['total_cost_usd'])->toBe('0.000000075000');
});

it('selects the price period active when usage occurred', function () {
    $calculator = app(PlayerTokenUsageCalculator::class);
    $intro = $calculator->calculate(
        'claude-sonnet-5',
        ['prompt_tokens' => 1_000_000, 'completion_tokens' => 1_000_000],
        CarbonImmutable::parse('2026-08-31T23:59:59Z'),
    );
    $standard = $calculator->calculate(
        'claude-sonnet-5',
        ['prompt_tokens' => 1_000_000, 'completion_tokens' => 1_000_000],
        CarbonImmutable::parse('2026-09-01T00:00:00Z'),
    );

    expect($intro['pricing_version'])->toBe('anthropic-intro-2026-06-30')
        ->and($intro['total_cost_usd'])->toBe('12.000000000000')
        ->and($standard['pricing_version'])->toBe('anthropic-standard-2026-09-01')
        ->and($standard['total_cost_usd'])->toBe('18.000000000000');
});

it('marks missing provider usage without pretending tokens were consumed', function () {
    $calculated = app(PlayerTokenUsageCalculator::class)->calculate(
        'gpt-4.1',
        [],
        CarbonImmutable::parse('2026-08-10T12:00:00Z'),
    );

    expect($calculated['usage_reported'])->toBeFalse()
        ->and($calculated['total_tokens'])->toBe(0)
        ->and($calculated['total_cost_usd'])->toBe('0.000000000000');
});

it('requires every selectable gameplay model to have matching pricing', function () {
    /** @var array<string, array{provider:string}> $pricing */
    $pricing = config('player-token-usage.models');

    expect(array_keys($pricing))->toEqualCanonicalizing(
        array_keys(ChaosEngineService::MODEL_CONFIG),
    );

    foreach (ChaosEngineService::MODEL_CONFIG as $model => $runtimeConfig) {
        expect($pricing[$model]['provider'])->toBe($runtimeConfig['provider']);
    }
});

it('fails closed when a future gameplay model has no price configuration', function () {
    app(PlayerTokenUsageCalculator::class)->calculate(
        'future-unpriced-model',
        ['prompt_tokens' => 1],
        CarbonImmutable::parse('2026-08-10T12:00:00Z'),
    );
})->throws(LogicException::class, 'No player token pricing is configured');
