<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Actions\Game\ProcessGameTurnAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\Prompt\StorePromptRequest;
use App\Models\Game;
use App\Models\Prompt;
use App\Models\User;
use App\Services\ChaosEngineService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class PromptController extends Controller
{
    private const CONTINUE_MARKER = '__continue__';

    public function __construct(
        private readonly ChaosEngineService $engine,
        private readonly ProcessGameTurnAction $processGameTurn,
    ) {}

    public function store(
        #[CurrentUser] User $user,
        Game $game,
        StorePromptRequest $request,
    ): RedirectResponse {
        $promptText = $request->string('prompt')->toString();
        $sourcePromptId = $request->string('prompt_id')->toString();
        $lockName = "game-turn:{$game->id}:{$sourcePromptId}";
        $turnLock = Cache::lock($lockName, 180);

        if (! $turnLock->get()) {
            return to_route('user.games.show', $game);
        }

        $playerAction = $promptText;
        $deferred = false;

        try {
            $sourcePrompt = $game->prompts()
                ->whereKey($sourcePromptId)
                ->whereNull('prompt')
                ->first();

            if ($sourcePrompt === null || $game->prompts()->latest()->value('id') !== $sourcePrompt->id) {
                return to_route('user.games.show', $game);
            }

            [$storedPromptValue, $playerAction, $explicitBranch] = $this->validateSubmittedAction(
                $request,
                $game,
                $sourcePrompt,
            );

            $lockOwner = $turnLock->owner();
            $gameId = $game->id;
            $userId = $user->id;

            // Return the Inertia response immediately. Run Sonnet after the
            // response is sent so proxies never 504 on a silent TTFB while the
            // model is still generating — that was the "instant 504, refresh
            // shows the turn" ghost.
            dispatch(function () use (
                $lockName,
                $lockOwner,
                $gameId,
                $sourcePromptId,
                $storedPromptValue,
                $playerAction,
                $explicitBranch,
                $userId,
            ): void {
                $lock = Cache::restoreLock($lockName, $lockOwner);

                try {
                    app(ProcessGameTurnAction::class)->handle(
                        gameId: $gameId,
                        sourcePromptId: $sourcePromptId,
                        storedPromptValue: $storedPromptValue,
                        playerAction: $playerAction,
                        explicitBranch: $explicitBranch,
                        userId: $userId,
                    );
                } catch (Throwable) {
                    // Logged inside the action; frontend polls the error cache key.
                } finally {
                    $lock->release();
                }
            })->afterResponse();

            $deferred = true;
            Cache::put($this->processGameTurn->pendingKey($game->id), $sourcePromptId, 180);
            Cache::forget($this->processGameTurn->errorKey($game->id));

            return back()->with('narrating', true);
        } catch (Throwable $e) {
            Log::channel('narration')->error('game.turn_failed', [
                'game_id' => $game->id,
                'model' => $game->model,
                'source_prompt_id' => $sourcePromptId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'player_input' => mb_substr($playerAction, 0, 120),
            ]);

            return back()->with('error', 'Narration hiccuped — please retry this turn.');
        } finally {
            if (! $deferred) {
                $turnLock->release();
            }
        }
    }

    /**
     * @return array{0:string,1:string,2:array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null}
     */
    private function validateSubmittedAction(
        StorePromptRequest $request,
        Game $game,
        Prompt $sourcePrompt,
    ): array {
        $game->loadMissing(['story.adaptation.sessionAdaptations', 'currentPlayableMoment']);

        $submittedText = $request->string('prompt')->toString();
        $suggestionIndex = $request->input('suggestion_index');

        if ($suggestionIndex === null) {
            $isLegacyContinue = $game->current_playable_moment_id === null
                && $submittedText === self::CONTINUE_MARKER;

            return [
                $isLegacyContinue ? self::CONTINUE_MARKER : $submittedText,
                $isLegacyContinue ? 'Continue the story forward.' : $submittedText,
                null,
            ];
        }

        $choices = (array) ($sourcePrompt->choices ?? []);
        $storedChoice = $choices[(int) $suggestionIndex] ?? null;

        if ($storedChoice === null) {
            throw new RuntimeException('The selected suggestion no longer exists on this prompt.');
        }

        if (is_string($storedChoice)) {
            if ($storedChoice !== $submittedText) {
                throw new RuntimeException('The submitted legacy choice does not match the stored prompt.');
            }

            return [$submittedText, $submittedText, null];
        }

        if (! is_array($storedChoice) || ($storedChoice['text'] ?? null) !== $submittedText) {
            throw new RuntimeException('The submitted suggestion does not match the stored prompt.');
        }

        $storedChoiceId = $storedChoice['canonical_choice_id'] ?? null;
        $storedPathLabel = $storedChoice['canonical_path_label'] ?? null;
        if (
            $request->input('canonical_choice_id') !== $storedChoiceId
            || $request->input('canonical_path_label') !== $storedPathLabel
        ) {
            throw new RuntimeException('The submitted canonical identity does not match the stored suggestion.');
        }

        $isContinue = $storedChoiceId === null && strcasecmp($submittedText, 'Continue') === 0;
        if ($storedChoiceId === null) {
            return [
                $isContinue ? self::CONTINUE_MARKER : $submittedText,
                $isContinue ? 'Continue the story forward.' : $submittedText,
                null,
            ];
        }

        $choiceId = (string) $storedChoiceId;
        $pathLabel = strtoupper((string) ($storedPathLabel ?? ''));
        $momentChoiceIds = (array) data_get($game->currentPlayableMoment?->scene_card, 'choice_ids', []);

        if (! in_array($choiceId, $momentChoiceIds, true)) {
            return $this->demoteSubmittedMapping(
                $submittedText,
                $game->id,
                $choiceId,
                $pathLabel,
                'not_bound_to_moment',
            );
        }

        $choiceSession = $this->engine->extractSessionNumber($choiceId);
        $session = $game->story?->adaptation?->sessionAdaptations?->firstWhere('session_number', $choiceSession);
        $design = (array) ($session?->session_choice_design ?? []);

        $branchingChoice = collect((array) ($design['branching_choices'] ?? []))
            ->firstWhere('choice_id', $choiceId);
        if (is_array($branchingChoice)) {
            $option = collect((array) ($branchingChoice['options'] ?? []))
                ->first(fn ($option): bool => strtoupper((string) ($option['label'] ?? '')) === $pathLabel);

            if (! is_array($option)) {
                return $this->demoteSubmittedMapping(
                    $submittedText,
                    $game->id,
                    $choiceId,
                    $pathLabel,
                    'branching_path_not_in_authored_options',
                );
            }

            return [
                $submittedText,
                $submittedText,
                [
                    'defining_choice_id' => $choiceId,
                    'defining_path_label' => $pathLabel,
                    'defining_choice_line' => ($option['defining_line'] ?? '') !== ''
                        ? (string) $option['defining_line']
                        : null,
                ],
            ];
        }

        $emotionalChoice = collect((array) ($design['emotional_choices'] ?? []))
            ->firstWhere('choice_id', $choiceId);
        if (is_array($emotionalChoice)) {
            $option = collect((array) ($emotionalChoice['options'] ?? []))
                ->first(fn ($option): bool => strtoupper((string) ($option['label'] ?? '')) === $pathLabel);

            if (! is_array($option)) {
                return $this->demoteSubmittedMapping(
                    $submittedText,
                    $game->id,
                    $choiceId,
                    $pathLabel,
                    'emotional_path_not_in_authored_options',
                );
            }

            Log::channel('narration')->info('boiler.submitted_emotional_mapping', [
                'game_id' => $game->id,
                'canonical_choice_id' => $choiceId,
                'canonical_path_label' => $pathLabel,
            ]);

            return [$submittedText, $submittedText, null];
        }

        return $this->demoteSubmittedMapping(
            $submittedText,
            $game->id,
            $choiceId,
            $pathLabel,
            'choice_not_in_design',
        );
    }

    /**
     * @return array{0:string,1:string,2:null}
     */
    private function demoteSubmittedMapping(
        string $submittedText,
        string $gameId,
        string $choiceId,
        string $pathLabel,
        string $reason,
    ): array {
        Log::channel('narration')->warning('boiler.submitted_mapping_demoted', [
            'game_id' => $gameId,
            'canonical_choice_id' => $choiceId,
            'canonical_path_label' => $pathLabel,
            'reason' => $reason,
            'player_input' => mb_substr($submittedText, 0, 120),
        ]);

        return [$submittedText, $submittedText, null];
    }
}
