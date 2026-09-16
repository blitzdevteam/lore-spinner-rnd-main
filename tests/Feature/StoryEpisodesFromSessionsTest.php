<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Game;
use App\Models\GameSessionCompletion;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function storyWithSessions(int $sessionCount = 3): Story
{
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $adaptation = $story->adaptation()->create([
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
        'story_session_map' => [
            'session_allocation' => [
                [
                    'session_number' => 1,
                    'primary_dramatic_question' => 'Can the guest survive the first night?',
                    'emotional_register' => 'Unease',
                ],
                [
                    'session_number' => 2,
                    'primary_dramatic_question' => 'Who is watching from the tower?',
                    'emotional_register' => 'Dread',
                ],
            ],
            'arc_progression' => [
                [
                    'session_number' => 3,
                    'primary_dramatic_question' => 'Is escape still possible?',
                ],
            ],
        ],
    ]);

    foreach (range(1, $sessionCount) as $sessionNumber) {
        $adaptation->sessionAdaptations()->create([
            'session_number' => $sessionNumber,
            'session_status' => SessionAdaptationStatusEnum::COMPLETED,
            'boiler_status' => BoilerStatus::FINALIZED,
        ]);
    }

    // A single chapter can span several sessions; episodes must not mirror it.
    $chapter = $story->chapters()->create([
        'position' => 1,
        'title' => 'Chapter one',
        'teaser' => '-Date. 3 May. Bistritz.',
        'content' => 'Left Munich at 8:35 P.M.',
        'status' => ChapterStatusEnum::READY_TO_PLAY,
    ]);

    foreach (range(1, $sessionCount) as $sessionNumber) {
        $chapter->events()->create([
            'position' => $sessionNumber,
            'title' => "Event {$sessionNumber}",
            'content' => 'Something happens.',
            'session_number' => $sessionNumber,
        ]);
    }

    return $story;
}

it('lists one episode per adaptation session instead of one per chapter', function (): void {
    $story = storyWithSessions(8);

    $story->chapters()->createMany(
        collect(range(2, 27))->map(fn (int $position): array => [
            'position' => $position,
            'title' => "Chapter {$position}",
            'teaser' => '-Date. Raw script prose.',
            'content' => 'Body.',
            'status' => ChapterStatusEnum::READY_TO_PLAY,
        ])->all()
    );

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Stories/Show')
                ->has('episodes', 8)
                ->where('episodes.0.number', 1)
                ->where('episodes.0.teaser', 'Can the guest survive the first night?')
                ->where('episodes.2.teaser', 'Is escape still possible?')
                ->etc()
        );
});

it('marks an episode completed only when its session is finished for the current cycle', function (): void {
    $story = storyWithSessions();
    $user = User::factory()->create(['is_active' => true]);

    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 2,
        'current_session_complete' => false,
        'model' => 'claude-sonnet-4-6',
    ]);

    GameSessionCompletion::query()->create([
        'game_id' => $game->id,
        'story_id' => $story->id,
        'user_id' => $user->id,
        'story_cycle_number' => $game->current_story_cycle_number,
        'session_number' => 1,
        'completed_at' => now(),
    ]);

    GameSessionCompletion::query()->create([
        'game_id' => $game->id,
        'story_id' => $story->id,
        'user_id' => $user->id,
        'story_cycle_number' => $game->current_story_cycle_number,
        'session_number' => 2,
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('stories.show', $story))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('episodes.0.is_completed', true)
                ->where('episodes.1.is_completed', false)
                ->where('episodes.2.is_completed', false)
                ->etc()
        );
});

it('reports no completed episodes for guests', function (): void {
    $story = storyWithSessions();

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('episodes.0.is_completed', false)
                ->etc()
        );
});
