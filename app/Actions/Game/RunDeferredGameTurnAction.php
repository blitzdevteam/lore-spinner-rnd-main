<?php

declare(strict_types=1);

namespace App\Actions\Game;

use App\Events\GameNarrationStreamed;
use App\Models\Game;
use App\Models\Prompt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The whole lifecycle of a single-player turn once the submitting request has
 * already returned: announce it, stream the prose over the player's private
 * channel, commit, then tell the client the turn landed (or did not).
 *
 * Everything the browser needs to finish the turn is delivered here or is
 * readable from the database afterwards, so a dropped socket, a closed tab, or
 * a proxy timeout on the original request can no longer lose a committed turn.
 */
final class RunDeferredGameTurnAction
{
    /**
     * Deltas are coalesced before broadcasting so a turn does not turn into
     * hundreds of tiny HTTP calls to the websocket server.
     */
    private const int DELTA_FLUSH_CHARS = 48;

    /**
     * @param  array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null  $explicitBranch
     */
    public function handle(
        string $gameId,
        string $sourcePromptId,
        string $storedPromptValue,
        string $playerAction,
        ?array $explicitBranch,
        int $userId,
    ): ?Prompt {
        $processGameTurn = app(ProcessGameTurnAction::class);

        GameNarrationStreamed::dispatch(
            $gameId,
            $sourcePromptId,
            GameNarrationStreamed::PHASE_READY,
            null,
            $storedPromptValue,
        );

        $buffer = '';
        $flush = static function () use ($gameId, $sourcePromptId, &$buffer): void {
            if ($buffer === '') {
                return;
            }

            GameNarrationStreamed::dispatch(
                $gameId,
                $sourcePromptId,
                GameNarrationStreamed::PHASE_DELTA,
                $buffer,
            );
            $buffer = '';
        };

        $responsePrompt = null;

        try {
            $responsePrompt = $processGameTurn->handle(
                gameId: $gameId,
                sourcePromptId: $sourcePromptId,
                storedPromptValue: $storedPromptValue,
                playerAction: $playerAction,
                explicitBranch: $explicitBranch,
                userId: $userId,
                onResponseDelta: static function (string $delta) use (&$buffer, $flush): void {
                    $buffer .= $delta;
                    if (mb_strlen($buffer) >= self::DELTA_FLUSH_CHARS) {
                        $flush();
                    }
                },
            );
        } catch (Throwable $exception) {
            // Already logged and recorded on the error cache key inside the action.
            Log::channel('narration')->debug('game.turn_deferred_failed', [
                'game_id' => $gameId,
                'source_prompt_id' => $sourcePromptId,
                'exception' => $exception::class,
            ]);
        }

        $flush();

        // Re-read rather than trust the return value: an idempotent re-run that found
        // an existing row, or a failure after commit, must still report the truth.
        $responsePrompt ??= Prompt::query()
            ->where('game_id', $gameId)
            ->where('source_prompt_id', $sourcePromptId)
            ->orderByDesc('id')
            ->first();

        if ($responsePrompt !== null) {
            $game = Game::query()->find($gameId);

            GameNarrationStreamed::dispatch(
                $gameId,
                $sourcePromptId,
                GameNarrationStreamed::PHASE_DONE,
                null,
                $storedPromptValue,
                $responsePrompt,
                $game,
            );

            return $responsePrompt;
        }

        GameNarrationStreamed::dispatch(
            $gameId,
            $sourcePromptId,
            GameNarrationStreamed::PHASE_ERROR,
            null,
            null,
            null,
            null,
            (string) (Cache::get($processGameTurn->errorKey($gameId))
                ?? 'Narration hiccuped — please retry this turn.'),
        );

        return null;
    }
}
