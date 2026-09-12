<?php

declare(strict_types=1);

use App\Actions\Game\ProcessGameTurnAction;
use App\Actions\Game\RunDeferredGameTurnAction;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Game;
use App\Models\Prompt;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * Stands in for the deferred narrator so the request path can be exercised
 * without a model call. Resolved by class name through the container, which
 * is how the controller's after-response closure reaches it.
 */
final class FakeDeferredTurnRunner
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public function handle(
        string $gameId,
        string $sourcePromptId,
        string $storedPromptValue,
        string $playerAction,
        ?array $explicitBranch,
        int $userId,
    ): ?Prompt {
        $this->calls[] = compact('gameId', 'sourcePromptId', 'storedPromptValue', 'playerAction', 'explicitBranch', 'userId');

        return null;
    }
}

beforeEach(function (): void {
    $this->withoutVite();

    $this->runner = new FakeDeferredTurnRunner;
    $this->app->instance(RunDeferredGameTurnAction::class, $this->runner);

    $this->user = User::factory()->withCompleteProfile()->create(['is_active' => true]);
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $this->game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $this->user->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $this->game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>The game begins.</p>',
        'choices' => [],
        'prompt' => 'I look around.',
    ]);
    $this->source = $this->game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>A door stands ajar.</p>',
        'choices' => ['Open it', 'Knock', 'Walk away'],
    ]);
});

function submitTurnJson(User $user, Game $game, array $overrides = [])
{
    return test()->actingAs($user, 'user')->postJson(
        route('user.games.prompt.store', $game),
        array_merge([
            'prompt' => 'I push the door open slowly.',
            'prompt_id' => test()->source->id,
        ], $overrides),
    );
}

it('accepts a move immediately and runs the narrator after the response', function (): void {
    $response = submitTurnJson($this->user, $this->game);

    $response->assertStatus(202)
        ->assertJson([
            'accepted' => true,
            'game_id' => (string) $this->game->id,
            'source_prompt_id' => (string) $this->source->id,
            'source_prompt_value' => 'I push the door open slowly.',
            'channel' => 'game.'.$this->game->id,
        ]);

    expect($this->runner->calls)->toHaveCount(1)
        ->and($this->runner->calls[0])->toMatchArray([
            'gameId' => (string) $this->game->id,
            'sourcePromptId' => (string) $this->source->id,
            'storedPromptValue' => 'I push the door open slowly.',
            'playerAction' => 'I push the door open slowly.',
            'explicitBranch' => null,
            'userId' => $this->user->id,
        ]);

    // The pending flag is visible before the response leaves so the very first
    // status poll already knows a turn is in flight.
    expect(Cache::get(app(ProcessGameTurnAction::class)->pendingKey((string) $this->game->id)))
        ->toBe((string) $this->source->id);
});

it('translates a continue move for the narrator while storing the marker', function (): void {
    submitTurnJson($this->user, $this->game, ['prompt' => '__continue__'])
        ->assertStatus(202)
        ->assertJsonPath('source_prompt_value', '__continue__');

    expect($this->runner->calls)->toHaveCount(1)
        ->and($this->runner->calls[0]['storedPromptValue'])->toBe('__continue__')
        ->and($this->runner->calls[0]['playerAction'])->toBe('Continue the story forward.');
});

it('returns the committed turn when the same move is submitted again', function (): void {
    $this->source->update(['prompt' => 'I push the door open slowly.']);
    $committed = $this->game->prompts()->create([
        'source_prompt_id' => $this->source->id,
        'session_number' => 1,
        'response' => '<p>The hinge gives.</p>',
        'choices' => ['Step through', 'Wait', 'Call out'],
    ]);
    $this->game->update(['current_session_complete' => true]);

    submitTurnJson($this->user, $this->game)
        ->assertStatus(409)
        ->assertJsonPath('accepted', false)
        ->assertJsonPath('reason', 'already_answered')
        ->assertJsonPath('source_prompt_id', (string) $this->source->id)
        ->assertJsonPath('source_prompt_value', 'I push the door open slowly.')
        ->assertJsonPath('prompt.id', (string) $committed->id)
        ->assertJsonPath('prompt.response', '<p>The hinge gives.</p>')
        ->assertJsonPath('game.current_session_complete', true);

    expect($this->runner->calls)->toBeEmpty()
        ->and(Prompt::query()->where('source_prompt_id', $this->source->id)->count())->toBe(1);
});

it('rejects a move against a turn that is no longer current without narrating', function (): void {
    $this->game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>A newer turn.</p>',
        'choices' => [],
    ]);

    submitTurnJson($this->user, $this->game)
        ->assertStatus(409)
        ->assertJsonPath('reason', 'out_of_turn')
        ->assertJsonMissingPath('prompt');

    expect($this->runner->calls)->toBeEmpty();
});

it('tells a duplicate submit that the move is already being narrated', function (): void {
    Cache::lock("game-turn:{$this->game->id}:{$this->source->id}", 180)->get();

    submitTurnJson($this->user, $this->game)
        ->assertStatus(409)
        ->assertJsonPath('reason', 'in_progress');

    expect($this->runner->calls)->toBeEmpty();
});

it('still answers a plain form post with a redirect', function (): void {
    $this->actingAs($this->user, 'user')
        ->from(route('user.games.show', $this->game))
        ->post(route('user.games.prompt.store', $this->game), [
            'prompt' => 'I push the door open slowly.',
            'prompt_id' => $this->source->id,
        ])
        ->assertRedirect(route('user.games.show', $this->game));

    expect($this->runner->calls)->toHaveCount(1);
});
