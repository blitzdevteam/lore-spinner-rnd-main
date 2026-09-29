<?php

declare(strict_types=1);

namespace App\Actions\Game;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\Game;
use App\Models\PlayableMoment;
use App\Models\PlayerTokenUsage;
use App\Models\Prompt;
use App\Models\UserActivityDay;
use App\Services\ChaosEngineService;
use App\Services\NarrationAudioService;
use App\Services\Runtime\CompletionReceiptRecorder;
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

    private const CONTINUE_MODE_INSTRUCTION = <<<'PROMPT'
CONTINUE MODE: MEANINGFUL FAST-FORWARD.
The player explicitly wants to move past low-agency material. Use the future
session context already available to you (the beat map, source events, current
moment context, and conversation history) to judge what is connective and what
is meaningful. Compress or resolve routine travel, waiting, scenery, and other
uneventful material instead of making the player repeat it.

Move the story forward until the next meaningful playable moment. If the
remaining material in the active moment is optional connective material and the
player's Continue makes it safe to bypass, you may set exit_condition_met=true
and advance normally. Do not hold the player in a low-agency loop merely
because the ordinary moment budget has not been exhausted.

Stop and return control to the player before an authored branch, crisis,
relationship or moral decision, required interaction, or session-ending choice.
Continue may bring the player to that decision, but it
must never choose an authored branch, invent a player action, or commit a
canonical choice for them.
Preserve established state and consequences. In Boiler, keep
moment_progress.moment_key accurate and use the existing resolver contract; in
legacy mode, set session_complete only when the actual session close is
reached.
Keep the fast-forward response concise: a short transition or montage followed
by the meaningful situation and its choices. Do not offer more filler actions
such as "keep walking" or "look around" when the player has explicitly chosen
to move past that material.

