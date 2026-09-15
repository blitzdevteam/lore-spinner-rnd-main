<?php

declare(strict_types=1);

use App\Enums\CompletionReceiptMode;
use App\Models\CompletionReceipt;
use App\Models\User;
use App\Services\Runtime\AchievementLibrary;
use App\Services\Runtime\CompletionReceiptRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CompletionReceiptFixtures;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('redirects guests away from achievements', function () {
    $this->get(route('user.achievements.index'))
        ->assertRedirect(route('user.authentication.login.create'));
});

it('returns an empty library when the player has no completion cards', function () {
    $user = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->getJson(route('user.achievements.index'))
        ->assertOk()
        ->assertJson(['stories' => []]);
});

it('groups episode and story cards by story for the signed-in player', function () {
    $sessionGame = CompletionReceiptFixtures::game();
    $sessionGame->story->update(['title' => 'Alpha Inquiry']);

    $storyGame = CompletionReceiptFixtures::game(gameAttributes: [
        'user_id' => $sessionGame->user_id,
        'current_session_number' => 2,
        'defining_choice_line' => 'I chose how much of it the world gets to know.',
    ]);
    $storyGame->story->update(['title' => 'Zeta Vigil']);

    $recorder = app(CompletionReceiptRecorder::class);
    $recorder->snapshot($sessionGame);
    $recorder->snapshot($storyGame);

    CompletionReceipt::factory()->create([
        'user_id' => User::factory()->withCompleteProfile()->create(['is_active' => true])->id,
        'receipt' => [
            'mode' => 'session',
            'story_title' => 'Hidden from another player',
            'session_number' => 1,
            'final_line' => 'This must not appear.',
        ],
    ]);

    $this->actingAs($sessionGame->user)
        ->getJson(route('user.achievements.index'))
        ->assertOk()
        ->assertJsonCount(2, 'stories')
        ->assertJsonPath('stories.0.episode_count', 1)
        ->assertJsonPath('stories.0.story_complete', false)
        ->assertJsonPath('stories.0.episodes.0.label', 'Episode 1')
        ->assertJsonPath('stories.0.episodes.0.receipt.final_line', 'The light waits; so, now, does Holmes.')
        ->assertJsonPath('stories.1.story_complete', true)
        ->assertJsonPath('stories.1.story_card.label', 'Story complete')
        ->assertJsonPath('stories.1.story_card.receipt.final_line', 'The case is solved; its meaning, and its measure, remain in your hands.')
        ->assertJsonMissing(['final_line' => 'This must not appear.']);
});

it('backfills a live complete session into the library', function () {
    $game = CompletionReceiptFixtures::game();

    expect(CompletionReceipt::query()->where('game_id', $game->id)->exists())->toBeFalse();

    $payload = app(AchievementLibrary::class)->forUser($game->user);

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['episodes'][0]['receipt']['final_line'])->toBe('The light waits; so, now, does Holmes.')
        ->and(CompletionReceipt::query()->where('game_id', $game->id)->where('mode', CompletionReceiptMode::Session)->exists())->toBeTrue();
});
