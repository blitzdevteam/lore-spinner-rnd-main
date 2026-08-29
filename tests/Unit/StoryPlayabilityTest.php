<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Support\StoryPlayability;

it('allows play only for approved review slugs with completed adaptation', function () {
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

it('blocks play for approved slugs still boiling', function () {
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

it('blocks play for completed adaptations outside the review list', function () {
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
