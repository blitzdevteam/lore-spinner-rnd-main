<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Game;
use App\Models\GameSessionCompletion;
use App\Models\PlayableMoment;
use App\Models\PlayableSessionCut;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            // Session 3 has no cold open, so it must fall back to the session map.
            'entry_point_diagnosis' => $sessionNumber === 3 ? [] : [
                'cold_open' => "Session {$sessionNumber}: the carriage stops where the road gives up."
                    ."\n\nA later paragraph that must not reach the card.",
            ],
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
                ->where('episodes.0.teaser', 'Session 1: the carriage stops where the road gives up.')
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
        'current_story_cycle_number' => 1,
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

it('shows the event being played on the in-progress episode and locks the rest', function (): void {
    $story = storyWithSessions();
    $user = User::factory()->create(['is_active' => true]);
    $chapter = $story->chapters()->firstOrFail();

    $second = $chapter->events()->create([
        'position' => 4,
        'title' => 'Event 2b',
        'content' => 'The hall has another door.',
        'session_number' => 2,
    ]);

    $chapter->events()->create([
        'position' => 5,
        'title' => 'Event 2c',
        'content' => 'A key turns in the lock.',
        'session_number' => 2,
    ]);

    $session = $story->adaptation->sessionAdaptations()->where('session_number', 2)->firstOrFail();

    $cut = PlayableSessionCut::query()->create([
        'story_id' => $story->id,
        'session_adaptation_id' => $session->id,
        'session_number' => 2,
        'cut_key' => 'standard',
        'status' => 'finalized',
    ]);

    $moment = PlayableMoment::query()->create([
        'playable_session_cut_id' => $cut->id,
        'moment_key' => 'S2_M02',
        'moment_order' => 2,
        'anchor_event_id' => $second->id,
        'source_event_ids' => [$second->id],
        'scene_card' => [],
    ]);

    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 2,
        'current_story_cycle_number' => 1,
        'current_playable_moment_id' => $moment->id,
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

    $this->actingAs($user)
        ->get(route('stories.show', $story))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('episodes.0.is_completed', true)
                ->where('episodes.0.events_played', 0)
                ->where('episodes.0.events_total', 1)
                ->where('episodes.1.is_completed', false)
                ->where('episodes.1.events_played', 2)
                ->where('episodes.1.events_total', 3)
                ->where('episodes.2.events_played', 0)
                ->where('episodes.2.events_total', 1)
                ->etc()
        );
});

it('drops cold-open banners from the episode description', function (): void {
    $story = storyWithSessions(1);
    $session = $story->adaptation->sessionAdaptations()->where('session_number', 1)->firstOrFail();
    $session->update([
        'entry_point_diagnosis' => [
            'cold_open' => "=== COLD OPEN: The Morning Summons | Session 1 ===\nYour sleep breaks sharply, and your eyes open upon Holmes.",
        ],
    ]);

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('episodes.0.teaser', 'Your sleep breaks sharply, and your eyes open upon Holmes.')
                ->etc()
        );
});

it('uses the story cover when a chapter cover file is missing', function (): void {
    Storage::fake('public');

    $story = storyWithSessions(1);
    $story->addMedia(UploadedFile::fake()->image('story.jpg'))
        ->toMediaCollection('cover');

    $chapter = $story->chapters()->firstOrFail();
    $chapter->addMedia(UploadedFile::fake()->image('chapter.png'))
        ->toMediaCollection('cover');

    $chapterMedia = $chapter->getFirstMedia('cover');
    expect($chapterMedia)->not->toBeNull();
    Storage::disk($chapterMedia->disk)->delete($chapterMedia->getPathRelativeToRoot());

    $storyCover = $story->fresh()->getFirstMediaUrl('cover');

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('episodes.0.cover', $storyCover)
                ->etc()
        );
});

it('keeps a chapter cover when its file is on disk', function (): void {
    Storage::fake('public');

    $story = storyWithSessions(1);
    $chapter = $story->chapters()->firstOrFail();
    $chapter->addMedia(UploadedFile::fake()->image('chapter.png'))
        ->toMediaCollection('cover');

    $chapterCover = $chapter->fresh()->getFirstMediaUrl('cover');

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('episodes.0.cover', $chapterCover)
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
