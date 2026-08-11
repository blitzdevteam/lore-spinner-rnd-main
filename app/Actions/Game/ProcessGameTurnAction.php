<?php

declare(strict_types=1);

namespace App\Actions\Game;

use App\Models\Game;
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
 * Runs the slow narration LLM + commit outside the HTTP response cycle so
 * Cloudflare/proxies never sit on a silent TTFB and return an instant 504
 * while PHP is still generating.
 */
final class ProcessGameTurnAction
{
    private const CONTINUE_MARKER = '__continue__';

    public function __construct(
        private readonly ChaosEngineService $engine,
        private readonly PlayableMomentResolver $momentResolver,
        private readonly NarrationResultNormalizer $resultNormalizer,
        private readonly NarrationAudioService $audio,
        private readonly PlayerTokenUsageRecorder $tokenUsage,
    ) {}

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
    ): void {
        $this->process(
            gameId: $gameId,
            sourcePromptId: $sourcePromptId,
            storedPromptValue: $storedPromptValue,
            playerAction: $playerAction,
            explicitBranch: $explicitBranch,
            userId: $userId,
            onResponseDelta: null,
            deferred: true,
        );
    }

    /**
     * @param  array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null  $explicitBranch
     * @param  callable(string): void  $onResponseDelta
     */
    public function handleStreaming(
        string $gameId,
        string $sourcePromptId,
        string $storedPromptValue,
        string $playerAction,
        ?array $explicitBranch,
        int $userId,
        callable $onResponseDelta,
    ): ?Prompt {
        return $this->process(
            gameId: $gameId,
            sourcePromptId: $sourcePromptId,
            storedPromptValue: $storedPromptValue,
            playerAction: $playerAction,
            explicitBranch: $explicitBranch,
            userId: $userId,
            onResponseDelta: $onResponseDelta,
            deferred: false,
        );
    }

    public function pendingKey(string $gameId): string
    {
        return "game-turn-pending:{$gameId}";
    }

    public function errorKey(string $gameId): string
    {
        return "game-turn-error:{$gameId}";
    }

    /**
     * @param  array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null  $explicitBranch
     * @param  callable(string): void|null  $onResponseDelta
     */
    private function process(
        string $gameId,
        string $sourcePromptId,
        string $storedPromptValue,
        string $playerAction,
        ?array $explicitBranch,
        int $userId,
        ?callable $onResponseDelta,
        bool $deferred,
    ): ?Prompt {
        $game = Game::query()->find($gameId);
        if ($game === null) {
            return null;
        }

        $sourcePrompt = $game->prompts()
            ->whereKey($sourcePromptId)
            ->whereNull('prompt')
            ->first();

        if (
            $sourcePrompt === null
            || (int) ($sourcePrompt->session_number ?? 0) !== (int) ($game->current_session_number ?? 0)
        ) {
            return null;
        }

        Cache::put($this->pendingKey($gameId), $sourcePromptId, 180);

        $responsePrompt = null;
        $startedAt = microtime(true);

        try {
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
                    .$explicitBranch['defining_choice_id'].' path '.$explicitBranch['defining_path_label']
                    .'. Resolve this selected authored path; do not infer a different one.';
            }

            $boilerActive = $game->current_playable_moment_id !== null;
            $usageMoment = $boilerActive
                ? PlayableMoment::query()->findOrFail($game->current_playable_moment_id)
                : null;
            // Boiler scales the window with time spent in the active room, but never
            // below the legacy floor: the turn count resets when the pointer advances,
            // and a narrator that only remembers one exchange cannot honour what the
            // player did in the room they just left.
            $historyWindow = $boilerActive
                ? array_slice($history, -max(12, ($game->playable_moment_turn_count + 1) * 2))
                : array_slice($history, -12);

            $usageOccurredAt = now()->toImmutable()->utc();
            $llmStartedAt = microtime(true);
            $result = $onResponseDelta === null
                ? $this->engine->callAgent(
                    model: $game->model,
                    systemPrompt: $systemPrompt,
                    conversationHistory: $historyWindow,
                    playerAction: $playerAction,
                    protagonist: (string) ($story->system_prompt['character_name'] ?? 'the protagonist'),
                    temperature: $this->engine->gameTemperatureFor($game->model),
                    worldState: $worldState,
                    game: $game,
                    isOpening: false,
                )
                : $this->engine->callAgentStreaming(
                    model: $game->model,
                    systemPrompt: $systemPrompt,
                    conversationHistory: $historyWindow,
                    playerAction: $playerAction,
                    protagonist: (string) ($story->system_prompt['character_name'] ?? 'the protagonist'),
                    onResponseDelta: $onResponseDelta,
                    temperature: $this->engine->gameTemperatureFor($game->model),
                    worldState: $worldState,
                    game: $game,
                    isOpening: false,
                );
            $llmElapsedMs = (int) round((microtime(true) - $llmStartedAt) * 1000);
            $normalizedChoices = $this->resultNormalizer->choices($result, $boilerActive);

            $responsePrompt = DB::transaction(function () use (
                $boilerActive,
                $game,
                $sourcePrompt,
                $storedPromptValue,
                $result,
                $normalizedChoices,
                $story,
                $explicitBranch,
                $usageMoment,
                $usageOccurredAt,
                $systemPrompt,
            ): Prompt {
                $committedPrompt = $boilerActive
                    ? $this->momentResolver->applyPlayerTurn(
                        $game,
                        $sourcePrompt,
                        $storedPromptValue,
                        $result,
                        $normalizedChoices,
                        $story->adaptation->sessionAdaptations,
                        $explicitBranch,
                    )
                    : $this->commitLegacyTurn(
                        $game,
                        $sourcePrompt,
                        $storedPromptValue,
                        $result,
                        $normalizedChoices,
                        $story->adaptation?->sessionAdaptations,
                    );

                $this->tokenUsage->record(
                    game: $game,
                    prompt: $committedPrompt,
                    rawUsage: (array) ($result['_usage'] ?? []),
                    kind: PlayerTokenUsage::KIND_TURN,
                    moment: $usageMoment,
                    occurredAt: $usageOccurredAt,
                    systemPrompt: $systemPrompt,
                );

                return $committedPrompt;
            });

            UserActivityDay::record($userId);
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::channel('narration')->info('game.turn', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'session_number' => $game->current_session_number,
                'model' => $game->model,
                'source_prompt_id' => $sourcePrompt->id,
                'boiler_active' => $boilerActive,
                'player_input' => mb_substr($playerAction, 0, 120),
                'response_bytes' => mb_strlen($result['response'], '8bit'),
                'system_prompt_bytes' => mb_strlen($systemPrompt, '8bit'),
                'llm_elapsed_ms' => $llmElapsedMs,
                'elapsed_ms' => $elapsedMs,
                'prompt_tokens' => data_get($result, '_usage.prompt_tokens'),
                'completion_tokens' => data_get($result, '_usage.completion_tokens'),
                'cache_write_input_tokens' => data_get($result, '_usage.cache_write_input_tokens'),
                'cache_read_input_tokens' => data_get($result, '_usage.cache_read_input_tokens'),
                ...((array) ($result['_output_metrics'] ?? [])),
                'deferred' => $deferred,
            ]);
            NarrationMetricsStore::record([
                'kind' => 'turn',
                'game_id' => $game->id,
                'story_id' => $story->id,
                'story_slug' => $story->slug,
                'session_number' => $game->current_session_number,
                'model' => $game->model,
                'boiler_active' => $boilerActive,
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
            Log::channel('narration')->error('game.turn_failed', [
                'game_id' => $gameId,
                'source_prompt_id' => $sourcePromptId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'player_input' => mb_substr($playerAction, 0, 120),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'deferred' => $deferred,
            ]);
            Cache::put($this->errorKey($gameId), 'Narration hiccuped — please retry this turn.', 60);
            throw $e;
        } finally {
            Cache::forget($this->pendingKey($gameId));
        }

        // Render the narration audio while the player is still reading the text.
        // Deliberately after the pending flag is cleared so the UI is not held
        // back by the voice provider.
        if ($deferred) {
            $this->audio->markPending($responsePrompt);
            $this->audio->generate($game, $responsePrompt);
        }

        return $responsePrompt;
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
    ): Prompt {
        return DB::transaction(function () use (
            $game,
            $sourcePrompt,
            $storedPromptValue,
            $result,
            $normalizedChoices,
            $sessionAdaptations,
        ): Prompt {
            $lockedGame = Game::query()->whereKey($game->id)->lockForUpdate()->firstOrFail();
            $lockedSource = Prompt::query()
                ->whereKey($sourcePrompt->id)
                ->where('game_id', $lockedGame->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingResponse = Prompt::query()
                ->where('game_id', $lockedGame->id)
                ->where('source_prompt_id', $lockedSource->id)
                ->first();
            if ($existingResponse !== null) {
                return $existingResponse;
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

            return $lockedGame->prompts()->create([
                'source_prompt_id' => $lockedSource->id,
                'session_number' => $lockedGame->current_session_number,
                'response' => $result['response'],
                'choices' => $normalizedChoices,
            ]);
        });
    }

    /**
     * @return array<int, array{role:string, text:string, protagonist?:string}>
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
