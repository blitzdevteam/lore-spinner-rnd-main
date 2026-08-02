<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\Chaos\ChaosNarrationSchema;
use App\Ai\Agents\Chaos\NativeObjectSchema;
use App\Models\Game;
use App\Models\PlayableMoment;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use RuntimeException;

/**
 * Chaos Engine — shared AI narration logic for both the experimental
 * /chaos-mode page and the production /user/games/{id} flow.
 *
 * This service owns every piece of the Chaos V2 narration pipeline:
 *   - Session context loading (runtime_narrator_prompt + events)
 *   - System prompt assembly (injection points)
 *   - Prism agent call (ChaosNarrationSchema, multi-provider)
 *   - World state + alignment merging
 *   - Memory accumulation
 *
 * Both ChaosModeController (experimental) and GameController/PromptController
 * (production) inject this service. There is no duplicated engine code.
 */
final class ChaosEngineService
{
    public const MODEL_CONFIG = [
        'gpt-5.5' => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.4' => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.4-mini' => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.2' => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-4.1' => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'chat-latest' => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'claude-opus-4-8' => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-opus-4-7' => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-sonnet-5' => ['provider' => 'anthropic', 'reasoning_effort' => null],
        'claude-sonnet-4-6' => ['provider' => 'anthropic', 'temperature' => 0.9,  'reasoning_effort' => null],
        'claude-haiku-4-5' => ['provider' => 'anthropic', 'temperature' => 0.95, 'reasoning_effort' => null],
    ];

    // public const DEFAULT_MODEL = 'claude-haiku-4-5';
    // /** Production /user/games narration — tighter than chaos-mode lab defaults. */
    // public const GAME_TEMPERATURE = 0.4;

    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /** Production /user/games narration — tighter than chaos-mode lab defaults. */
    public const GAME_TEMPERATURE = 0.6;

    /**
     * Narrator self-reported relationship between a turn and the active room.
     * Diagnostic only — progression is still driven solely by exit_condition_met.
     */
    private const ROUTE_RELATIONS = ['advanced', 'deepened', 'detoured', 'reconverged'];

    /** Narrator self-reported durability of what a turn changed. */
    private const IMPACT_SCOPES = ['expressive', 'local', 'persistent'];

    /**
     * Detour beats granted when the player first steps outside the active room.
     * A fresh allowance per detour — never consumed by ordinary in-room play.
     */
    private const DETOUR_BEAT_ALLOWANCE = 3;

    private const COMPLETE_LIST_STATE_FIELDS = ['conditions', 'items', 'unresolved_promises'];

    private const KEYED_DELTA_STATE_FIELDS = ['object_states', 'relationship_updates', 'world_flags'];

    private const APPEND_ONLY_STATE_FIELDS = ['knowledge', 'notes', 'player_style'];

    private const SPARSE_STATE_DELTA_CONTRACT = <<<'TEXT'
RUNTIME STATE OUTPUT — SPARSE AND NON-DESTRUCTIVE:
- In state_delta, null means unchanged. Never repeat unchanged world state.
- conditions, items, and unresolved_promises are complete replacement lists only when they changed; [] explicitly clears the list.
- object_states, relationship_updates, and world_flags contain changed keyed entries only.
- knowledge, notes, player_style, and emotional_ledger_entries contain new entries only.
- If uncertain whether state changed, return null. Narration must continue; state bookkeeping must never block play.
TEXT;

    /**
     * Fixed output ceiling for one narration turn (prose + suggestions + state_delta).
     * No dynamic budgeting and no in-request truncation retry — one call, one budget.
     */
    private const NARRATION_MAX_TOKENS = 8_192;

    public function __construct(private SessionEventLoader $eventLoader) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public function allowedModels(): array
    {
        return array_keys(self::MODEL_CONFIG);
    }

    public function defaultTemperatureFor(string $model): float
    {
        return (float) (self::MODEL_CONFIG[$model]['temperature'] ?? 1.0);
    }

    public function gameTemperatureFor(string $model): float
    {
        return self::GAME_TEMPERATURE;
    }

    /**
     * Load the cached runtime narrator prompt + per-session context needed to
     * assemble injection points and the opening section.
     *
     * Returns null when runtime_narrator_prompt is null (story not V2-adapted).
     *
     * Opening artifacts are returned separately so renderSystemPrompt() can
     * combine them correctly:
     *   - cold_open         : Phase 3 authored prose (120-180 words), every session
     *   - opening_handoff   : arc_progression.opens_with one-liner (sessions 2+); empty for session 1
     *   - emotional_promise : Phase 3 emotional promise sentence
     *   - must_reintroduce  : Phase 3 cut material to weave back in through action
     *
     * The old single `opening_scene` field (which discarded the cold open when
     * opening_handoff was set) is intentionally removed. Callers must use
     * renderSystemPrompt() with isSessionStart=true to get the full opening block.
     *
     * @return array{
     *     session_number: int,
     *     total_sessions: int,
     *     cold_open: string,
     *     opening_handoff: string,
     *     emotional_promise: string,
     *     must_reintroduce: string,
     *     runtime_prompt: string,
     *     alignment_labels: array<int, array<string, mixed>>,
     *     full_session_events: array<int, array{position:int, title:string, content:string, objectives:?string}>,
     * }|null
     */
    public function loadSessionContext(
        Story $story,
        int $sessionNumber,
        ?string $openingHandoff,
        ?array $resolvedConsequencePath = null,
        ?array $sessionCloseDesign = null,
        ?Game $game = null,
    ): ?array {
        /** @var StoryAdaptation|null $adaptation */
        $adaptation = $story->adaptation;

        /** @var SessionAdaptation|null $sessionAdaptation */
        $sessionAdaptation = $adaptation?->sessionAdaptations
            ?->firstWhere('session_number', $sessionNumber);

        if ($sessionAdaptation === null || $sessionAdaptation->runtime_narrator_prompt === null) {
            return null;
        }

        $storyMap = (array) ($adaptation->story_session_map ?? []);

        $events = $this->eventLoader
            ->forSession($story, $sessionNumber)
            ->map(fn ($event) => [
                'id' => (int) $event->id,
                'chapter_id' => (int) $event->chapter_id,
                'chapter_position' => (int) $event->chapter_position,
                'chapter_title' => (string) $event->chapter_title,
                'position' => (int) $event->position,
                'story_position' => (int) $event->story_position,
                'session_number' => (int) $event->session_number,
                'title' => (string) $event->title,
                'content' => (string) $event->content,
                'objectives' => $event->objectives,
            ])
            ->all();

        $entry = (array) ($sessionAdaptation->entry_point_diagnosis ?? []);

        // Resolve opening_handoff: caller may pass one (session transitions), otherwise
        // fall back to arc_progression.opens_with. Session 1 stays empty (N/A).
        $resolvedHandoff = $this->normalizeOpeningScene($openingHandoff);
        if ($resolvedHandoff === '') {
            $arcRow = $this->findArcProgressionRow($adaptation, $sessionNumber);
            $resolvedHandoff = $this->normalizeOpeningScene($arcRow['opens_with'] ?? '');
        }

        $totalSessions = (int) ($adaptation?->sessionAdaptations?->count() ?? 0);
        $activeMomentContext = $this->resolveActiveMomentContext($game, $sessionAdaptation);

        return [
            'session_number' => $sessionNumber,
            'total_sessions' => $totalSessions,
            'cold_open' => $this->normalizeOpeningScene($entry['cold_open'] ?? ''),
            'opening_handoff' => $resolvedHandoff,
            'emotional_promise' => mb_trim((string) ($entry['emotional_promise'] ?? '')),
            'must_reintroduce' => mb_trim((string) ($entry['format_specific_cut']['must_reintroduce'] ?? '')),
            'runtime_prompt' => (string) $sessionAdaptation->runtime_narrator_prompt,
            'alignment_labels' => (array) ($storyMap['alignment_labels'] ?? []),
            'full_session_events' => $events,
            'resolved_consequence_path' => $resolvedConsequencePath,
            'session_close_design' => $sessionCloseDesign,
            'active_moment_context' => $activeMomentContext,
        ];
    }

