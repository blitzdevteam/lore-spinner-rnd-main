<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

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

it('features baker street, jane austen, and gothic horrors ahead of other worlds', function () {
    World::factory()->create([
        'title' => 'World of Oz',
        'slug' => 'world-of-oz',
        'position' => 0,
        'is_active' => true,
    ]);
    World::factory()->create([
        'title' => 'Gothic Horrors',
        'slug' => 'gothic-horrors',
        'position' => 9,
        'is_active' => true,
    ]);
    World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
        'position' => 8,
        'is_active' => true,
    ]);
    World::factory()->create([
        'title' => 'Baker Street',
        'slug' => 'baker-street',
        'position' => 7,
        'is_active' => true,
    ]);

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Index')
                ->has('worlds', 3)
                ->where('worlds.0.slug', 'baker-street')
                ->where('worlds.1.slug', 'jane-austen')
                ->where('worlds.2.slug', 'gothic-horrors')
                ->where('worldsCount', 4)
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

it('includes every published story on homepage worlds so the enter-world modal can list them', function () {
    $world = World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
        'position' => 1,
        'is_active' => true,
    ]);

    $stories = Story::factory()->count(5)->sequence(
        ['title' => 'Emma', 'slug' => 'emma'],
        ['title' => 'Persuasion', 'slug' => 'persuasion'],
        ['title' => 'Mansfield Park', 'slug' => 'mansfield-park'],
        ['title' => 'Northanger Abbey', 'slug' => 'northanger-abbey'],
        ['title' => 'Sense and Sensibility', 'slug' => 'sense-and-sensibility'],
    )->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    foreach ($stories as $index => $story) {
        $world->stories()->attach($story->id, ['position' => $index]);
    }

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Index')
                ->has('worlds', 1)
                ->where('worlds.0.title', 'Jane Austen')
                ->has('worlds.0.background')
                ->has('worlds.0.stories', 5)
                ->where('worlds.0.stories.0.title', 'Emma')
                ->where('worlds.0.stories.4.title', 'Sense and Sensibility')
                ->where('worlds.0.stories_count', 5)
                ->etc()
        );
});
