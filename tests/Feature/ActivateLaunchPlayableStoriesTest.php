<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Models\World;
use App\Support\LaunchStoryActivator;
use App\Support\StoryPlayability;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publishes and attaches a legacy jekyll slug after boiler completion', function (): void {
    $story = Story::factory()->create([
        'slug' => 'the-strange-case-of-dr-jekyll-and-mr-hyde',
        'status' => StoryStatusEnum::DRAFT,
        'published_at' => null,
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    expect(LaunchStoryActivator::activate($story->fresh()))->toBeTrue();

    $story->refresh();

    expect($story->status)->toBe(StoryStatusEnum::PUBLISHED)
        ->and($story->published_at)->not->toBeNull()
        ->and(StoryPlayability::isPlayable($story->load('adaptation')))->toBeTrue();

    $world = World::query()->where('slug', 'gothic-horrors')->first();

    expect($world)->not->toBeNull()
        ->and($world->stories()->whereKey($story->id)->exists())->toBeTrue();
});

it('activates completed launch titles via artisan command', function (): void {
    $story = Story::factory()->create([
        'slug' => 'the-strange-case-of-dr-jekyll-and-mr-hyde',
        'status' => StoryStatusEnum::DRAFT,
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    $this->seed(WorldSeeder::class);

    $this->artisan('stories:activate-launch-playable', ['story' => $story->slug])
        ->assertSuccessful();

    $story->refresh();

    expect($story->status)->toBe(StoryStatusEnum::PUBLISHED);

    $world = World::query()->where('slug', 'gothic-horrors')->first();

    expect($world?->stories()->whereKey($story->id)->exists())->toBeTrue();
});
