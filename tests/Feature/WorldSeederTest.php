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
        'slug' => 'the-unwritten-ones',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $this->seed(WorldSeeder::class);

    $world = World::query()->where('slug', 'lorespinner-originals')->first();

    expect($world)->not->toBeNull()
        ->and($world->title)->toBe('LoreSpinner Originals')
        ->and($world->stories()->whereKey($story->id)->exists())->toBeTrue();

    expect(World::query()->where('slug', 'standalone')->exists())->toBeFalse();
});