    /**
     * Inject runtime-only blocks into the cached runtime narrator prompt.
     *
     * isSessionStart=true  → Section 13 opening block is assembled and injected
     *                        (cold open + handoff + must_reintroduce + 3-minute protocol).
     * isSessionStart=false → Section 13 block is replaced with a single continuation
     *                        line so the narrator does not re-open on mid-session turns.
     *
     * @param  array<string, mixed>  $sessionContext
     * @param  array<string, mixed>  $worldState
     * @param  array{chaotic:int, lawful:int, neutral:int}  $alignmentScaffold
     */
    public function renderSystemPrompt(
        array $sessionContext,
        array $worldState,
        array $alignmentScaffold,
        ?string $symbolicMemory,
        bool $isSessionStart,
        bool $isClimacticPrevious,
        bool $isClimacticThisTurn = false,
    ): string {
        $prompt = $sessionContext['runtime_prompt'];

        $symbolicBlock = mb_trim((string) $symbolicMemory) !== ''
            ? mb_trim((string) $symbolicMemory)
            : '(No symbolic memory yet. The protagonist is still becoming.)';

        $alignmentBlock = $this->renderAlignmentTilt(
            (array) ($sessionContext['alignment_labels'] ?? []),
            $alignmentScaffold,
        );

        // Memory relevance is scoped to the whole session, not the active room.
        // Script containment is a separate concern, already enforced by the active
        // moment injection and the Boiler session map — narrowing this haystack to
        // one scene card only demoted everything the player did earlier in the
        // session to background, which is the opposite of a world that remembers.
        $worldStateBlock = $this->renderTieredWorldState(
            worldState: $worldState,
            isClimactic: $isClimacticPrevious,
            sessionEvents: (array) ($sessionContext['full_session_events'] ?? []),
        );

        // On session-start: inject the full opening block into Section 13 via its token.
        // On continuation: mechanically strip the entire Section 13 block (header + "THIS IS THE
        // HARD START" instructions + FIRST-3-MINUTES PROTOCOL + injection point) so those
        // narrator instructions never reach the model mid-session. The LLM does not see them;
        // it is not asked to self-suppress them. strtr still processes the other three tokens
        // normally; [OPENING_SCENE_INJECTION_POINT] simply goes unused on continuation paths.
        $consequenceBlock = $this->buildConsequenceEchoBlock(
            $sessionContext['resolved_consequence_path'] ?? null
        );

        $closeBlock = $isClimacticThisTurn
            ? $this->buildSessionCloseBlock($sessionContext['session_close_design'] ?? null)
            : '';
        $activeMomentBlock = $this->buildActiveMomentBlock(
            $sessionContext['active_moment_context'] ?? null,
        );

        if ($isSessionStart) {
            $prompt = strtr($prompt, [
                '[SYMBOLIC_MEMORY_INJECTION_POINT]' => $symbolicBlock,
                '[ALIGNMENT_TILT_INJECTION_POINT]' => $alignmentBlock,
                '[OPENING_SCENE_INJECTION_POINT]' => $this->buildOpeningSection($sessionContext),
                '[WORLD_STATE_TIERED_INJECTION_POINT]' => $worldStateBlock,
                '[CONSEQUENCE_ECHO_INJECTION_POINT]' => $consequenceBlock,
                '[SESSION_CLOSE_INJECTION_POINT]' => $closeBlock,
                '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]' => $activeMomentBlock,
            ]);
        } else {
            // Strip Section 13 entirely before substitution. Find the section header and the
            // injection point token and replace the whole span with a single continuation marker.
            // D8 v2 template uses '### SECTION 13:' heading format.
            $sec13Anchor = '### SECTION 13:';
            $injToken = '[OPENING_SCENE_INJECTION_POINT]';
            $sec13Start = mb_strpos($prompt, $sec13Anchor);
            $injPos = mb_strpos($prompt, $injToken);

            if ($sec13Start !== false && $injPos !== false && $injPos > $sec13Start) {
                $prompt = mb_substr($prompt, 0, $sec13Start)
                    .'(Continuation turn — opening already delivered. Resume from conversation history; do not re-cold-open.)'
                    .mb_substr($prompt, $injPos + mb_strlen($injToken));
            }

            $prompt = strtr($prompt, [
                '[SYMBOLIC_MEMORY_INJECTION_POINT]' => $symbolicBlock,
                '[ALIGNMENT_TILT_INJECTION_POINT]' => $alignmentBlock,
                '[WORLD_STATE_TIERED_INJECTION_POINT]' => $worldStateBlock,
                '[CONSEQUENCE_ECHO_INJECTION_POINT]' => $consequenceBlock,
                '[SESSION_CLOSE_INJECTION_POINT]' => $closeBlock,
                '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]' => $activeMomentBlock,
            ]);
        }

        return $prompt;
    }

