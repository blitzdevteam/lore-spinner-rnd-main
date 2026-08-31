<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Support\StoryPlayability;

it('orders launch slugs ahead of boiled extras and unknown titles', function () {
    expect(StoryPlayability::orderIndex('the-wonderful-wizard-of-oz'))->toBe(0)
        ->and(StoryPlayability::orderIndex('peter-pan'))->toBe(1)
        ->and(StoryPlayability::orderIndex('the-strange-case-of-dr-jekyll-and-mr-hyde'))
        ->toBe(StoryPlayability::orderIndex('dr-jekyll-and-mr-hyde'))
        ->and(StoryPlayability::orderIndex('momotaro-the-peach-boy'))
        ->toBeGreaterThan(StoryPlayability::orderIndex('sir-gawain-and-the-green-knight'))
        ->and(StoryPlayability::orderIndex('wasteland'))->toBe(PHP_INT_MAX);
});

it('keeps non-playable rnd slugs off the allowlist', function () {
    expect(StoryPlayability::isLaunchSlug('the-adventure-of-the-speckled-band'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('emma'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('peter-pan'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('wasteland'))->toBeFalse()
        ->and(StoryPlayability::isLaunchSlug('treasure-island'))->toBeFalse();
});

it('allows play only for launch-list slugs with completed adaptation', function () {
    $story = Story::factory()->create([
        'slug' => 'the-adventure-of-the-speckled-band',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    $story->load('adaptation');

    expect(StoryPlayability::isPlayable($story))->toBeTrue();
});

it('blocks play for launch-list slugs still boiling', function () {
    $story = Story::factory()->create([
        'slug' => 'the-adventure-of-the-blue-carbuncle',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::ADAPTING_SESSIONS,
        'boiler_enabled' => true,
    ]);

    $story->load('adaptation');

    expect(StoryPlayability::isPlayable($story))->toBeFalse();
});

it('allows play for boiled extras with completed adaptation', function () {
    $story = Story::factory()->create([
        'slug' => 'the-unwritten-ones',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    $story->load('adaptation');

    expect(StoryPlayability::isPlayable($story))->toBeTrue();
});

it('allows play for legacy jekyll slug with completed adaptation', function () {
    $story = Story::factory()->create([
        'slug' => 'the-strange-case-of-dr-jekyll-and-mr-hyde',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    $story->load('adaptation');

    expect(StoryPlayability::isPlayable($story))->toBeTrue();
});

it('blocks play for completed adaptations outside the allowlist', function () {
    $story = Story::factory()->create([
        'slug' => 'wasteland',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    $story->load('adaptation');

    expect(StoryPlayability::isPlayable($story))->toBeFalse();
});
