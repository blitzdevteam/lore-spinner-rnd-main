<?php

declare(strict_types=1);

namespace App\Actions\Game;

use App\Models\Game;
use App\Models\GameSessionCompletion;
use App\Models\UserActivityDay;
use App\Services\ChaosEngineService;
use App\Services\Runtime\NarrationMetricsStore;
use App\Services\Runtime\NarrationResultNormalizer;
use App\Services\Runtime\PlayableMomentResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Opening narration (begin / next-session) deferred past the HTTP response so
 * Cloudflare never 504s on a silent TTFB while Sonnet is still writing.
 */
final class ProcessGameOpeningAction
{
    public function __construct(
        private readonly ChaosEngineService $engine,
        private readonly PlayableMomentResolver $momentResolver,
        private readonly NarrationResultNormalizer $resultNormalizer,
    ) {}

    public function pendingKey(string $gameId): string
    {
        return "game-opening-pending:{$gameId}";
    }

    public function errorKey(string $gameId): string
    {
        return "game-opening-error:{$gameId}";
    }

    public function handleBegin(string $gameId): void
    {
        $game = Game::query()->find($gameId);
        if ($game === null || $game->prompts()->exists()) {
            return;
        }

        Cache::put($this->pendingKey($gameId), 'begin', 180);
        Cache::forget($this->errorKey($gameId));
        $startedAt = microtime(true);

        try {
            $story = $game->story()->with(['adaptation', 'adaptation.sessionAdaptations'])->first();
            $firstSession = $story->adaptation?->sessionAdaptations?->firstWhere('session_number', 1);

            if ($story->adaptation?->boiler_enabled) {
                if ($firstSession === null) {
                    throw new RuntimeException('This Boiler story has no first session adaptation.');
                }
                $this->momentResolver->initializeForSession($game, $firstSession);
                $game->refresh();
            }

            $sessionContext = $this->engine->loadSessionContext($story, 1, null, game: $game);
            if ($sessionContext === null) {
                throw new RuntimeException('This story has not been adapted yet.');
            }

            $emptyWorldState = $this->engine->emptyWorldState();
            $emptyAlignment = $this->engine->emptyAlignmentScaffold();
            $systemPrompt = $this->engine->renderSystemPrompt(
                sessionContext: $sessionContext,
                worldState: $emptyWorldState,
                alignmentScaffold: $emptyAlignment,
                symbolicMemory: null,
                isSessionStart: true,
                isClimacticPrevious: false,
                isClimacticThisTurn: false,
            );

            $llmStartedAt = microtime(true);
            $result = $this->engine->callAgent(
                model: $game->model,
                systemPrompt: $systemPrompt,
                conversationHistory: [],
                playerAction: null,
                protagonist: (string) ($story->system_prompt['character_name'] ?? 'the protagonist'),
                temperature: $this->engine->gameTemperatureFor($game->model),
                game: $game,
                isOpening: true,
            );
            $llmElapsedMs = (int) round((microtime(true) - $llmStartedAt) * 1000);
            $normalizedChoices = $this->resultNormalizer->choices(
                $result,
                $game->current_playable_moment_id !== null,
            );
            $normalizedChoices = $this->momentResolver->sanitizeSuggestionsForCurrentMoment(
                $game,
                $normalizedChoices,
                $story->adaptation->sessionAdaptations,
            );

            $worldState = $this->engine->mergeStateDelta($emptyWorldState, $result['state_delta']);
            $alignmentScaffold = $this->engine->mergeAlignmentDelta($emptyAlignment, $result['alignment_tally_delta']);
            $symbolicMemory = $this->engine->appendMemory(null, $result['symbolic_memory_update']);
            $branchCommit = $this->engine->resolveBranchCommit(
                $result,
                $game,
                $story->adaptation?->sessionAdaptations,
            );

            $game->update([
                'world_state' => $worldState,
                'alignment_scaffold' => $alignmentScaffold,
                'symbolic_memory' => $symbolicMemory,
                'is_climactic_choice' => (bool) $result['is_climactic_choice'],
                'defining_choice_id' => $branchCommit ? $branchCommit['defining_choice_id'] : null,
                'defining_path_label' => $branchCommit ? $branchCommit['defining_path_label'] : null,
                'defining_choice_line' => $branchCommit ? $branchCommit['defining_choice_line'] : null,
                'current_session_complete' => $game->current_playable_moment_id !== null
                    ? false
                    : $result['session_complete'],
            ]);

            $game->prompts()->create([
                'session_number' => 1,
                'response' => $result['response'],
                'choices' => $normalizedChoices,
            ]);

            GameSessionCompletion::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'story_cycle_number' => $game->current_story_cycle_number,
                    'session_number' => 1,
                ],
                [
                    'story_id' => $story->id,
                    'user_id' => $game->user_id,
                    'started_at' => now(),
                    'completed_at' => null,
                ],
            );

