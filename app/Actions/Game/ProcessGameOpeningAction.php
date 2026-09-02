<?php

declare(strict_types=1);

namespace App\Actions\Game;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\Game;
use App\Models\GameSessionCompletion;
use App\Models\PlayableMoment;
use App\Models\PlayerTokenUsage;
use App\Models\Prompt;
use App\Models\UserActivityDay;
use App\Services\ChaosEngineService;
use App\Services\NarrationAudioService;
use App\Services\Runtime\NarrationMetricsStore;
use App\Services\Runtime\NarrationResultNormalizer;
use App\Services\Runtime\PlayableMomentResolver;
use App\Services\Runtime\PlayerTokenUsageRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        private readonly NarrationAudioService $audio,
        private readonly PlayerTokenUsageRecorder $tokenUsage,
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

        $this->ensureConfiguredNarrationModel($game);

        Cache::put($this->pendingKey($gameId), 'begin', 180);
        Cache::forget($this->errorKey($gameId));
        $narration = null;
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

            $usageMoment = $game->current_playable_moment_id === null
                ? null
                : PlayableMoment::query()->findOrFail($game->current_playable_moment_id);

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

            $usageOccurredAt = now()->toImmutable()->utc();
            $llmStartedAt = microtime(true);
            $result = $this->engine->callAgent(
                model: $game->model,
                systemPrompt: $systemPrompt,
                conversationHistory: [],
                playerAction: null,
                protagonist: ChaosStoryConfig::protagonistFor(
                    $story->slug,
                    $story->system_prompt['character_name'] ?? null,
                ),
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

            $narration = DB::transaction(function () use (
                $game,
                $worldState,
                $alignmentScaffold,
                $symbolicMemory,
                $result,
                $branchCommit,
                $normalizedChoices,
                $story,
                $usageMoment,
                $usageOccurredAt,
                $systemPrompt,
            ): Prompt {
                $game->update([
                    'world_state' => $worldState,
                    'alignment_scaffold' => $alignmentScaffold,
                    'symbolic_memory' => $symbolicMemory,
                    'is_climactic_choice' => (bool) $result['is_climactic_choice'],
                    'defining_choice_id' => $branchCommit ? $branchCommit['defining_choice_id'] : null,
                    'defining_path_label' => $branchCommit ? $branchCommit['defining_path_label'] : null,
                    'defining_choice_line' => $branchCommit ? $branchCommit['defining_choice_line'] : null,
                    'current_session_complete' => $usageMoment === null
                        ? $result['session_complete']
                        : false,
                ]);

                $openingMomentKey = $usageMoment?->moment_key;
                $prompt = $game->prompts()->create([
                    'session_number' => 1,
                    'moment_key' => $openingMomentKey,
                    'exit_condition_met' => $openingMomentKey !== null ? false : null,
                    'moment_result' => null,
                    'moment_footnote' => null,
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

                $this->tokenUsage->record(
                    game: $game,
                    prompt: $prompt,
                    rawUsage: (array) ($result['_usage'] ?? []),
                    kind: PlayerTokenUsage::KIND_BEGIN,
                    moment: $usageMoment,
                    occurredAt: $usageOccurredAt,
                    systemPrompt: $systemPrompt,
                );

                return $prompt;
            });

            UserActivityDay::record($game->user_id);
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::channel('narration')->info('game.begin', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'model' => $game->model,
                'session_complete' => $result['session_complete'],
                'response_bytes' => mb_strlen($result['response'], '8bit'),
                'system_prompt_bytes' => mb_strlen($systemPrompt, '8bit'),
                'llm_elapsed_ms' => $llmElapsedMs,
                'elapsed_ms' => $elapsedMs,
                'prompt_tokens' => data_get($result, '_usage.prompt_tokens'),
                'completion_tokens' => data_get($result, '_usage.completion_tokens'),
                'cache_write_input_tokens' => data_get($result, '_usage.cache_write_input_tokens'),
                'cache_read_input_tokens' => data_get($result, '_usage.cache_read_input_tokens'),
                ...((array) ($result['_output_metrics'] ?? [])),
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
                'system_prompt_bytes' => mb_strlen($systemPrompt, '8bit'),
                'response_bytes' => mb_strlen($result['response'], '8bit'),
                'llm_elapsed_ms' => $llmElapsedMs,
                'elapsed_ms' => $elapsedMs,
                'prompt_tokens' => data_get($result, '_usage.prompt_tokens'),
                'completion_tokens' => data_get($result, '_usage.completion_tokens'),
                'cache_write_input_tokens' => data_get($result, '_usage.cache_write_input_tokens'),
                'cache_read_input_tokens' => data_get($result, '_usage.cache_read_input_tokens'),
                ...((array) ($result['_output_metrics'] ?? [])),
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

        $this->renderNarrationAudio($game, $narration);
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

        $this->ensureConfiguredNarrationModel($game);

        Cache::put($this->pendingKey($gameId), 'next_session', 180);
        Cache::forget($this->errorKey($gameId));
        $narration = null;
        $startedAt = microtime(true);

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

            $usageMoment = $game->current_playable_moment_id === null
                ? null
                : PlayableMoment::query()->findOrFail($game->current_playable_moment_id);

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
                isEpisodeTransition: true,
            );

            $usageOccurredAt = now()->toImmutable()->utc();
            $llmStartedAt = microtime(true);
            $result = $this->engine->callAgent(
                model: $game->model,
                systemPrompt: $systemPrompt,
                conversationHistory: [],
                playerAction: null,
                protagonist: ChaosStoryConfig::protagonistFor(
                    $story->slug,
                    $story->system_prompt['character_name'] ?? null,
                ),
                temperature: $this->engine->gameTemperatureFor($game->model),
                worldState: $carriedWorldState,
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

            $worldState = $this->engine->mergeStateDelta($carriedWorldState, $result['state_delta']);
            $alignmentScaffold = $this->engine->mergeAlignmentDelta($carriedAlignment, $result['alignment_tally_delta']);
            $symbolicMemory = $this->engine->appendMemory($carriedMemory, $result['symbolic_memory_update']);
            $branchCommit = $this->engine->resolveBranchCommit(
                $result,
                $game,
                $story->adaptation?->sessionAdaptations,
            );

            $currentSessionNumber = $nextSessionNumber - 1;

            $narration = DB::transaction(function () use (
                $game,
                $nextSessionNumber,
                $result,
                $worldState,
                $alignmentScaffold,
                $symbolicMemory,
                $branchCommit,
                $normalizedChoices,
                $currentSessionNumber,
                $story,
                $usageMoment,
                $usageOccurredAt,
                $systemPrompt,
            ): Prompt {
                $game->update([
                    'current_session_number' => $nextSessionNumber,
                    'current_session_complete' => $usageMoment === null
                        ? $result['session_complete']
                        : false,
                    'world_state' => $worldState,
                    'alignment_scaffold' => $alignmentScaffold,
                    'symbolic_memory' => $symbolicMemory,
                    'is_climactic_choice' => (bool) $result['is_climactic_choice'],
                    'defining_choice_id' => $branchCommit ? $branchCommit['defining_choice_id'] : $game->defining_choice_id,
                    'defining_path_label' => $branchCommit ? $branchCommit['defining_path_label'] : $game->defining_path_label,
                    'defining_choice_line' => $branchCommit ? $branchCommit['defining_choice_line'] : $game->defining_choice_line,
                ]);

                $openingMomentKey = $usageMoment?->moment_key;
                $prompt = $game->prompts()->create([
                    'session_number' => $nextSessionNumber,
                    'moment_key' => $openingMomentKey,
                    'exit_condition_met' => $openingMomentKey !== null ? false : null,
                    'moment_result' => null,
                    'moment_footnote' => null,
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

                $this->tokenUsage->record(
                    game: $game,
                    prompt: $prompt,
                    rawUsage: (array) ($result['_usage'] ?? []),
                    kind: PlayerTokenUsage::KIND_NEXT_SESSION,
                    moment: $usageMoment,
                    occurredAt: $usageOccurredAt,
                    systemPrompt: $systemPrompt,
                );

                return $prompt;
            });

            UserActivityDay::record($game->user_id);
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::channel('narration')->info('game.next_session', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'session_number' => $nextSessionNumber,
                'model' => $game->model,
                'session_complete' => $result['session_complete'],
                'response_bytes' => mb_strlen($result['response'], '8bit'),
                'system_prompt_bytes' => mb_strlen($systemPrompt, '8bit'),
                'llm_elapsed_ms' => $llmElapsedMs,
                'elapsed_ms' => $elapsedMs,
                'prompt_tokens' => data_get($result, '_usage.prompt_tokens'),
                'completion_tokens' => data_get($result, '_usage.completion_tokens'),
                'cache_write_input_tokens' => data_get($result, '_usage.cache_write_input_tokens'),
                'cache_read_input_tokens' => data_get($result, '_usage.cache_read_input_tokens'),
                ...((array) ($result['_output_metrics'] ?? [])),
                'deferred' => true,
            ]);
            NarrationMetricsStore::record([
                'kind' => 'next_session',
                'game_id' => $game->id,
                'story_id' => $story->id,
                'story_slug' => $story->slug,
                'session_number' => $nextSessionNumber,
                'model' => $game->model,
                'boiler_active' => $game->current_playable_moment_id !== null,
                'system_prompt_bytes' => mb_strlen($systemPrompt, '8bit'),
                'response_bytes' => mb_strlen($result['response'], '8bit'),
                'llm_elapsed_ms' => $llmElapsedMs,
                'elapsed_ms' => $elapsedMs,
                'prompt_tokens' => data_get($result, '_usage.prompt_tokens'),
                'completion_tokens' => data_get($result, '_usage.completion_tokens'),
                'cache_write_input_tokens' => data_get($result, '_usage.cache_write_input_tokens'),
                'cache_read_input_tokens' => data_get($result, '_usage.cache_read_input_tokens'),
                ...((array) ($result['_output_metrics'] ?? [])),
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

        $this->renderNarrationAudio($game, $narration);
    }

    /**
     * Render the opening narration's audio while the player is still reading.
     * Runs after the pending flag is cleared so the voice provider never holds
     * the UI back.
     */
    private function renderNarrationAudio(?Game $game, ?Prompt $narration): void
    {
        if ($game === null || $narration === null) {
            return;
        }

        $this->audio->markPendingRecommended($game, $narration);
        $this->audio->generate($game, $narration);
    }

    private function ensureConfiguredNarrationModel(Game $game): void
    {
        $configuredModel = $this->engine->modelForConfiguredProvider($game->model);
        if ($configuredModel === $game->model) {
            return;
        }

        Log::channel('narration')->warning('game.model_provider_fallback', [
            'game_id' => $game->id,
            'from_model' => $game->model,
            'to_model' => $configuredModel,
        ]);

        $game->update(['model' => $configuredModel]);
    }
}
