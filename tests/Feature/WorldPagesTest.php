<?php

declare(strict_types=1);

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\ComingSoonStory;
use App\Models\Story;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists active worlds on the worlds index page', function () {
    World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
        'position' => 1,
    ]);

    World::factory()->create([
        'title' => 'Gothic Horrors',
        'slug' => 'gothic-horrors',
        'position' => 2,
    ]);

    World::factory()->inactive()->create([
        'title' => 'Hidden World',
        'slug' => 'hidden-world',
        'position' => 0,
    ]);

    $this->get(route('worlds.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Worlds/Index')
                ->has('worlds', 2)
                ->where('worlds.0.title', 'Jane Austen')
                ->where('worlds.1.title', 'Gothic Horrors')
                ->etc()
        );
});

it('renders the worlds index when no worlds exist', function () {
    $this->get(route('worlds.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Worlds/Index')
                ->has('worlds', 0)
        );
});

it('exposes world search fields on the worlds index', function () {
    $world = World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
        'tagline' => 'Find love without losing yourself.',
        'tags' => ['Destiny', 'Courage', 'Control'],
        'position' => 1,
    ]);

    $story = Story::factory()->create([
        'title' => 'Pride and Prejudice',
        'slug' => 'pride-and-prejudice',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $world->stories()->attach($story->id, ['position' => 0]);

    $this->get(route('worlds.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Worlds/Index')
                ->where('worlds.0.title', 'Jane Austen')
                ->where('worlds.0.tagline', 'Find love without losing yourself.')
                ->where('worlds.0.tags', ['Destiny', 'Courage', 'Control'])
                ->where('worlds.0.stories.0.title', 'Pride and Prejudice')
                ->where('worlds.0.stories.0.slug', 'pride-and-prejudice')
                ->etc()
        );
});

it('shows an active world with stories, other worlds, and coming soon', function () {
    $world = World::factory()->create([
        'title' => 'Gothic Horrors',
        'slug' => 'gothic-horrors',
        'tagline' => 'Face the monster. Find out what it sees in you.',
        'description' => 'Step into timeless tales of terror where shadows linger, and the unknown is never far behind.',
        'tags' => ['Horror', 'Psychological', 'Supernatural'],
        'position' => 1,
    ]);

    World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
        'position' => 2,
    ]);

    World::factory()->inactive()->create([
        'title' => 'Hidden World',
        'slug' => 'hidden-world',
        'position' => 0,
    ]);

    $story = Story::factory()->create([
        'title' => 'Frankenstein',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $world->stories()->attach($story->id, ['position' => 0]);

    ComingSoonStory::factory()->create([
        'title' => 'Dracula',
        'section' => ComingSoonStorySectionEnum::COMING_SOON,
        'is_active' => true,
        'position' => 1,
    ]);

    ComingSoonStory::factory()->create([
        'title' => 'Hero Banner Story',
        'section' => ComingSoonStorySectionEnum::HERO,
        'is_active' => true,
        'position' => 1,
    ]);

    $this->get(route('worlds.show', $world))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Worlds/Show')
                ->where('world.title', 'Gothic Horrors')
                ->where('world.tagline', 'Face the monster. Find out what it sees in you.')
                ->where('world.description', 'Step into timeless tales of terror where shadows linger, and the unknown is never far behind.')
                ->where('world.stories.0.title', 'Frankenstein')
                ->has('otherWorlds', 1)
                ->where('otherWorlds.0.title', 'Jane Austen')
                ->where('worldsCount', 2)
                ->has('comingSoonStories', 1)
                ->where('comingSoonStories.0.title', 'Dracula')
                ->etc()
        );
});

it('does not show an inactive world', function () {
    $world = World::factory()->inactive()->create([
        'slug' => 'hidden-world',
    ]);

    $this->get(route('worlds.show', $world))->assertNotFound();
});
