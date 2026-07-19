<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\Prompt\StorePromptRequest;
use App\Models\Game;
use App\Models\Prompt;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Services\ChaosEngineService;
use App\Services\Runtime\NarrationResultNormalizer;
use App\Services\Runtime\PlayableMomentResolver;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class PromptController extends Controller
{
    private const CONTINUE_MARKER = '__continue__';

    public function __construct(
        private readonly ChaosEngineService $engine,
        private readonly PlayableMomentResolver $momentResolver,
        private readonly NarrationResultNormalizer $resultNormalizer,
    ) {}

    public function store(
        #[CurrentUser] User $user,
        Game $game,
        StorePromptRequest $request,
    ): RedirectResponse {
        $promptText = $request->string('prompt')->toString();
        $sourcePromptId = $request->string('prompt_id')->toString();
        $turnLock = Cache::lock("game-turn:{$game->id}:{$sourcePromptId}", 120);

        if (! $turnLock->get()) {
            return back()->with('error', 'That turn is already being processed.');
        }

        $playerAction = $promptText;

        try {
            $sourcePrompt = $game->prompts()
                ->whereKey($sourcePromptId)
                ->whereNull('prompt')
                ->first();

            if ($sourcePrompt === null || $game->prompts()->latest()->value('id') !== $sourcePrompt->id) {
                return back()->with('error', 'That prompt is no longer awaiting an answer.');
            }

            [$storedPromptValue, $playerAction, $explicitBranch] = $this->validateSubmittedAction(
                $request,
                $game,
                $sourcePrompt,
            );

            $story = $game->story()->with(['adaptation', 'adaptation.sessionAdaptations'])->first();
            $resolvedConsequencePath = null;
            if (! empty($game->defining_choice_id) && ! empty($game->defining_path_label)) {
                $priorSessionNumber = $this->engine->extractSessionNumber($game->defining_choice_id);
                $priorSession = $story->adaptation?->sessionAdaptations
                    ?->firstWhere('session_number', $priorSessionNumber);
                if ($priorSession && ! empty($priorSession->choice_consequence_map)) {
                    $resolvedConsequencePath = $this->engine->resolveConsequencePath(
                        $game->defining_choice_id,
                        $game->defining_path_label,
                        $priorSession->choice_consequence_map,
                    );
                }
            }

            $currentSession = $story->adaptation?->sessionAdaptations
                ?->firstWhere('session_number', (int) ($game->current_session_number ?? 1));
            $sessionContext = $this->engine->loadSessionContext(
                story: $story,
                sessionNumber: (int) ($game->current_session_number ?? 1),
                openingHandoff: null,
                resolvedConsequencePath: $resolvedConsequencePath,
                sessionCloseDesign: $currentSession?->session_close_design,
                game: $game,
            );

            if ($sessionContext === null) {
                throw new RuntimeException('This story session has not been adapted yet.');
            }

            $history = $this->buildConversationHistory($game, $story);
            $worldState = $game->world_state ?? $this->engine->emptyWorldState();
            $alignment = $game->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold();
            $isClimactic = (bool) $game->is_climactic_choice;
            $systemPrompt = $this->engine->renderSystemPrompt(
                sessionContext: $sessionContext,
                worldState: $worldState,
                alignmentScaffold: $alignment,
                symbolicMemory: $game->symbolic_memory,
                isSessionStart: false,
                isClimacticPrevious: $isClimactic,
                isClimacticThisTurn: $isClimactic,
            );

            if ($explicitBranch !== null) {
                $systemPrompt .= "\n\nVALIDATED PLAYER SELECTION (authoritative): "
                    . $explicitBranch['defining_choice_id'] . ' path ' . $explicitBranch['defining_path_label']
                    . '. Resolve this selected authored path; do not infer a different one.';
            }

            $boilerActive = $game->current_playable_moment_id !== null;
            $historyWindow = $boilerActive
                ? array_slice($history, -max(2, ($game->playable_moment_turn_count + 1) * 2))
                : array_slice($history, -12);
            $result = $this->engine->callAgent(
                model: $game->model,
                systemPrompt: $systemPrompt,
                conversationHistory: $historyWindow,
                playerAction: $playerAction,
                protagonist: (string) ($story->system_prompt['character_name'] ?? 'the protagonist'),
                temperature: $this->engine->gameTemperatureFor($game->model),
                worldState: $worldState,
                game: $game,
                isOpening: false,
            );
            $normalizedChoices = $this->resultNormalizer->choices($result, $boilerActive);

            if ($boilerActive) {
                $this->momentResolver->applyPlayerTurn(
                    $game,
                    $sourcePrompt,
                    $storedPromptValue,
                    $result,
                    $normalizedChoices,
                    $story->adaptation->sessionAdaptations,
                    $explicitBranch,
                );
            } else {
                $this->commitLegacyTurn(
                    $game,
                    $sourcePrompt,
                    $storedPromptValue,
                    $result,
                    $normalizedChoices,
                    $story->adaptation?->sessionAdaptations,
                );
            }

            UserActivityDay::record($user->id);
            Log::channel('narration')->info('game.turn', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'session_number' => $game->current_session_number,
                'model' => $game->model,
                'source_prompt_id' => $sourcePrompt->id,
                'boiler_active' => $boilerActive,
                'player_input' => mb_substr($playerAction, 0, 120),
                'response_bytes' => strlen($result['response']),
            ]);

            return back();
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
            $turnLock->release();
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

        $momentChoiceIds = (array) data_get($game->currentPlayableMoment?->scene_card, 'choice_ids', []);
        if (! in_array($storedChoiceId, $momentChoiceIds, true)) {
            throw new RuntimeException('The selected canonical choice is not bound to the active moment.');
        }

        $choiceSession = $this->engine->extractSessionNumber($storedChoiceId);
        $session = $game->story?->adaptation?->sessionAdaptations?->firstWhere('session_number', $choiceSession);
        $choice = collect((array) data_get($session, 'session_choice_design.branching_choices', []))
            ->firstWhere('choice_id', $storedChoiceId);
        $option = collect((array) ($choice['options'] ?? []))
            ->first(fn ($option): bool => strtoupper((string) ($option['label'] ?? '')) === $storedPathLabel);

        if (! is_array($option)) {
            throw new RuntimeException('The selected canonical path does not exist in authored Choice Design.');
        }

        return [
            $submittedText,
            $submittedText,
            [
                'defining_choice_id' => $storedChoiceId,
                'defining_path_label' => $storedPathLabel,
                'defining_choice_line' => ($option['defining_line'] ?? '') !== ''
                    ? (string) $option['defining_line']
                    : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, string|array<string, mixed>>  $normalizedChoices
     */
    private function commitLegacyTurn(
        Game $game,
        Prompt $sourcePrompt,
        string $storedPromptValue,
        array $result,
        array $normalizedChoices,
        ?object $sessionAdaptations,
    ): void {
        DB::transaction(function () use (
            $game,
            $sourcePrompt,
            $storedPromptValue,
            $result,
            $normalizedChoices,
            $sessionAdaptations,
        ): void {
            $lockedGame = Game::query()->whereKey($game->id)->lockForUpdate()->firstOrFail();
            $lockedSource = Prompt::query()
                ->whereKey($sourcePrompt->id)
                ->where('game_id', $lockedGame->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                Prompt::query()
                    ->where('game_id', $lockedGame->id)
                    ->where('source_prompt_id', $lockedSource->id)
                    ->exists()
            ) {
                return;
            }

            if ($lockedSource->prompt !== null) {
                throw new RuntimeException('The source prompt has already been answered.');
            }

            $worldState = $this->engine->mergeStateDelta(
                $lockedGame->world_state ?? $this->engine->emptyWorldState(),
                (array) $result['state_delta'],
            );
            $alignment = $this->engine->mergeAlignmentDelta(
                $lockedGame->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold(),
                (array) $result['alignment_tally_delta'],
            );
            $memory = $this->engine->appendMemory(
                $lockedGame->symbolic_memory,
                (string) $result['symbolic_memory_update'],
            );
            $branchCommit = $this->engine->resolveBranchCommit(
                $result,
                $lockedGame,
                $sessionAdaptations,
            );

            $lockedSource->update(['prompt' => $storedPromptValue]);
            $lockedGame->update([
                'world_state' => $worldState,
                'alignment_scaffold' => $alignment,
                'symbolic_memory' => $memory,
                'is_climactic_choice' => (bool) $result['is_climactic_choice'],
                'defining_choice_id' => $branchCommit ? $branchCommit['defining_choice_id'] : $lockedGame->defining_choice_id,
                'defining_path_label' => $branchCommit ? $branchCommit['defining_path_label'] : $lockedGame->defining_path_label,
                'defining_choice_line' => $branchCommit ? $branchCommit['defining_choice_line'] : $lockedGame->defining_choice_line,
                'current_session_complete' => (bool) $result['session_complete'],
            ]);
            $lockedGame->prompts()->create([
                'source_prompt_id' => $lockedSource->id,
                'session_number' => $lockedGame->current_session_number,
                'response' => $result['response'],
                'choices' => $normalizedChoices,
            ]);
        });
    }

    /**
     * Build a conversation history array from persisted prompts for use as
     * Chaos engine context. Kept as [{role, text}] pairs matching the
     * turn-prompt blade template expectations.
     *
     * @return array<int, array{role:string, text:string}>
     */
    private function buildConversationHistory(Game $game, mixed $story): array
    {
        $history = [];
        $protagonist = (string) ($story->system_prompt['character_name'] ?? 'the protagonist');

        $prompts = $game->prompts()
            ->where('session_number', $game->current_session_number)
            ->oldest()
            ->get();

        foreach ($prompts as $p) {
            if ($p->response) {
                $history[] = [
                    'role' => 'narrator',
                    'text' => $this->engine->stripHtml($p->response),
                ];
            }
            if ($p->prompt && $p->prompt !== self::CONTINUE_MARKER) {
                $history[] = ['role' => 'player', 'text' => $p->prompt, 'protagonist' => $protagonist];
            } elseif ($p->prompt === self::CONTINUE_MARKER) {
                $history[] = ['role' => 'player', 'text' => 'Continue the story forward.', 'protagonist' => $protagonist];
            }
        }

        return $history;
    }
}
