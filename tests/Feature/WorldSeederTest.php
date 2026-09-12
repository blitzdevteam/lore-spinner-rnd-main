<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\World;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('upserts launch worlds and attaches seeded titles', function () {
    $story = Story::factory()->create([
        'slug' => 'the-adventure-of-the-speckled-band',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $this->seed(WorldSeeder::class);

    $world = World::query()->where('slug', 'baker-street')->first();

    expect($world)->not->toBeNull()
        ->and($world->title)->toBe('Baker Street')
        ->and($world->stories()->whereKey($story->id)->exists())->toBeTrue();

    expect(World::query()->where('slug', 'standalone')->exists())->toBeFalse();
});

it('attaches jekyll when the boiled story uses the legacy slug', function () {
    $story = Story::factory()->create([
        'slug' => 'the-strange-case-of-dr-jekyll-and-mr-hyde',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $this->seed(WorldSeeder::class);

    $world = World::query()->where('slug', 'gothic-horrors')->first();

    expect($world)->not->toBeNull()
        ->and($world->title)->toBe('Gothic Horrors')
        ->and($world->stories()->whereKey($story->id)->exists())->toBeTrue();
});

it('retires renamed worlds and moves their stories to the canonical slug', function () {
    $legacy = World::factory()->create([
        'slug' => 'land-of-oz',
        'title' => 'The World of Oz',
        'is_active' => true,
    ]);

    $story = Story::factory()->create([
        'slug' => 'the-wonderful-wizard-of-oz',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $legacy->stories()->attach($story->id, ['position' => 0]);

    $this->seed(WorldSeeder::class);

    $canonical = World::query()->where('slug', 'world-of-oz')->first();

    expect($canonical)->not->toBeNull()
        ->and($canonical->title)->toBe('World of Oz')
        ->and($canonical->stories()->whereKey($story->id)->exists())->toBeTrue()
        ->and($legacy->fresh()->is_active)->toBeFalse()
        ->and($legacy->stories()->count())->toBe(0);
});
