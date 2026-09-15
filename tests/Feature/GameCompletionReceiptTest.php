<?php

declare(strict_types=1);

use App\Http\Middleware\Guard\EnsureActiveSubscription;
use App\Http\Resources\GameResource;
use App\Models\CompletionReceipt;
use App\Models\GameCompletion;
use App\Models\GameSessionCompletion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CompletionReceiptFixtures;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->withoutMiddleware(EnsureActiveSubscription::class);
});

it('includes a completion receipt on the game show page only when the session is complete', function () {
    $complete = CompletionReceiptFixtures::game();
    $incomplete = CompletionReceiptFixtures::game(gameAttributes: [
        'user_id' => $complete->user_id,
        'story_id' => $complete->story_id,
        'current_session_complete' => false,
        'defining_choice_line' => null,
    ]);

    $this->actingAs($complete->user)
        ->get(route('user.games.show', $complete))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Games/Show')
            ->where('game.completion_receipt.mode', 'session')
            ->where('game.completion_receipt.final_line', 'The light waits; so, now, does Holmes.')
            ->where('game.completion_receipt.commitment', 'You told Holmes you would go — but only after testing the signal once more in your mind.')
            ->missing('game.world_state')
            ->missing('game.alignment_scaffold')
            ->missing('game.moment_footnote')
            ->etc());

    $this->actingAs($incomplete->user)
        ->get(route('user.games.show', $incomplete))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Games/Show')
            ->where('game.completion_receipt', null)
            ->etc());
});

it('omits the receipt from GameResource when the session is still in progress', function () {
    $game = CompletionReceiptFixtures::loadForShow(CompletionReceiptFixtures::game(
        gameAttributes: ['current_session_complete' => false],
    ));

    $payload = GameResource::make($game)->resolve();

    expect($payload['completion_receipt'])->toBeNull()
        ->and($payload)->not->toHaveKey('world_state');
});

it('records story completion analytics when the last session advances past the arc', function () {
    $game = CompletionReceiptFixtures::game(gameAttributes: [
        'current_session_number' => 2,
        'current_session_complete' => true,
    ]);

    GameSessionCompletion::query()->create([
        'game_id' => $game->id,
        'story_id' => $game->story_id,
        'user_id' => $game->user_id,
        'story_cycle_number' => 1,
        'session_number' => 2,
        'started_at' => now()->subHour(),
        'completed_at' => null,
    ]);

    $this->actingAs($game->user)
        ->post(route('user.games.next-session', $game))
        ->assertRedirect(route('user.games.show', $game));

    $storyReceipt = CompletionReceipt::query()
        ->where('game_id', $game->id)
        ->where('mode', 'story')
        ->first();

    expect($game->fresh()->completed_at)->not->toBeNull()
        ->and(GameCompletion::query()->where('game_id', $game->id)->where('story_cycle_number', 1)->exists())->toBeTrue()
        ->and(GameSessionCompletion::query()->where('game_id', $game->id)->where('session_number', 2)->value('completed_at'))
        ->not->toBeNull()
        ->and($storyReceipt)->not->toBeNull()
        ->and($storyReceipt->receipt['final_line'])->toBe('The case is solved; its meaning, and its measure, remain in your hands.');
});