    /**
     * Extract the session number from a choice_id like "S2_C3" → 2.
     *
     * Centralised here so PromptController and GameController don't duplicate
     * this parsing logic — it belongs in the engine, not controller layer.
     */
    public function extractSessionNumber(string $choiceId): int
    {
        if (preg_match('/^S(\d+)_/i', $choiceId, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    /**
     * Resolve the consequence path for a given defining choice using a direct
     * choice_id + path_label lookup against the consequence map.
     *
     * V2.3.3: Text matching and fallback_to_first_path are removed entirely.
     * The label (A/B/C) is now stored on `games.defining_path_label` and
     * passed in here directly. An invalid or missing label logs a warning and
     * returns null — the consequence echo is simply omitted rather than
     * applying a wrong branch.
     *
     * @param  array<string, mixed>  $consequenceMap  session_adaptations.choice_consequence_map
     * @return array<string, mixed>|null
     */
    public function resolveConsequencePath(
        string $definingChoiceId,
        string $definingPathLabel,
        array $consequenceMap,
    ): ?array {
        $pathLabel = mb_strtoupper(mb_trim($definingPathLabel));

        if ($definingChoiceId === '' || $pathLabel === '') {
            return null;
        }

        $branchingEntry = null;
        foreach ((array) ($consequenceMap['branching_consequences'] ?? []) as $bc) {
            if (mb_strtolower((string) ($bc['choice_id'] ?? '')) === mb_strtolower($definingChoiceId)) {
                $branchingEntry = $bc;
                break;
            }
        }

        if ($branchingEntry === null) {
            Log::warning('consequence_path.choice_not_found', [
                'choice_id' => $definingChoiceId,
                'path_label' => $pathLabel,
            ]);

            return null;
        }

        $matchedPath = null;
        foreach ((array) ($branchingEntry['paths'] ?? []) as $path) {
            if (mb_strtoupper((string) ($path['label'] ?? '')) === $pathLabel) {
                $matchedPath = $path;
                break;
            }
        }

        if ($matchedPath === null) {
            Log::warning('consequence_path.label_not_found', [
                'choice_id' => $definingChoiceId,
                'path_label' => $pathLabel,
                'available_labels' => array_map(
                    fn ($p) => ($p['label'] ?? '?'),
                    (array) ($branchingEntry['paths'] ?? [])
                ),
            ]);

            return null;
        }

        $freeform = null;
        foreach ((array) ($consequenceMap['freeform_guidelines'] ?? []) as $fg) {
            if (
                mb_strtolower((string) ($fg['choice_id'] ?? '')) === mb_strtolower($definingChoiceId)
                && mb_strtoupper((string) ($fg['path_label'] ?? '')) === $pathLabel
            ) {
                $freeform = $fg;
                break;
            }
        }

        return array_merge($matchedPath, $freeform ?? []);
    }

    /**
     * Validate and return the branch triple to commit, or null if nothing should
     * be committed this turn.
     *
     * Rules (atomic — all three fields are committed together or not at all):
     *   - Empty choice_id: expressive/emergent/folded-back turn → return null
     *     (caller preserves prior triple unchanged).
     *   - Non-empty choice_id + invalid/missing label (not A/B/C, or not in
     *     the authored options for that choice): log branch_commit.invalid_label
     *     and return null (prior triple preserved — never pair a new choice_id
     *     with a stale or guessed label).
     *   - Non-empty choice_id + valid label: return the triple.
     *
     * @param  array<string, mixed>  $result  callAgent() return value
     * @param  \Illuminate\Database\Eloquent\Collection<int, SessionAdaptation>|null  $sessionAdaptations
     * @return array{defining_choice_id:string, defining_path_label:string, defining_choice_line:string}|null
     */
    public function resolveBranchCommit(
        array $result,
        Game $game,
        ?object $sessionAdaptations,
    ): ?array {
        $newChoiceId = mb_trim((string) ($result['defining_choice_id'] ?? ''));

        if ($newChoiceId === '') {
            return null;
        }

        $pathLabel = mb_strtoupper(mb_trim((string) ($result['defining_path_label'] ?? '')));

        if (! in_array($pathLabel, ['A', 'B', 'C'], true)) {
            Log::warning('branch_commit.invalid_label', [
                'game_id' => $game->id,
                'choice_id' => $newChoiceId,
                'raw_label' => $result['defining_path_label'] ?? '',
                'note' => 'Label is not A/B/C — branch triple not committed; prior state preserved.',
            ]);

            return null;
        }

        // Validate label exists in the authored options for this choice's own session.
        if ($sessionAdaptations !== null) {
            $choiceSession = $this->extractSessionNumber($newChoiceId);
            $targetSA = $sessionAdaptations->firstWhere('session_number', $choiceSession);
            $authoredLabels = [];

            if ($targetSA && ! empty($targetSA->session_choice_design['branching_choices'])) {
                foreach ((array) $targetSA->session_choice_design['branching_choices'] as $bc) {
                    if (mb_strtolower((string) ($bc['choice_id'] ?? '')) === mb_strtolower($newChoiceId)) {
                        $authoredLabels = array_map(
                            fn ($opt) => mb_strtoupper((string) ($opt['label'] ?? '')),
                            (array) ($bc['options'] ?? [])
                        );
                        break;
                    }
                }
            }

            if ($authoredLabels !== [] && ! in_array($pathLabel, $authoredLabels, true)) {
                Log::warning('branch_commit.invalid_label', [
                    'game_id' => $game->id,
                    'choice_id' => $newChoiceId,
                    'raw_label' => $result['defining_path_label'] ?? '',
                    'authored_labels' => $authoredLabels,
                    'note' => 'Label not found in authored options — branch triple not committed; prior state preserved.',
                ]);

                return null;
            }
        }

        $newLine = mb_trim((string) ($result['defining_choice_line'] ?? ''));

        return [
            'defining_choice_id' => $newChoiceId,
            'defining_path_label' => $pathLabel,
            'defining_choice_line' => $newLine !== '' ? $newLine : null,
        ];
    }

    /**
     * Call the Chaos narration agent via Prism. Retries once on failure.
     *
     * Pass $playerAction = null for the opening turn (session start).
     * Pass the string 'Continue the story forward.' for the continue button.
     *
     * @param  array<int, array{role:string, text:string}>  $conversationHistory
     * @return array{
     *     response:string,
     *     choices:array<int,string>,
     *     visible_suggestions:array<int,mixed>|null,
     *     moment_progress:array<string,mixed>|null,
     *     session_complete:bool,
     *     state_delta:array<string,mixed>,
     *     alignment_tally_delta: array{chaotic:int, lawful:int, neutral:int},
     *     is_climactic_choice:bool,
     *     defining_choice_id:string,
     *     defining_path_label:string,
     *     defining_choice_line:string,
     *     symbolic_memory_update:string,
     *     session_memory_update:string,
     *     _usage?:array<string,mixed>,
     *     _output_metrics?:array<string,int>,
     * }
     */
    public function callAgent(
        string $model,
        string $systemPrompt,
        array $conversationHistory,
        ?string $playerAction,
        string $protagonist,
        float $temperature = 1.0,
        array $worldState = [],
        ?Game $game = null,
        bool $isOpening = false,
    ): array {
        $config = self::MODEL_CONFIG[$model] ?? self::MODEL_CONFIG[self::DEFAULT_MODEL];
        $systemPrompt .= "\n\n".self::SPARSE_STATE_DELTA_CONTRACT;

        $promptText = view('ai.agents.chaos.turn-prompt', [
            'conversationHistory' => $conversationHistory,
            'playerAction' => $playerAction,
            'protagonist' => $protagonist,
        ])->render();

        $schemaArray = ChaosNarrationSchema::definition(new JsonSchemaTypeFactory);
        $providerOptions = $this->providerOptionsFor($config);

        // The browser response has already been sent before production game
        // narration reaches this call. Do not reuse Cloudflare's HTTP timeout
        // as the model timeout: Anthropic structured responses are buffered,
        // so curl receives no bytes until the complete object is ready.
        $request = Prism::structured()
            ->using($config['provider'], $model)
            ->withSchema(new NativeObjectSchema($schemaArray))
            ->withPrompt($promptText)
            ->withClientOptions(['timeout' => 150, 'connect_timeout' => 10])
            ->withProviderOptions($providerOptions);

        if ($config['provider'] === 'anthropic') {
            $request = $request->withSystemPrompts(
                $this->anthropicSystemPrompts($systemPrompt),
            );
        } else {
            $request = $request->withSystemPrompt($systemPrompt);
        }

        // Sonnet 5 / Opus 4.7+ reject non-default temperature, top_p, top_k (400).
        // Omit sampling params; tone is governed by the runtime narrator system prompt.
        if ($this->modelAcceptsSamplingParams($model)) {
            $request = $request->usingTemperature($temperature);
        }

        if ($config['provider'] === 'anthropic') {
            $request = $request->withMaxTokens(self::NARRATION_MAX_TOKENS);
        }

        $response = $request->asStructured();

        /** @var array<string, mixed> $structured */
        $structured = $response->structured ?? [];

        $alignment = (array) ($structured['alignment_tally_delta'] ?? []);

        $result = [
            'response' => (string) ($structured['response'] ?? ''),
            'choices' => (array) ($structured['choices'] ?? []),
            'visible_suggestions' => array_key_exists('visible_suggestions', $structured)
                ? (array) $structured['visible_suggestions']
                : null,
            'moment_progress' => isset($structured['moment_progress'])
                ? (array) $structured['moment_progress']
                : null,
            'session_complete' => (bool) ($structured['session_complete'] ?? false),
            'state_delta' => (array) ($structured['state_delta'] ?? []),
            'alignment_tally_delta' => [
                'chaotic' => (int) ($alignment['chaotic'] ?? 0),
                'lawful' => (int) ($alignment['lawful'] ?? 0),
                'neutral' => (int) ($alignment['neutral'] ?? 0),
            ],
            'is_climactic_choice' => (bool) ($structured['is_climactic_choice'] ?? false),
            'defining_choice_id' => (string) ($structured['defining_choice_id'] ?? ''),
            'defining_path_label' => mb_strtoupper(mb_trim((string) ($structured['defining_path_label'] ?? ''))),
            'defining_choice_line' => (string) ($structured['defining_choice_line'] ?? ''),
            'symbolic_memory_update' => (string) ($structured['symbolic_memory_update'] ?? ''),
            'session_memory_update' => (string) ($structured['session_memory_update'] ?? ''),
            '_usage' => $response->usage->toArray(),
            '_output_metrics' => $this->structuredOutputMetrics($structured),
        ];

        $this->validateBoilerNarrationResult($result, $game, $isOpening);

        return $result;
    }

    /**
     * Merge a state_delta into persisted world_state.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    public function mergeStateDelta(array $previous, array $delta): array
    {
        $merged = $this->emptyWorldState();

        $merged['location'] = mb_trim((string) ($delta['location'] ?? '')) !== ''
            ? (string) $delta['location']
            : (string) ($previous['location'] ?? '');

        foreach (self::COMPLETE_LIST_STATE_FIELDS as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value) && $this->isStringList($value)
                ? $this->cleanStringList($value)
                : $this->cleanStringList($previous[$key] ?? []);
        }

        foreach (self::KEYED_DELTA_STATE_FIELDS as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value)
                ? $this->mergeKeyedStateList((array) ($previous[$key] ?? []), $value)
                : $this->cleanStringList($previous[$key] ?? []);
        }

        foreach (self::APPEND_ONLY_STATE_FIELDS as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value)
                ? $this->appendUniqueStringList((array) ($previous[$key] ?? []), $value)
                : $this->cleanStringList($previous[$key] ?? []);
        }

        $previousLedger = (array) ($previous['emotional_ledger'] ?? []);
        $newLedger = [];
        foreach ((array) ($delta['emotional_ledger_entries'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $category = mb_trim((string) ($entry['category'] ?? ''));
            $text = mb_trim((string) ($entry['entry'] ?? ''));
            if ($category === '' || $text === '') {
                continue;
            }
            $newLedger[] = ['category' => $category, 'entry' => $text];
        }

        $merged['emotional_ledger'] = array_values(array_merge($previousLedger, $newLedger));

        return $merged;
    }

    /**
     * @param  array{chaotic:int, lawful:int, neutral:int}  $previous
     * @param  array{chaotic:int, lawful:int, neutral:int}  $delta
     * @return array{chaotic:int, lawful:int, neutral:int}
     */
    public function mergeAlignmentDelta(array $previous, array $delta): array
    {
        return [
            'chaotic' => max(0, (int) ($previous['chaotic'] ?? 0) + (int) ($delta['chaotic'] ?? 0)),
            'lawful' => max(0, (int) ($previous['lawful'] ?? 0) + (int) ($delta['lawful'] ?? 0)),
            'neutral' => max(0, (int) ($previous['neutral'] ?? 0) + (int) ($delta['neutral'] ?? 0)),
        ];
    }

    public function appendMemory(?string $existing, string $update): ?string
    {
        $update = mb_trim($update);
        if ($update === '') {
            return $existing;
        }

        if ($existing === null || mb_trim($existing) === '') {
            return $update;
        }

        $lines = preg_split("/\r\n|\n|\r/", mb_trim($existing)) ?: [];
        $recent = array_slice($lines, -8);
        $normalizedUpdate = $this->normalizeMemoryLine($update);

        foreach ($recent as $line) {
            if ($this->normalizeMemoryLine((string) $line) === $normalizedUpdate) {
                return $existing;
            }
        }

        return mb_trim($existing)."\n".$update;
    }

    private function normalizeMemoryLine(string $line): string
    {
        $line = mb_strtolower(mb_trim($line));
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return $line;
    }

    /**
     * @param  array<int, array{role:string, text:string}>  $history
     * @return array<int, array{role:string, text:string}>
     */
    public function appendNarratorTurn(array $history, string $responseHtml): array
    {
        $history[] = ['role' => 'narrator', 'text' => $this->stripHtml($responseHtml)];

        return $history;
    }

    /**
     * @param  array<int, array{role:string, text:string}>  $history
     * @return array<int, array{role:string, text:string}>
     */
    public function appendPlayerTurn(array $history, string $action, string $protagonist = 'the protagonist'): array
    {
        $history[] = ['role' => 'player', 'text' => $action, 'protagonist' => $protagonist];

        return $history;
    }

    public function stripHtml(string $html): string
    {
        return mb_trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @return array{
     *   location:string,
     *   conditions:array<int,string>,
     *   items:array<int,string>,
     *   object_states:array<int,string>,
     *   relationship_updates:array<int,string>,
     *   world_flags:array<int,string>,
     *   knowledge:array<int,string>,
     *   notes:array<int,string>,
     *   player_style:array<int,string>,
     *   unresolved_promises:array<int,string>,
     *   emotional_ledger:array<int, array{category:string, entry:string}>,
     * }
     */
    public function emptyWorldState(): array
    {
        return [
            'location' => '',
            'conditions' => [],
            'items' => [],
            'object_states' => [],
            'relationship_updates' => [],
            'world_flags' => [],
            'knowledge' => [],
            'notes' => [],
            'player_style' => [],
            'unresolved_promises' => [],
            'emotional_ledger' => [],
        ];
    }

    /**
     * @return array{chaotic:int, lawful:int, neutral:int}
     */
    public function emptyAlignmentScaffold(): array
    {
        return ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveActiveMomentContext(?Game $game, SessionAdaptation $session): ?array
    {
        if ($game === null || $game->current_playable_moment_id === null) {
            return null;
        }

        $moment = PlayableMoment::query()
            ->with('cut')
            ->whereKey($game->current_playable_moment_id)
            ->first();

        $pointerHealthy = $moment !== null
            && $moment->cut->id === $game->current_playable_session_cut_id
            && $moment->cut->session_adaptation_id === $session->id;

        if (! $pointerHealthy) {
            // Soft-heal rather than kill the turn: rebind to the session's finalized
            // standard cut and keep the player in a real room.
            $cut = $session->playableCut()
                ->where('status', 'finalized')
                ->where('cut_key', 'standard')
                ->first();
            $healed = $cut?->moments()->orderBy('moment_order')->first();

            if ($cut === null || $healed === null) {
                throw new RuntimeException('Game playable-moment pointer does not belong to the active session cut.');
            }

            Log::channel('narration')->warning('boiler.moment_pointer_healed', [
                'game_id' => $game->id,
                'session_number' => $session->session_number,
                'stale_moment_id' => $game->current_playable_moment_id,
                'stale_cut_id' => $game->current_playable_session_cut_id,
                'healed_moment_id' => $healed->id,
                'healed_moment_key' => $healed->moment_key,
            ]);

            $game->update([
                'current_playable_session_cut_id' => $cut->id,
                'current_playable_moment_id' => $healed->id,
                'playable_moment_turn_count' => 0,
                'moment_footnote' => null,
            ]);
            $game->refresh();
            $moment = $healed->load('cut');
        }

        $nextMoment = PlayableMoment::query()
            ->where('playable_session_cut_id', $moment->playable_session_cut_id)
            ->where('moment_order', $moment->moment_order + 1)
            ->first();
        $footnote = (array) ($game->moment_footnote ?? []);

        if (($footnote['moment_key'] ?? null) !== $moment->moment_key) {
            $footnote = [];
        }

        $nextCard = (array) ($nextMoment?->scene_card ?? []);
        $card = (array) $moment->scene_card;
        $preview = $nextMoment === null ? null : [
            'moment_key' => $nextMoment->moment_key,
            'title' => $nextCard['title'] ?? '',
            'runtime_goal' => $nextCard['runtime_goal'] ?? '',
            'opening_image' => $nextCard['opening_image'] ?? '',
            'live_pressure' => $nextCard['live_pressure'] ?? '',
            'player_affordances' => $nextCard['player_affordances'] ?? [],
            'choice_ids' => $nextCard['choice_ids'] ?? [],
            'canon_must_keep' => $nextCard['canon_must_keep'] ?? [],
            'next_bridge' => data_get($card, 'next_bridge'),
        ];

        $targetTurns = (int) data_get($card, 'turn_budget.target_turns', 0);
        $gravityAfter = (int) data_get($card, 'turn_budget.gravity_after', 0);
        $turnCount = (int) $game->playable_moment_turn_count;
        $pacingState = 'UNDER TARGET';
        if ($gravityAfter > 0 && $turnCount >= $gravityAfter) {
            $pacingState = $turnCount > $gravityAfter ? 'PAST GRAVITY' : 'AT GRAVITY';
        } elseif ($targetTurns > 0 && $turnCount >= $targetTurns) {
            $pacingState = 'AT GRAVITY';
        }

        $authoredOptions = $this->authoredOptionsForMoment($session, (array) data_get($card, 'choice_ids', []));

        // Detour accounting rides along in the per-moment footnote blob, so it
        // resets naturally whenever the pointer moves to a new room. Detour
        // allowance is deliberately independent of turn_count: engaging deeply
        // with a room must never cost the player the right to improvise later.
        $detourBeatsUsed = max(0, (int) ($footnote['detour_beats_used'] ?? 0));

        return [
            'moment' => $card,
            'moment_key' => $moment->moment_key,
            'turn_count' => $turnCount,
            'target_turns' => $targetTurns,
            'gravity_after' => $gravityAfter,
            'pacing_state' => $pacingState,
            'exit_condition' => (string) ($card['exit_condition'] ?? ''),
            'forbidden_expansions' => (array) ($card['forbidden_expansions'] ?? []),
            'next_bridge' => (string) data_get($card, 'next_bridge', ''),
            'authored_options' => $authoredOptions,
            'treasure_insight' => $footnote['text'] ?? null,
            'next_moment_preview' => $preview,
            'detour_beats_used' => $detourBeatsUsed,
            'detour_beats_remaining' => max(0, self::DETOUR_BEAT_ALLOWANCE - $detourBeatsUsed),
        ];
    }

    /**
     * @param  array<int, mixed>  $choiceIds
     * @return array<int, array{choice_id:string, options:array<int, array{label:string, text:string}>}>
     */
    private function authoredOptionsForMoment(SessionAdaptation $session, array $choiceIds): array
    {
        $out = [];
        $design = (array) ($session->session_choice_design ?? []);

        foreach ($choiceIds as $choiceId) {
            $id = (string) $choiceId;
            if ($id === '') {
                continue;
            }

            $match = null;
            foreach (['branching_choices', 'emotional_choices'] as $bucket) {
                $found = collect((array) ($design[$bucket] ?? []))->firstWhere('choice_id', $id);
                if (is_array($found)) {
                    $match = $found;
                    break;
                }
            }

            if ($match === null) {
                continue;
            }

            $options = [];
            foreach ((array) ($match['options'] ?? []) as $option) {
                if (! is_array($option)) {
                    continue;
                }
                $options[] = [
                    'label' => strtoupper((string) ($option['label'] ?? '')),
                    'text' => (string) ($option['text'] ?? ''),
                ];
            }

            $out[] = [
                'choice_id' => $id,
                'options' => $options,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function buildActiveMomentBlock(?array $context): string
    {
        if ($context === null) {
            return '';
        }

        $momentKey = (string) ($context['moment_key'] ?? data_get($context, 'moment.moment_key') ?? '');
        $turnCount = (int) ($context['turn_count'] ?? 0);
        $targetTurns = (int) ($context['target_turns'] ?? 0);
        $gravityAfter = (int) ($context['gravity_after'] ?? 0);
        $pacingState = (string) ($context['pacing_state'] ?? 'UNDER TARGET');
        $exitCondition = mb_trim((string) ($context['exit_condition'] ?? ''));
        $forbidden = array_values(array_filter(array_map(
            static fn ($line): string => mb_trim((string) $line),
            (array) ($context['forbidden_expansions'] ?? []),
        )));
        $nextBridge = mb_trim((string) ($context['next_bridge'] ?? ''));
        $authoredOptions = (array) ($context['authored_options'] ?? []);

        $detourRemaining = (int) ($context['detour_beats_remaining'] ?? self::DETOUR_BEAT_ALLOWANCE);
        $detourUsed = (int) ($context['detour_beats_used'] ?? 0);

        $lines = [
            'ACTIVE PLAYABLE MOMENT (where this session\'s pressure lives right now):',
            "Current moment: {$momentKey}",
            "Completed turns in this moment: {$turnCount}",
            "Target turns: {$targetTurns}",
            "Gravity begins: {$gravityAfter}",
            "Pacing state: {$pacingState}",
            'REQUIRED moment_progress.moment_key (exact string): '.$momentKey,
            'REQUIRED: emit valid moment_progress for this key in THIS response (moment_key exact match, boolean exit_condition_met, honest route_relation and impact_scope, and after player input a non-empty moment_footnote ≤30 words).',
        ];

        if ($exitCondition !== '') {
            $lines[] = 'EXIT CONDITION (semantic — outcomes, not a checklist): '.$exitCondition;
        }

        $lines[] = implode("\n", array_filter([
            'WHERE THE PLAYER MAY GO:',
            '- Inside this room: full play. Inspect, talk, act, push, escalate.',
            '- Outside this room: permitted. If the player reaches past the card, follow them rather than blocking them. Each such beat must cost or reveal something real, and what happens there is committed history.',
            '- Never permitted: the canon walls below, and the walls in the Freedom Contract.',
            "Detour beats spent since the player was last on-spine: {$detourUsed}. Comfortable room to follow them further: {$detourRemaining}.",
            $detourRemaining > 0
                ? 'You have room to follow. Let the detour matter, then let the world — not an instruction — draw the story back toward this room\'s pressure.'
                : 'You have followed this thread a while. Bring the story back now through the world\'s own logic: someone acts, something arrives, a cost comes due. Honour what the player did; do not undo it, and do not narrate the redirection as a rule.',
        ]));

        if ($forbidden !== []) {
            $lines[] = implode("\n", [
                'STORYGUARD BOUNDARIES FOR THIS ROOM:',
                'Two different kinds live in this list — read each one and treat it accordingly.',
                '- If it names a truth of the world or a character (what is real, what someone would never do, what cannot happen): that is a wall. Never cross it.',
                '- If it names material that belongs to a LATER room (a scene, reveal, or confrontation not yet due): that is staging, not truth. You may let it be referenced, feared, foreshadowed, or attempted and fail forward — you simply may not stage it as a scene here.',
                ...array_map(static fn (string $rule): string => '- '.$rule, $forbidden),
            ]);
        }

        if ($nextBridge !== '') {
            $lines[] = 'NEXT BRIDGE (use only when exit_condition_met=true): '.$nextBridge;
        }

        if ($authoredOptions !== []) {
            $lines[] = 'AUTHORED OPTIONS BOUND TO THIS MOMENT (map visible_suggestions identities only to these when you map):';
            $lines[] = json_encode($authoredOptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        if (in_array($pacingState, ['AT GRAVITY', 'PAST GRAVITY'], true)) {
            $lines[] = implode("\n", [
                'PACING DIRECTIVE — THE WORLD STARTS PULLING:',
                'Answer the player fully first; this is never a reason to cut them short.',
                'Then make standing still expensive. Time moves, someone decides something, evidence shifts or degrades, a voice or a body arrives, a window begins to close.',
                'Make the next step the most attractive thing in the scene rather than announcing that it is time to move on. The pull belongs to the world — never phrase it as an instruction to the player.',
                'If the exit condition is now semantically satisfied, return exit_condition_met=true and use next_bridge to transition.',
                'Do not repeat established material or hold this room without a new consequence.',
            ]);
        }

        $lines[] = 'FULL SCENE CARD (reference):';
        $lines[] = json_encode($context['moment'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $lines[] = implode("\n", [
            'READ OF THE PLAYER, CARRIED FORWARD FROM THE LAST TURN (private — never quote, expose, or confirm):',
            ($context['treasure_insight'] ?? null) ?: '(none — opening or no matching prior insight)',
            'This is your brief on what would land hardest on this specific person right now. Aim this response at it: choose the image, the pressure, the character reaction, and the silence that answer what they were reaching for. Let them feel recognised without ever being told they were read.',
        ]);
        $lines[] = implode("\n", [
            'BOUNDED NEXT-MOMENT PREVIEW (guidance only; ignore until current exit is met):',
            json_encode($context['next_moment_preview'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null',
            'Its pressure and canon are fixed. How it OPENS is not — let it open on the state this player has actually created: what they broke, said, learned, refused, or made someone believe.',
        ]);

        return implode("\n\n", $lines);
    }

    /**
     * Build the consequence echo block injected at [CONSEQUENCE_ECHO_INJECTION_POINT].
     *
     * Tells the narrator how to surface the player's prior defining choice:
     * what to echo this session, what payoffs to honour, fold-back guidance,
     * and consequence visibility target.
     *
     * Returns empty string when no resolved path is provided.
     *
     * @param  array<string, mixed>|null  $path
     */
    private function buildConsequenceEchoBlock(?array $path): string
    {
        if ($path === null) {
            return '';
        }

        $lines = ['ACTIVE CONSEQUENCE GUIDANCE (player\'s prior defining choice):'];

        if (! empty($path['current_session_echo'])) {
            $lines[] = 'Echo this session: '.$path['current_session_echo'];
        }
        if (! empty($path['next_session_payoff'])) {
            $lines[] = 'Honour this session: '.$path['next_session_payoff'];
        }
        if (! empty($path['narrator_behavior'])) {
            $lines[] = 'Narrator behaviour going forward: '.$path['narrator_behavior'];
        }
        if (! empty($path['consequence_visibility']['what_changes'])) {
            $vis = $path['consequence_visibility'];
            $target = (int) ($vis['when_visible_target_responses'] ?? 2);
            $lines[] = 'Make visible within '.$target.' responses: '.$vis['what_changes']
                .' ('.($vis['how_player_sees_it'] ?? '').')';
        }
        if (! empty($path['fold_back_acknowledge'])) {
            $lines[] = 'Fold-back — acknowledge: "'.$path['fold_back_acknowledge']
                .'" → redirect: "'.($path['fold_back_redirect'] ?? '').'"';
        }
        if (! empty($path['hard_limits'])) {
            $lines[] = 'Hard limits at this node: '.implode(' / ', (array) $path['hard_limits']);
        }

        return implode("\n", $lines);
    }

    /**
     * Build the authored session close block injected at [SESSION_CLOSE_INJECTION_POINT].
     *
     * Only injected when is_climactic_choice was set on the previous turn,
     * signalling the model to deliver the authored resolution prose and
     * session-end choice instead of improvising its own close.
     *
     * Returns empty string when no close design is provided.
     *
     * @param  array<string, mixed>|null  $closeDesign
     */
    private function buildSessionCloseBlock(?array $closeDesign): string
    {
        if ($closeDesign === null) {
            return '';
        }

        $lines = ['AUTHORED SESSION CLOSE — deliver this, do not improvise:'];

        if (! empty($closeDesign['resolution_prose'])) {
            $lines[] = '';
            $lines[] = 'RESOLUTION PROSE (deliver in the author\'s voice):';
            $lines[] = $closeDesign['resolution_prose'];
        }
        if (! empty($closeDesign['hook_transition'])) {
            $lines[] = '';
            $lines[] = 'HOOK TRANSITION:';
            $lines[] = $closeDesign['hook_transition'];
        }

        $choice = $closeDesign['session_end_choice'] ?? null;
        if ($choice !== null) {
            $lines[] = '';
            $lines[] = 'SESSION-END CHOICE — present these three options:';
            $lines[] = 'Q: '.($choice['choice_question'] ?? '');
            foreach (['option_a', 'option_b', 'option_c'] as $opt) {
                if (! empty($choice[$opt]['text'])) {
                    $lines[] = '  '.mb_strtoupper(str_replace('_', ' ', $opt)).': '.$choice[$opt]['text'];
                }
            }
            $lines[] = '';
            $lines[] = 'Offer these same three options as your visible_suggestions this turn, each mapped to this choice_id and its A/B/C path, so the player can take one directly. The session does not close until one is genuinely chosen — so never narrate the story as finished while still asking for this decision, and never ask for it twice as though the first answer did not count.';

            if (! empty($choice['final_line'])) {
                $lines[] = '';
                $lines[] = 'FINAL LINE (last words of this session): '.$choice['final_line'];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build the full opening block injected into Section 13 on session-start turns.
     *
     * For session 1: cold open only (no handoff).
     * For sessions 2+: handoff context THEN cold open so the narrator has both
     *   the continuity seed from the previous session close and the authored
     *   dramatic cut-in for this session's opening.
     *
     * Also injects must_reintroduce and emotional_promise so cut world-building
     * is woven back in through action, matching the Phase 3 design intent.
     *
     * @param  array<string, mixed>  $sessionContext
     */
    private function buildOpeningSection(array $sessionContext): string
    {
        $sessionNumber = (int) ($sessionContext['session_number'] ?? 1);
        $coldOpen = (string) ($sessionContext['cold_open'] ?? '');
        $openingHandoff = (string) ($sessionContext['opening_handoff'] ?? '');
        $emotionalPromise = (string) ($sessionContext['emotional_promise'] ?? '');
        $mustReintroduce = (string) ($sessionContext['must_reintroduce'] ?? '');

        $lines = [];

        // Sessions 2+: prepend the continuity handoff from the previous session close.
        if ($sessionNumber > 1 && $openingHandoff !== '') {
            $lines[] = 'SESSION HANDOFF (what the previous session left behind):';
            $lines[] = $openingHandoff;
            $lines[] = '';
        }

        // Full Phase 3 authored cold open — always present for every session.
        if ($coldOpen !== '') {
            $lines[] = 'COLD OPEN — begin here, not before:';
            $lines[] = $coldOpen;
        }

        // FIRST-3-MINUTES OPENING PROTOCOL — injected on session-start turn 1 only.
        // Present in V2.2 Section 13 as static template text; restored here as a runtime
        // injection so no re-assembly is needed and the clock never leaks to later turns.
        $lines[] = '';
        $lines[] = 'FIRST-3-MINUTES OPENING PROTOCOL (turn 1 only — not every turn): On this opening turn and the first few tiered responses at session start, deliver the beat-map opening sequence: location and situation in under 30 words, first meaningful choice or custom input opportunity within 90 seconds of play, first visible consequence within 120 seconds. After the opening sequence lands, do not re-apply this clock on later turns.';

        // Emotional promise — the engagement hook for this session.
        if ($emotionalPromise !== '') {
            $lines[] = '';
            $lines[] = 'EMOTIONAL PROMISE: '.$emotionalPromise;
        }

        // Cut material that must resurface through action, not exposition.
        if ($mustReintroduce !== '') {
            $lines[] = '';
            $lines[] = 'MUST REINTRODUCE (weave through action and dialogue, never as an exposition dump):';
            $lines[] = $mustReintroduce;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function validateBoilerNarrationResult(array &$result, ?Game $game, bool $isOpening): void
    {
        if ($game === null || $game->current_playable_moment_id === null) {
            return;
        }

        $moment = $game->currentPlayableMoment()->first();
        if ($moment === null) {
            // Rare: pointer lost between prompt render and result parse. Hold progress
            // without inventing a fake key — commit path will soft-repair onto the
            // active row after resolveActiveMomentContext heals the pointer.
            $progress = is_array($result['moment_progress'] ?? null)
                ? $result['moment_progress']
                : [];
            $rawFootnote = $progress['moment_footnote'] ?? null;
            $result['moment_progress'] = [
                'moment_key' => (string) ($progress['moment_key'] ?? ''),
                'exit_condition_met' => false,
                ...$this->normalizeRouteFields($progress),
                'moment_footnote' => $isOpening
                    ? null
                    : (is_string($rawFootnote) && mb_trim($rawFootnote) !== '' ? $rawFootnote : null),
            ];
            Log::channel('narration')->warning('boiler.moment_row_missing_progress_held', [
                'game_id' => $game->id,
                'is_opening' => $isOpening,
            ]);

            return;
        }

        $progress = $result['moment_progress'] ?? null;
        $keyMatches = is_array($progress) && ($progress['moment_key'] ?? null) === $moment->moment_key;
        $exitIsBool = is_array($progress) && array_key_exists('exit_condition_met', $progress)
            && is_bool($progress['exit_condition_met']);

        if (! is_array($progress) || ! $keyMatches || ! $exitIsBool) {
            // Soft structural fallback — rare malformed metadata only.
            // Hold the room; never invent earned exits; never reject the player turn.
            $hadProgressObject = is_array($progress);
            $rawMomentKey = $hadProgressObject ? ($progress['moment_key'] ?? null) : null;
            $footnote = $hadProgressObject ? ($progress['moment_footnote'] ?? null) : null;
            $result['moment_progress'] = [
                'moment_key' => $moment->moment_key,
                // Never trust an exit judgment that was attached to the wrong room
                // or an incomplete object — hold the player in the active moment.
                'exit_condition_met' => false,
                ...$this->normalizeRouteFields(is_array($progress) ? $progress : []),
                'moment_footnote' => $footnote,
            ];
            $progress = $result['moment_progress'];

            Log::channel('narration')->warning('boiler.moment_progress_repaired', [
                'game_id' => $game->id,
                'active_moment_key' => $moment->moment_key,
                'is_opening' => $isOpening,
                'had_progress_object' => $hadProgressObject,
                'raw_moment_key' => $rawMomentKey,
            ]);
        }

        if (! is_array($result['visible_suggestions'] ?? null)) {
            $result['visible_suggestions'] = [];
            Log::channel('narration')->warning('boiler.visible_suggestions_defaulted', [
                'game_id' => $game->id,
            ]);
        } elseif (count($result['visible_suggestions']) > 3) {
            $result['visible_suggestions'] = array_slice($result['visible_suggestions'], 0, 3);
            Log::channel('narration')->warning('boiler.visible_suggestions_truncated', [
                'game_id' => $game->id,
            ]);
        }

        if ((array) ($result['choices'] ?? []) !== []) {
            // Legacy choices are ignored in Boiler mode rather than killing the turn.
            $result['choices'] = [];
            Log::channel('narration')->warning('boiler.legacy_choices_stripped', [
                'game_id' => $game->id,
            ]);
        }

        // Route/impact are observability signals, never progression gates. Normalize
        // them so a loose value degrades to "unknown" instead of poisoning metrics.
        $result['moment_progress'] = [
            ...$result['moment_progress'],
            ...$this->normalizeRouteFields($result['moment_progress']),
        ];
        $progress = $result['moment_progress'];

        $footnote = $progress['moment_footnote'] ?? null;
        if ($isOpening) {
            $result['moment_progress']['route_relation'] = '';
            $result['moment_progress']['impact_scope'] = '';

            if ($progress['exit_condition_met'] !== false || $footnote !== null) {
                $result['moment_progress']['exit_condition_met'] = false;
                $result['moment_progress']['moment_footnote'] = null;
                Log::channel('narration')->warning('boiler.opening_progress_repaired', [
                    'game_id' => $game->id,
                ]);
            }

            return;
        }

        // Footnote must be model-produced after player turns. Never invent a PHP
        // placeholder — log only so soft structural hold stays metadata-only.
        if (! is_string($footnote) || mb_trim($footnote) === '') {
            $result['moment_progress']['moment_footnote'] = null;
            Log::channel('narration')->warning('boiler.moment_footnote_missing', [
                'game_id' => $game->id,
            ]);
        }
    }

    /**
     * Normalize the narrator's self-reported route/impact signals.
     *
     * These are diagnostic only — they never gate progression. An unrecognized
     * value degrades to an empty string ("unknown") rather than being coerced
     * into a wrong label, so drift metrics stay honest.
     *
     * @param  array<string, mixed>  $progress
     * @return array{route_relation:string, impact_scope:string}
     */
    private function normalizeRouteFields(array $progress): array
    {
        $route = mb_strtolower(mb_trim((string) ($progress['route_relation'] ?? '')));
        $impact = mb_strtolower(mb_trim((string) ($progress['impact_scope'] ?? '')));

        return [
            'route_relation' => in_array($route, self::ROUTE_RELATIONS, true) ? $route : '',
            'impact_scope' => in_array($impact, self::IMPACT_SCOPES, true) ? $impact : '',
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $alignmentLabels
     * @param  array{chaotic:int, lawful:int, neutral:int}  $scaffold
     */
    private function renderAlignmentTilt(array $alignmentLabels, array $scaffold): string
    {
        $chaotic = (int) ($scaffold['chaotic'] ?? 0);
        $lawful = (int) ($scaffold['lawful'] ?? 0);
        $neutral = (int) ($scaffold['neutral'] ?? 0);

        $total = $chaotic + $lawful + $neutral;
        if ($total === 0) {
            return 'No player tendency has declared itself yet. Keep all story-native tonal registers available without naming alignment.';
        }

        $dominant = 'mixed';
        $threshold = (int) ceil($total * 0.4);
        $top = max($chaotic, $lawful, $neutral);
        $tieCount = (int) ($chaotic === $top) + (int) ($lawful === $top) + (int) ($neutral === $top);

        if ($tieCount === 1 && $top >= $threshold) {
            $dominant = match (true) {
                $chaotic === $top => 'chaotic',
                $lawful === $top => 'lawful',
                default => 'neutral',
            };
        }

        $label = null;
        $description = null;
        $voiceSignature = null;
        foreach ($alignmentLabels as $row) {
            $mapsTo = mb_strtolower((string) ($row['maps_to_internal'] ?? ''));
            if ($mapsTo === $dominant) {
                $label = (string) ($row['label'] ?? '');
                $description = (string) ($row['narrative_consequences'] ?? ($row['description'] ?? ''));
                $voiceSignature = (string) ($row['voice_signature'] ?? '');
                break;
            }
        }

        if ($label === null || $label === '') {
            return 'The player\'s alignment has tilted, but no story-native label is configured for "'.$dominant
                .'". Narrate without surfacing the alignment, but lean toward this tendency in tone.';
        }

        $lines = ['STORY-NATIVE ALIGNMENT TILT: "'.$label.'" (hidden — never surface the literal label or any RPG terminology).'];
        if ($description !== '') {
            $lines[] = 'Behavioural shape: '.$description;
        }
        if ($voiceSignature !== '') {
            $lines[] = 'Voice signature: '.$voiceSignature;
        }
        $lines[] = 'Tune the narrator\'s voice toward this tendency, but never call it out.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $worldState
     * @param  array<int, array<string, mixed>>  $sessionEvents
     */
    private function renderTieredWorldState(array $worldState, bool $isClimactic, array $sessionEvents): string
    {
        $tier1 = $this->formatTier1($worldState);
        $tier2 = $this->formatTier2($worldState, $sessionEvents);

        if ($isClimactic) {
            $tier3 = $this->formatTier3($worldState);

            return implode("\n\n", array_filter([$tier1, $tier2, $tier3]));
        }

        return implode("\n\n", array_filter([$tier1, $tier2]));
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function formatTier1(array $w): string
    {
        $lines = ['PERSISTENT STATE — TIER 1 (always loaded):'];
        $location = mb_trim((string) ($w['location'] ?? ''));
        $lines[] = 'Location: '.($location !== '' ? $location : '(unset)');
        $lines[] = 'Items: '.$this->joinList((array) ($w['items'] ?? []));
        $lines[] = 'Conditions: '.$this->joinList((array) ($w['conditions'] ?? []));
        $lines[] = 'Knowledge: '.$this->joinList((array) ($w['knowledge'] ?? []));
        $lines[] = 'Notes: '.$this->joinList((array) ($w['notes'] ?? []));
        $lines[] = 'Player style: '.$this->joinList((array) ($w['player_style'] ?? []));

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $w
     * @param  array<int, array<string, mixed>>  $sessionEvents
     */
    private function formatTier2(array $w, array $sessionEvents): string
    {
        $haystack = mb_strtolower(implode(' ', array_map(
            static fn ($e) => (string) ($e['title'] ?? '').' '.(string) ($e['content'] ?? ''),
            $sessionEvents,
        )));

        $allObjects = (array) ($w['object_states'] ?? []);
        $allRelationships = (array) ($w['relationship_updates'] ?? []);
        $allFlags = (array) ($w['world_flags'] ?? []);

        $object = $this->filterByHaystack($allObjects, $haystack);
        $relationships = $this->filterByHaystack($allRelationships, $haystack);
        $flags = $this->filterByHaystack($allFlags, $haystack);
        $dormantObjects = array_values(array_diff($allObjects, $object));
        $dormantRelationships = array_values(array_diff($allRelationships, $relationships));
        $dormantFlags = array_values(array_diff($allFlags, $flags));
        $promises = (array) ($w['unresolved_promises'] ?? []);

        $ledger = (array) ($w['emotional_ledger'] ?? []);
        $recentLedger = array_slice($ledger, -6);
        $ledgerLines = array_map(
            static fn (array $entry) => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_filter($recentLedger, static fn ($e) => is_array($e)),
        );

        $lines = ['PERSISTENT STATE — TIER 2 (scene-connected):'];
        $lines[] = 'Object states: '.$this->joinList($object);
        $lines[] = 'Relationships: '.$this->joinList($relationships);
        $lines[] = 'World flags: '.$this->joinList($flags);
        $lines[] = 'Carried, not yet paid off — callback material, still true (object states): '.$this->joinList($dormantObjects);
        $lines[] = 'Carried, not yet paid off — callback material, still true (relationships): '.$this->joinList($dormantRelationships);
        $lines[] = 'Carried, not yet paid off — callback material, still true (world flags): '.$this->joinList($dormantFlags);
        $lines[] = 'Unresolved promises: '.$this->joinList($promises);
        $lines[] = 'Recent emotional ledger: '.$this->joinList($ledgerLines);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function formatTier3(array $w): string
    {
        $ledger = (array) ($w['emotional_ledger'] ?? []);
        $allLedger = array_map(
            static fn (array $entry) => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_filter($ledger, static fn ($e) => is_array($e)),
        );

        $lines = ['PERSISTENT STATE — TIER 3 (climactic load — previous turn resolved a moral-weight or session-end choice):'];
        $lines[] = 'All object states: '.$this->joinList((array) ($w['object_states'] ?? []));
        $lines[] = 'All relationships: '.$this->joinList((array) ($w['relationship_updates'] ?? []));
        $lines[] = 'All world flags: '.$this->joinList((array) ($w['world_flags'] ?? []));
        $lines[] = 'Full emotional ledger: '.$this->joinList($allLedger);

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $entries
     * @return array<int, string>
     */
    private function filterByHaystack(array $entries, string $haystack): array
    {
        if ($haystack === '') {
            return $entries;
        }

        $filtered = [];
        foreach ($entries as $entry) {
            $name = mb_strtolower((string) (explode(':', (string) $entry, 2)[0] ?? ''));
            if ($name !== '' && str_contains($haystack, $name)) {
                $filtered[] = $entry;
            }
        }

        return $filtered === [] ? $entries : $filtered;
    }

    /**
     * @param  array<int, string>  $list
     */
    private function joinList(array $list): string
    {
        $list = array_values(array_filter(array_map('trim', $list), static fn ($v) => $v !== ''));

        return $list === [] ? '(none)' : implode(' • ', $list);
    }

    /**
     * @return array<int, string>
     */
    private function cleanStringList(mixed $value): array
    {
        $clean = [];

        foreach ((array) $value as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }

            $item = mb_trim((string) $item);
            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isStringList(array $value): bool
    {
        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $previous
     * @param  array<int, mixed>  $delta
     * @return array<int, string>
     */
    private function mergeKeyedStateList(array $previous, array $delta): array
    {
        $entriesByKey = [];
        $orderedKeys = [];

        foreach (array_merge($this->cleanStringList($previous), $this->cleanStringList($delta)) as $entry) {
            $key = $this->stateEntryKey($entry);

            if (! array_key_exists($key, $entriesByKey)) {
                $orderedKeys[] = $key;
            }

            $entriesByKey[$key] = $entry;
        }

        return array_values(array_map(
            static fn (string $key) => $entriesByKey[$key],
            $orderedKeys,
        ));
    }

    private function stateEntryKey(string $entry): string
    {
        $name = mb_trim((string) (explode(':', $entry, 2)[0] ?? $entry));

        return mb_strtolower($name !== '' ? $name : $entry);
    }

    /**
     * @param  array<int, mixed>  $previous
     * @param  array<int, mixed>  $delta
     * @return array<int, string>
     */
    private function appendUniqueStringList(array $previous, array $delta): array
    {
        $seen = [];
        $merged = [];

        foreach (array_merge($this->cleanStringList($previous), $this->cleanStringList($delta)) as $entry) {
            $key = preg_replace('/\s+/u', ' ', mb_strtolower(mb_trim($entry))) ?? mb_strtolower($entry);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[] = $entry;
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $storyMap
     * @return array<string, mixed>
     */
    private function findAllocationRow(array $storyMap, int $sessionNumber): array
    {
        foreach ((array) ($storyMap['session_allocation'] ?? []) as $row) {
            if ((int) ($row['session_number'] ?? 0) === $sessionNumber) {
                return (array) $row;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function findArcProgressionRow(?StoryAdaptation $adaptation, int $sessionNumber): array
    {
        $storyMap = (array) ($adaptation?->story_session_map ?? []);
        foreach ((array) ($storyMap['arc_progression'] ?? []) as $row) {
            if ((int) ($row['session_number'] ?? 0) === $sessionNumber) {
                return (array) $row;
            }
        }

        return [];
    }

    private function normalizeOpeningScene(mixed $value): string
    {
        $text = mb_trim((string) $value);

        return in_array(mb_strtolower($text), ['n/a', 'na', 'none', 'null', '(none)'], true)
            ? ''
            : $text;
    }

    /**
     * Anthropic Sonnet 5 and Opus 4.7+ return 400 if temperature/top_p/top_k
     * are set to non-default values. Omit sampling params for those models.
     *
     * @see https://platform.claude.com/docs/en/about-claude/models/whats-new-sonnet-5
     */
    private function modelAcceptsSamplingParams(string $model): bool
    {
        return ! in_array($model, [
            'claude-sonnet-5',
            'claude-opus-4-8',
            'claude-opus-4-7',
        ], true);
    }

    /**
     * Keep the prompt text and order intact while placing one explicit cache
     * breakpoint immediately before the first per-turn runtime injection.
     *
     * For the Speckled Band prompt this caches about 66KB (Sections 1-7).
     * Sections 8+ still carry fresh memory, active-moment, and world-state data.
     *
     * @return array<int, SystemMessage>
     */
    private function anthropicSystemPrompts(string $systemPrompt): array
    {
        $runtimeSection = '### SECTION 8:';
        $runtimeOffset = mb_strpos($systemPrompt, $runtimeSection);

        if ($runtimeOffset === false || $runtimeOffset === 0) {
            return [new SystemMessage($systemPrompt)];
        }

        $staticPrefix = new SystemMessage(mb_substr($systemPrompt, 0, $runtimeOffset));
        $staticPrefix->withProviderOptions([
            'cacheType' => 'ephemeral',
            'cacheTtl' => '1h',
        ]);

        return [
            $staticPrefix,
            new SystemMessage(mb_substr($systemPrompt, $runtimeOffset)),
        ];
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array<string, int>
     */
    private function structuredOutputMetrics(array $structured): array
    {
        return [
            'structured_response_bytes' => $this->jsonBytes($structured),
            'narration_field_bytes' => mb_strlen((string) ($structured['response'] ?? ''), '8bit'),
            'state_delta_bytes' => $this->jsonBytes($structured['state_delta'] ?? null),
            'suggestions_bytes' => $this->jsonBytes([
                'choices' => $structured['choices'] ?? null,
                'visible_suggestions' => $structured['visible_suggestions'] ?? null,
            ]),
            'moment_progress_bytes' => $this->jsonBytes($structured['moment_progress'] ?? null),
            'alignment_bytes' => $this->jsonBytes($structured['alignment_tally_delta'] ?? null),
            'branch_metadata_bytes' => $this->jsonBytes([
                'is_climactic_choice' => $structured['is_climactic_choice'] ?? null,
                'defining_choice_id' => $structured['defining_choice_id'] ?? null,
                'defining_path_label' => $structured['defining_path_label'] ?? null,
                'defining_choice_line' => $structured['defining_choice_line'] ?? null,
            ]),
            'memory_update_bytes' => $this->jsonBytes([
                'symbolic_memory_update' => $structured['symbolic_memory_update'] ?? null,
                'session_memory_update' => $structured['session_memory_update'] ?? null,
            ]),
        ];
    }

    private function jsonBytes(mixed $value): int
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? mb_strlen($json, '8bit') : 0;
    }

    /**
     * @param  array{provider:string, temperature?:float, reasoning_effort:?string}  $config
     * @return array<string, mixed>
     */
    private function providerOptionsFor(array $config): array
    {
        if ($config['provider'] === 'openai') {
            $options = ['schema' => ['strict' => true]];

            if (! empty($config['reasoning_effort'])) {
                $options['reasoning'] = ['effort' => $config['reasoning_effort']];
            }

            return $options;
        }

        return [];
    }
}
