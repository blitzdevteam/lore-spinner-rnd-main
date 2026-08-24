<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the multiplayer host and player join page', function () {
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'title' => 'Treasure Island',
    ]);

    $this->get(route('stories.multiplayer', $story))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Multiplayer/Multiplayer')
            ->where('story.title', 'Treasure Island')
            ->where('story.slug', $story->slug)
            ->where('inviteRoomCode', null));
});

it('passes an invite room code to the multiplayer join page', function () {
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    $this->get(route('stories.multiplayer', ['story' => $story, 'room' => 'abc123']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Multiplayer/Multiplayer')
            ->where('inviteRoomCode', 'ABC123'));
});
