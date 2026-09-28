<?php

declare(strict_types=1);

use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('does not send a continue-story game to guests', function (): void {
    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Index')
                ->where('lastGame', null)
                ->etc()
        );
});

it('sends the authenticated user latest game with episode details for continue story', function (): void {
    $user = User::factory()->create([
        'is_active' => true,
    ]);
    $story = Story::factory()->create([
        'title' => 'Sherlock Holmes: The Speckled Band',
        'slug' => 'the-adventure-of-the-speckled-band',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $story->chapters()->create([
        'position' => 1,
        'title' => 'The strange note',
        'teaser' => 'A letter arrives at Baker Street.',
        'content' => 'Holmes unfolds the note.',
        'status' => ChapterStatusEnum::READY_TO_PLAY,
    ]);
    $olderStory = Story::factory()->create([
        'title' => 'Older Adventure',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $olderGame = Game::query()->create([
        'story_id' => $olderStory->id,
        'user_id' => $user->id,
        'current_session_number' => 2,
        'model' => 'claude-sonnet-4-6',
    ]);
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    Game::query()->whereKey($olderGame->id)->update([
        'updated_at' => now()->subDay(),
    ]);
    $game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>The game begins.</p>',
        'choices' => [],
    ]);
    $game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>Holmes studies the note.</p>',
        'choices' => [],
    ]);

    $this->actingAs($user)
        ->get(route('index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Index')
                ->where('lastGame.id', $game->id)
                ->where('lastGame.current_session_number', 1)
                ->where('lastGame.prompts_count', 2)
                ->where('lastGame.story.title', 'Sherlock Holmes: The Speckled Band')
                ->where('lastGame.story.slug', 'the-adventure-of-the-speckled-band')
                ->where('lastGame.story.chapters.0.position', 1)
                ->where('lastGame.story.chapters.0.title', 'The strange note')
                ->etc()
        );

    expect($olderGame->id)->not->toBe($game->id);
});