            UserActivityDay::record($game->user_id);
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::channel('narration')->info('game.begin', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'model' => $game->model,
                'session_complete' => $result['session_complete'],
                'response_bytes' => mb_strlen($result['response']),
                'system_prompt_bytes' => strlen($systemPrompt),
                'llm_elapsed_ms' => $llmElapsedMs,
                'elapsed_ms' => $elapsedMs,
                'deferred' => true,
            ]);
            NarrationMetricsStore::record([
                'kind' => 'begin',
                'game_id' => $game->id,
                'story_id' => $story->id,
                'story_slug' => $story->slug,
                'session_number' => 1,
                'model' => $game->model,
                'boiler_active' => $game->current_playable_moment_id !== null,
                'system_prompt_bytes' => strlen($systemPrompt),
                'response_bytes' => strlen($result['response']),
                'llm_elapsed_ms' => $llmElapsedMs,
                'elapsed_ms' => $elapsedMs,
            ]);
            Cache::forget($this->errorKey($gameId));
        } catch (Throwable $e) {
            Log::channel('narration')->error('game.begin_failed', [
                'game_id' => $gameId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'deferred' => true,
            ]);
            Cache::put($this->errorKey($gameId), 'Opening narration hiccuped — please retry.', 60);
            throw $e;
        } finally {
            Cache::forget($this->pendingKey($gameId));
        }
    }

    /**
     * @param  array<string, mixed>  $boilerStateBeforeTransition
     * @param  array<string, mixed>|null  $resolvedConsequencePath
     */
    public function handleNextSession(
        string $gameId,
        int $nextSessionNumber,
        string $openingHandoff,
        ?array $resolvedConsequencePath,
        array $boilerStateBeforeTransition,
    ): void {
        $game = Game::query()->find($gameId);
        if ($game === null) {
            return;
        }

        Cache::put($this->pendingKey($gameId), 'next_session', 180);
        Cache::forget($this->errorKey($gameId));

        $story = $game->story()->with(['adaptation', 'adaptation.sessionAdaptations'])->first();

        try {
            $nextSA = $story->adaptation?->sessionAdaptations
                ?->firstWhere('session_number', $nextSessionNumber);
            $sessionCloseDesign = $nextSA?->session_close_design;

            if ($story->adaptation?->boiler_enabled) {
                if ($nextSA === null) {
                    throw new RuntimeException('The next Boiler session has not been finalized.');
                }
                $this->momentResolver->initializeForSession($game, $nextSA);
                $game->refresh();
            }

            $sessionContext = $this->engine->loadSessionContext(
                story: $story,
                sessionNumber: $nextSessionNumber,
                openingHandoff: $openingHandoff,
                resolvedConsequencePath: $resolvedConsequencePath,
                sessionCloseDesign: $sessionCloseDesign,
                game: $game,
            );

            if ($sessionContext === null) {
                throw new RuntimeException('The next session has not been adapted yet.');
            }

            $carriedWorldState = $game->world_state ?? $this->engine->emptyWorldState();
            $carriedAlignment = $game->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold();
            $carriedMemory = $game->symbolic_memory;

            $systemPrompt = $this->engine->renderSystemPrompt(
                sessionContext: $sessionContext,
                worldState: $carriedWorldState,
                alignmentScaffold: $carriedAlignment,
                symbolicMemory: $carriedMemory,
                isSessionStart: true,
                isClimacticPrevious: false,
                isClimacticThisTurn: false,
            );

            $result = $this->engine->callAgent(
                model: $game->model,
                systemPrompt: $systemPrompt,
                conversationHistory: [],
                playerAction: null,
                protagonist: (string) ($story->system_prompt['character_name'] ?? 'the protagonist'),
                temperature: $this->engine->gameTemperatureFor($game->model),
                worldState: $carriedWorldState,
                game: $game,
                isOpening: true,
            );
            $normalizedChoices = $this->resultNormalizer->choices(
                $result,
                $game->current_playable_moment_id !== null,
            );
            $normalizedChoices = $this->momentResolver->sanitizeSuggestionsForCurrentMoment(
                $game,
                $normalizedChoices,
                $story->adaptation->sessionAdaptations,
            );

            $worldState = $this->engine->mergeStateDelta($carriedWorldState, $result['state_delta']);
            $alignmentScaffold = $this->engine->mergeAlignmentDelta($carriedAlignment, $result['alignment_tally_delta']);
            $symbolicMemory = $this->engine->appendMemory($carriedMemory, $result['symbolic_memory_update']);
            $branchCommit = $this->engine->resolveBranchCommit(
                $result,
                $game,
                $story->adaptation?->sessionAdaptations,
            );

            $currentSessionNumber = $nextSessionNumber - 1;

            $game->update([
                'current_session_number' => $nextSessionNumber,
                'current_session_complete' => $game->current_playable_moment_id !== null
                    ? false
                    : $result['session_complete'],
                'world_state' => $worldState,
                'alignment_scaffold' => $alignmentScaffold,
                'symbolic_memory' => $symbolicMemory,
                'is_climactic_choice' => (bool) $result['is_climactic_choice'],
                'defining_choice_id' => $branchCommit ? $branchCommit['defining_choice_id'] : $game->defining_choice_id,
                'defining_path_label' => $branchCommit ? $branchCommit['defining_path_label'] : $game->defining_path_label,
                'defining_choice_line' => $branchCommit ? $branchCommit['defining_choice_line'] : $game->defining_choice_line,
            ]);

            $game->prompts()->create([
                'session_number' => $nextSessionNumber,
                'response' => $result['response'],
                'choices' => $normalizedChoices,
            ]);

            GameSessionCompletion::where('game_id', $game->id)
                ->where('story_cycle_number', $game->current_story_cycle_number)
                ->where('session_number', $currentSessionNumber)
                ->update(['completed_at' => now()]);

            GameSessionCompletion::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'story_cycle_number' => $game->current_story_cycle_number,
                    'session_number' => $nextSessionNumber,
                ],
                [
                    'story_id' => $story->id,
                    'user_id' => $game->user_id,
                    'started_at' => now(),
                    'completed_at' => null,
                ],
            );

            UserActivityDay::record($game->user_id);
            Log::channel('narration')->info('game.next_session', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'session_number' => $nextSessionNumber,
                'model' => $game->model,
                'session_complete' => $result['session_complete'],
                'deferred' => true,
            ]);
            Cache::forget($this->errorKey($gameId));
        } catch (Throwable $e) {
            if ($story->adaptation?->boiler_enabled && $boilerStateBeforeTransition !== []) {
                $game->update($boilerStateBeforeTransition);
            }

            Log::channel('narration')->error('game.next_session_failed', [
                'game_id' => $gameId,
                'session_number' => $nextSessionNumber,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'deferred' => true,
            ]);
            Cache::put($this->errorKey($gameId), 'Could not open the next session — please try again.', 60);
            throw $e;
        } finally {
            Cache::forget($this->pendingKey($gameId));
        }
    }
}
