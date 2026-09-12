<?php

declare(strict_types=1);

use App\Actions\Game\ProcessGameTurnAction;
use App\Actions\Game\RunDeferredGameTurnAction;
use App\Enums\Story\StoryStatusEnum;
use App\Events\GameNarrationStreamed;
use App\Models\Game;
use App\Models\Prompt;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Stands in for the narrator. The runner resolves the processor through the
 * container at call time, so a plain object with the same surface is enough.
 */
final class FakeTurnProcessor
{
    /** @param Closure(callable(string): void): ?Prompt $script */
    public function __construct(private readonly Closure $script) {}

    public function pendingKey(string $gameId): string
    {
        return "game-turn-pending:{$gameId}";
    }

    public function errorKey(string $gameId): string
    {
        return "game-turn-error:{$gameId}";
    }

    public function handle(
        string $gameId,
        string $sourcePromptId,
        string $storedPromptValue,
        string $playerAction,
        ?array $explicitBranch,
        int $userId,
        ?callable $onResponseDelta = null,
    ): ?Prompt {
        return ($this->script)($onResponseDelta ?? static fn (string $delta): null => null);
    }
}

beforeEach(function (): void {
    Event::fake([GameNarrationStreamed::class]);

    $user = User::factory()->withCompleteProfile()->create(['is_active' => true]);
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $this->game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $this->source = $this->game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>A door stands ajar.</p>',
        'choices' => ['Open it', 'Knock', 'Walk away'],
    ]);
});

function runDeferredTurn(Game $game, Prompt $source, Closure $script): ?Prompt
{
    app()->instance(ProcessGameTurnAction::class, new FakeTurnProcessor($script));

    return app(RunDeferredGameTurnAction::class)->handle(
        gameId: (string) $game->id,
        sourcePromptId: (string) $source->id,
        storedPromptValue: 'I push the door open slowly.',
        playerAction: 'I push the door open slowly.',
        explicitBranch: null,
        userId: (int) $game->user_id,
    );
}

/** @return array<int, GameNarrationStreamed> */
function narrationEventsFor(string $sourcePromptId): array
{
    $events = [];
    Event::assertDispatched(GameNarrationStreamed::class, function (GameNarrationStreamed $event) use (&$events, $sourcePromptId): bool {
        if ($event->sourcePromptId === $sourcePromptId) {
            $events[] = $event;
        }

        return true;
    });

    return $events;
}

it('announces, relays coalesced prose, and reports the committed turn', function (): void {
    $game = $this->game;
    $source = $this->source;

    $result = runDeferredTurn($game, $source, function (callable $onDelta) use ($game, $source): Prompt {
        // Short pieces accumulate; the flush threshold turns them into a few sends.
        foreach (['The ', 'hinge ', 'gives, ', 'and the corridor beyond is dark and cold. ', 'You step.'] as $piece) {
            $onDelta($piece);
        }

        $source->update(['prompt' => 'I push the door open slowly.']);
        $game->update(['current_session_complete' => true]);

        return $game->prompts()->create([
            'source_prompt_id' => $source->id,
            'session_number' => 1,
            'response' => '<p>The hinge gives, and the corridor beyond is dark and cold. You step.</p>',
            'choices' => ['Step through', 'Wait', 'Call out'],
        ]);
    });

    expect($result)->toBeInstanceOf(Prompt::class);

    $events = narrationEventsFor((string) $source->id);
    $phases = array_map(static fn (GameNarrationStreamed $event): string => $event->phase, $events);
    $deltaCount = array_count_values($phases)['delta'] ?? 0;

    expect($phases[0])->toBe('ready')
        ->and(end($phases))->toBe('done')
        ->and($deltaCount)->toBeGreaterThanOrEqual(1)
        ->and($deltaCount)->toBeLessThan(5);

    $streamed = implode('', array_map(
        static fn (GameNarrationStreamed $event): string => (string) $event->text,
        array_filter($events, static fn (GameNarrationStreamed $event): bool => $event->phase === 'delta'),
    ));
    expect($streamed)->toBe('The hinge gives, and the corridor beyond is dark and cold. You step.');

    $done = $events[array_key_last($events)];
    expect($done->sourcePromptValue)->toBe('I push the door open slowly.')
        ->and($done->prompt?->id)->toBe($result->id)
        ->and($done->game?->current_session_complete)->toBeTrue();
});

it('reports an error when the narrator fails before committing', function (): void {
    $game = $this->game;

    $result = runDeferredTurn($game, $this->source, function () use ($game): never {
        Cache::put("game-turn-error:{$game->id}", 'Narration hiccuped — please retry this turn.', 60);

        throw new RuntimeException('provider timeout');
    });

    expect($result)->toBeNull();

    $events = narrationEventsFor((string) $this->source->id);
    $last = $events[array_key_last($events)];

    expect($last->phase)->toBe('error')
        ->and($last->message)->toBe('Narration hiccuped — please retry this turn.');
});

it('still reports done when the row exists even though the action threw after committing', function (): void {
    $game = $this->game;
    $source = $this->source;

    $result = runDeferredTurn($game, $source, function () use ($game, $source): never {
        $source->update(['prompt' => 'I push the door open slowly.']);
        $game->prompts()->create([
            'source_prompt_id' => $source->id,
            'session_number' => 1,
            'response' => '<p>Committed.</p>',
            'choices' => [],
        ]);

        throw new RuntimeException('audio render failed after commit');
    });

    expect($result?->response)->toBe('<p>Committed.</p>');

    $events = narrationEventsFor((string) $source->id);
    expect($events[array_key_last($events)]->phase)->toBe('done');
});
