<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
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
                ->etc()
        );
});

it('shows an active world and its published stories', function () {
    $world = World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
        'tagline' => 'Find love without losing yourself.',
    ]);

    $story = Story::factory()->create([
        'title' => 'Pride and Prejudice',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $world->stories()->attach($story->id, ['position' => 0]);

    $this->get(route('worlds.show', $world))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Worlds/Show')
                ->where('world.title', 'Jane Austen')
                ->where('world.tagline', 'Find love without losing yourself.')
                ->where('world.stories.0.title', 'Pride and Prejudice')
                ->etc()
        );
});

it('does not show an inactive world', function () {
    $world = World::factory()->inactive()->create([
        'slug' => 'hidden-world',
    ]);

    $this->get(route('worlds.show', $world))->assertNotFound();
});
