<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('loads only active worlds ordered by position with published stories', function () {
    $firstWorld = World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
        'tagline' => 'Find love without losing yourself.',
        'position' => 1,
        'is_active' => true,
    ]);

    $secondWorld = World::factory()->create([
        'title' => 'Gothic Horrors',
        'slug' => 'gothic-horrors',
        'position' => 2,
        'is_active' => true,
    ]);

    World::factory()->inactive()->create([
        'title' => 'Hidden World',
        'slug' => 'hidden-world',
        'position' => 0,
    ]);

    $publishedStory = Story::factory()->create([
        'title' => 'Emma',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $draftStory = Story::factory()->create([
        'title' => 'Unfinished Draft',
        'status' => StoryStatusEnum::DRAFT,
        'published_at' => null,
    ]);

    $firstWorld->stories()->attach($publishedStory->id, ['position' => 0]);
    $firstWorld->stories()->attach($draftStory->id, ['position' => 1]);
    $secondWorld->stories()->attach($publishedStory->id, ['position' => 0]);

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Index')
                ->has('worlds', 2)
                ->where('worlds.0.title', 'Jane Austen')
                ->where('worlds.0.slug', 'jane-austen')
                ->where('worlds.0.tagline', 'Find love without losing yourself.')
                ->where('worlds.0.stories_count', 1)
                ->where('worlds.0.stories.0.title', 'Emma')
                ->where('worlds.1.title', 'Gothic Horrors')
                ->where('worldsCount', 2)
                ->etc()
        );
});

it('shows at most three worlds on the homepage and the full count on view all', function () {
    World::factory()->count(4)->sequence(
        ['title' => 'First', 'slug' => 'first', 'position' => 1],
        ['title' => 'Second', 'slug' => 'second', 'position' => 2],
        ['title' => 'Third', 'slug' => 'third', 'position' => 3],
        ['title' => 'Fourth', 'slug' => 'fourth', 'position' => 4],
    )->create([
        'is_active' => true,
    ]);

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Index')
                ->has('worlds', 3)
                ->where('worlds.0.title', 'First')
                ->where('worlds.1.title', 'Second')
                ->where('worlds.2.title', 'Third')
                ->where('worldsCount', 4)
                ->etc()
        );
});