Continue is a request to read ahead, not a decision by the protagonist. Play
the author's pages for this scene forward at a quicker pace. The protagonist may
do and say what the source has them do along the way, but never make a choice
the source leaves open and never take an authored decision for the player; stop
when the story reaches it. Each Continue must
bring something the previous turn did not have: a new place, a new person, or a
new development. Do not re-describe the journey, the company, or what the
protagonist is carrying.
PROMPT;

    /** How far back to look for a player restating the same intent. */
    private const REPEAT_INTENT_LOOKBACK = 4;

    /** Share of content words two actions must share to count as the same intent. */
    private const REPEAT_INTENT_THRESHOLD = 0.5;

    /**
     * Words carrying no intent, removed before comparing two player actions. Kept
     * deliberately small: the comparison should be blind to phrasing, not to content.
     *
     * @var array<int, string>
     */
    private const INTENT_STOP_WORDS = [
        'a', 'am', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'do', 'for', 'from',
        'go', 'had', 'has', 'have', 'he', 'her', 'him', 'his', 'i', 'if', 'in', 'into',
        'is', 'it', 'its', 'just', 'll', 'm', 'me', 'my', 'not', 'now', 'of', 'on', 'or',
        'out', 're', 's', 'she', 'so', 'that', 'the', 'their', 'them', 'then', 'they',
        'this', 'to', 'up', 've', 'was', 'we', 'were', 'what', 'when', 'will', 'with',
        'you', 'your',
    ];

    public function __construct(
        private readonly ChaosEngineService $engine,
        private readonly PlayableMomentResolver $momentResolver,
        private readonly NarrationResultNormalizer $resultNormalizer,
        private readonly NarrationAudioService $audio,
        private readonly PlayerTokenUsageRecorder $tokenUsage,
        private readonly CompletionReceiptRecorder $receiptRecorder,
    ) {}

    /**
     * Runs the turn outside the player's HTTP request. When a delta callback is
     * given the model is streamed so the prose can be relayed live (over a
     * broadcast channel, never the request), while still pre-rendering audio
     * and clearing the pending flag exactly like a plain deferred turn.
     *
     * @param  array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null  $explicitBranch
     * @param  callable(string): void|null  $onResponseDelta
     */
    public function handle(
        string $gameId,
        string $sourcePromptId,
        string $storedPromptValue,
        string $playerAction,
        ?array $explicitBranch,
        int $userId,
        ?callable $onResponseDelta = null,
    ): ?Prompt {
        return $this->process(
            gameId: $gameId,
            sourcePromptId: $sourcePromptId,
            storedPromptValue: $storedPromptValue,
            playerAction: $playerAction,
            explicitBranch: $explicitBranch,
            userId: $userId,
            onResponseDelta: $onResponseDelta,
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
                playerAction: $playerAction,
                recentNarrations: array_slice(array_column(
                    array_filter($history, static fn (array $entry): bool => ($entry['role'] ?? '') === 'narrator'),
                    'text',
                ), -3),
            );

            if ($explicitBranch !== null) {
                $systemPrompt .= "\n\nVALIDATED PLAYER SELECTION (authoritative): "
                    .$explicitBranch['defining_choice_id'].' path '.$explicitBranch['defining_path_label']
                    .'. Resolve this selected authored path; do not infer a different one.';
            }

            if ($storedPromptValue === self::CONTINUE_MARKER) {
                $systemPrompt .= "\n\n".self::CONTINUE_MODE_INSTRUCTION;
                $continueBound = $this->engine->continueBoundInstruction($game);
                if ($continueBound !== '') {
                    $systemPrompt .= "\n\n".$continueBound;
                }
            }

            $repeatedAttempts = $this->countRepeatedIntent($game, $playerAction);
            if ($repeatedAttempts > 0) {
                $ordinal = $repeatedAttempts + 1;
                $systemPrompt .= "\n\nREPEATED INTENT (MEASURED, NOT INFERRED): this is the {$ordinal}"
                    .'th consecutive turn on which the player has asked for substantially the same thing. '
                    .'Their previous attempts did not land. Do not deflect it again with a new in-world reason: '
                    .'either let it happen now with its full consequences, or make the obstacle unmistakable in '
                    .'the prose so they can see it and choose differently. Do not narrate them changing their '
                    .'mind on their own, and do not record that they chose restraint.';
            }

            $boilerActive = $game->current_playable_moment_id !== null;
            $usageMoment = $boilerActive
                ? PlayableMoment::query()->findOrFail($game->current_playable_moment_id)
                : null;
            // Boiler scales the window with time spent in the active room, but never
            // below the legacy floor: the turn count resets when the pointer advances,
            // and a narrator that only remembers one exchange cannot honour what the
            // player did in the room they just left. The ceiling stops a long stay in
            // one room from feeding the narrator its own repeated prose; older facts
            // reach it through the persistent state instead.
            $historyWindow = $boilerActive
                ? array_slice($history, -min(28, max(20, ($game->playable_moment_turn_count + 1) * 2)))
                : array_slice($history, -20);

            $usageOccurredAt = now()->toImmutable()->utc();
            $llmStartedAt = microtime(true);
            $result = $onResponseDelta === null
                ? $this->engine->callAgent(
                    model: $game->model,
                    systemPrompt: $systemPrompt,
                    conversationHistory: $historyWindow,
                    playerAction: $playerAction,
                    protagonist: ChaosStoryConfig::protagonistFor(
                        $story->slug,
                        $story->system_prompt['character_name'] ?? null,
                    ),
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
                    protagonist: ChaosStoryConfig::protagonistFor(
                        $story->slug,
                        $story->system_prompt['character_name'] ?? null,
                    ),
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
            $game->refresh();
            if ($game->current_session_complete) {
                $this->receiptRecorder->snapshot($game);
            }
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
            $this->audio->markPendingRecommended($game, $responsePrompt);
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
     * How many of the immediately preceding player actions asked for the same thing
     * as this one.
     *
     * A player restating an intent is the clearest available signal that the previous
     * turn did not actually deliver it. This only measures and reports: the narrator
     * still decides what to do about it, and nothing here alters or blocks the turn.
     */
    private function countRepeatedIntent(Game $game, string $playerAction): int
    {
        $current = $this->intentFingerprint($playerAction);
        if ($current === []) {
            return 0;
        }

        $previous = $game->prompts()
            ->whereNotNull('prompt')
            ->where('prompt', '!=', '')
            ->orderByDesc('id')
            ->limit(self::REPEAT_INTENT_LOOKBACK)
            ->pluck('prompt');

        $repeats = 0;
        foreach ($previous as $earlier) {
            $candidate = $this->intentFingerprint((string) $earlier);
            if ($candidate === []) {
                break;
            }

            $shared = count(array_intersect($current, $candidate));
            $overlap = $shared / min(count($current), count($candidate));

            // Consecutive only: the first turn that asked for something else ends the run.
            if ($overlap < self::REPEAT_INTENT_THRESHOLD) {
                break;
            }

            $repeats++;
        }

        return $repeats;
    }

    /**
     * Content words of a player action, for comparing one action against another.
     *
     * @return array<int, string>
     */
    private function intentFingerprint(string $action): array
    {
        $normalized = mb_strtolower(mb_trim($action));
        if ($normalized === '' || $normalized === '__continue__') {
            return [];
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $content = array_values(array_diff($words, self::INTENT_STOP_WORDS));

        return array_values(array_unique($content));
    }

    /**
     * @return array<int, array{role:string, text:string, protagonist?:string}>
     */
    private function buildConversationHistory(Game $game, mixed $story): array
    {
        $history = [];
        $protagonist = ChaosStoryConfig::protagonistFor(
            $story->slug,
            $story->system_prompt['character_name'] ?? null,
        );

        // ULID order, not created_at: this is the narrator's memory of the session,
        // and two turns sharing a created_at second could be replayed out of sequence.
        $prompts = $game->prompts()
            ->where('session_number', $game->current_session_number)
            ->orderBy('id')
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
