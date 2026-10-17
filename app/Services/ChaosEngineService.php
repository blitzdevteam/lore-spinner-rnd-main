<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Adaptation\ReintroTracker;
use App\Ai\Agents\Chaos\ChaosNarrationSchema;
use App\Ai\Agents\Chaos\NarrationToolProperty;
use App\Ai\Agents\Chaos\NativeObjectSchema;
use App\Models\Game;
use App\Models\PlayableMoment;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Services\Adaptation\SessionEventLoader;
use App\Services\Runtime\StreamingJsonResponseExtractor;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
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
        'claude-opus-5-5' => ['provider' => 'anthropic', 'reasoning_effort' => null],
        'claude-opus-5' => ['provider' => 'anthropic', 'reasoning_effort' => null],
        'claude-opus-4-8' => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-opus-4-7' => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-sonnet-5' => ['provider' => 'anthropic', 'reasoning_effort' => null],
        'claude-sonnet-4-6' => ['provider' => 'anthropic', 'temperature' => 0.9,  'reasoning_effort' => null],
        'claude-haiku-5-5' => ['provider' => 'anthropic', 'reasoning_effort' => null],
        'claude-haiku-4-5' => ['provider' => 'anthropic', 'temperature' => 0.95, 'reasoning_effort' => null],
    ];

    /** Alternate production narrator: 'claude-sonnet-4-6'. */
    public const DEFAULT_MODEL = 'claude-haiku-4-5';

    /**
     * These models return 400 unless temperature, top_p, and top_k are omitted.
     * Haiku 5.5 rejects every temperature other than 1, including the 0.7 used
     * for Haiku 4.5. Thinking stays at the provider default (adaptive, medium).
     *
     * @var list<string>
     */
    public const MODELS_WITHOUT_SAMPLING = [
        'claude-opus-5-5',
        'claude-sonnet-5',
        'claude-opus-5',
        'claude-opus-4-8',
        'claude-opus-4-7',
        'claude-haiku-5-5',
    ];

    /**
     * Production /user/games narration. Sent on every turn whose model accepts
     * sampling parameters. Haiku 4.5 does. Haiku 5.5, Sonnet 5, and Opus 4.7+ do not.
     */
    public const GAME_TEMPERATURE = 0.7;

    /**
     * Heading of the end-of-prompt section that carries every per-turn block.
     * The Anthropic cache breakpoint sits immediately before it.
     */
    public const LIVE_TURN_CONTEXT_MARKER = '### LIVE TURN CONTEXT';

    private const OPENAI_FALLBACK_MODEL = 'gpt-5.2';

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

    private const SESSION_SCOPED_STATE_FIELDS = ['object_states', 'relationship_updates', 'world_flags', 'notes', 'conditions'];

    private const HISTORY_MAX_ENTRIES = 60;

    private const HISTORY_RELEVANT_ENTRIES = 8;

    /** State the runtime writes itself; the narrator's delta never touches it. */
    private const RUNTIME_OWNED_STATE_FIELDS = ['history', 'reintroduced_facts'];

    private const PREVIOUS_ENDING_MAX_CHARS = 1800;

    /**
     * Per-turn injection caps for the append-only lists. Storage keeps every
     * entry; only the always-loaded view is bounded, because an unbounded view
     * turns every remembered detail into something the narrator re-mentions.
     */
    private const TIER1_RECENT_KNOWLEDGE = 12;

    private const TIER1_RECENT_NOTES = 12;

    private const TIER1_RECENT_PLAYER_STYLE = 4;

    private const TIER2_BACKGROUND_ENTRIES = 12;

    /**
     * Of the Tier 1 knowledge and notes shown each turn, how many are simply the
     * latest. The rest of the cap goes to older entries relevant to this turn,
     * so an early fact returns when the story reaches for it again.
     */
    private const TIER1_ALWAYS_RECENT = 6;

    /** Ledger entries shown on a full (Tier 3) load. Storage keeps all of them. */
    private const TIER3_LEDGER_ENTRIES = 20;

    /** Share of meaningful words two memory lines must have in common to count as one fact. */
    private const MEMORY_SAME_THRESHOLD = 0.6;

    /** Player-style observations are short, so a looser overlap already means the same pattern. */
    private const PLAYER_STYLE_SAME_THRESHOLD = 0.5;

    /** Capitalised at the start of a line without naming anyone. */
    private const MEMORY_SENTENCE_OPENERS = [
        'the', 'she', 'her', 'hers', 'his', 'him', 'they', 'you', 'our', 'its',
        'and', 'but', 'now', 'yet', 'not', 'has', 'had', 'was', 'are', 'can',
        'will', 'still', 'already', 'player', 'protagonist', 'narrator',
    ];

    /** Words that cannot on their own make two narrations sound repetitive. */
    private const PHRASING_FILLER = [
        'said', 'says', 'asked', 'into', 'from', 'with', 'that', 'this', 'there',
        'then', 'their', 'your', 'have', 'were', 'what', 'when', 'which',
    ];

    /**
     * Function words excluded when comparing memory lines. Words
     * shorter than four characters are dropped separately, so this list
     * only needs the longer connectives that would otherwise manufacture
     * false stems.
     */
    private const MEMORY_STEM_STOPWORDS = [
        'about', 'after', 'again', 'against', 'along', 'also', 'among',
        'around', 'because', 'been', 'before', 'being', 'below',
        'between', 'both', 'during', 'each', 'else', 'even', 'ever',
        'every', 'from', 'have', 'here', 'into', 'itself', 'just',
        'less', 'like', 'made', 'make', 'many', 'more', 'most', 'much',
        'must', 'near', 'next', 'once', 'only', 'onto', 'other', 'over',
        'same', 'some', 'such', 'than', 'that', 'their', 'them', 'then',
        'there', 'these', 'they', 'this', 'those', 'through', 'toward',
        'under', 'until', 'upon', 'very', 'what', 'when', 'where',
        'which', 'while', 'with', 'within', 'without', 'would', 'your',
        'kind', 'wants', 'trying', 'feels', 'keeps', 'seems', 'looks',
        'knows',
    ];

    private const SPARSE_STATE_DELTA_CONTRACT = <<<'TEXT'
RUNTIME STATE OUTPUT (SPARSE, ACCURATE, NON-DESTRUCTIVE):
- In state_delta, null means unchanged. Never repeat unchanged world state.
- conditions, items, and unresolved_promises are complete replacement lists only when they changed; [] explicitly clears the list.
- object_states, relationship_updates, and world_flags contain changed keyed entries only. Reuse the existing key when a tracked thing changes ("Stable door: forced open" replaces "Stable door: locked"), so the old value is superseded instead of kept beside the new one.
- knowledge, notes, player_style, and emotional_ledger_entries contain new entries only.
- ITEMS ARE CONTEXTUAL: if an item lives inside a container (a suit pocket, a bag, a drawer), report the container's state alongside the item. "suit (with match in pocket)" when worn, "suit" when taken off, "suit (with match in pocket)" when put back on. The world remembers what a container holds; taking the container off does not destroy its contents.
- If uncertain whether state changed, return null. Narration must continue; state bookkeeping must never block play.

MEMORY ACCURACY (THE WORLD REMEMBERS WHAT IS TRUE):
- The state_delta is the world's memory. An inaccurate memory is worse than no memory: it makes the world confidently wrong. Before you write a field, ask: is this actually true of what just happened, or is it what I assumed happened?
- OBJECTS: track what the player took, dropped, used, broke, gave away, or had taken from them. An item the player no longer has must be removed from items. An item whose state changed (broken, loaded, empty, lit, soaked) must be updated, not re-added as a duplicate.
- LOCATIONS: the location field is where the protagonist physically IS right now. If they moved, it changed. If they did not move, it did not. A stale location makes the world describe the wrong room.
- CHARACTERS AND NPCs: relationship_updates reflect how someone's feelings toward the protagonist changed THIS turn. A relationship that did not change is not in the delta.
- CLAIMS ARE NOT FACTS: when a character asserts something the world has not shown (a magical property, a past event, a rumour), record it as that character's claim ("the innkeeper claims the ring is cursed"), never as a world fact.
- SENSATIONS ARE NOT POWERS: a warm, heavy, or glowing object is a description. Do not record a new ability or rule for it unless the source material establishes one.
- PLAYER STYLE: record what the player actually does, in plain behavioural terms tied to their inputs ("asks questions before acting", "keeps returning to the ferryman"). Never personality verdicts, labels of authority or identity, or anything the narration did on their behalf. One current entry per pattern; a changed pattern replaces the old entry.
- PROMISES AND LEDGER: record a commitment only when the player actually made one. Leaving, arriving, or staying silent is not a promise.
- DROP WHAT IS NO LONGER TRUE: conditions that belonged to a scene the story has left must not survive into the next scene. A wound persists; a mood about a room that is now behind you does not. When the location changes, carry forward only what is still true of this body in this room.
- DO NOT ACCUMULATE: write the CURRENT version of an observation, not a new phrasing on top of old ones. If something is already recorded and has not changed, return nothing for it.
- SUPERSEDE WHAT CHANGED: when a recorded knowledge, note, or player-style entry stops being true, write its new version with the same names and key words as the old one ("the guard trusts the protagonist" becomes "the guard no longer trusts the protagonist"). The runtime replaces the old entry with it, so the record never holds both.
TEXT;

    /**
     * Fixed output ceiling for one narration turn (prose + suggestions + state_delta).
     * No dynamic budgeting and no in-request truncation retry — one call, one budget.
     */
    private const NARRATION_MAX_TOKENS = 8_192;

    /**
     * Haiku 5.5 thinks by default, and those tokens share this ceiling.
     * The Haiku 4.5 ceiling is sized for a reply with no thinking block.
     */
    private const HAIKU_55_NARRATION_MAX_TOKENS = 32_768;

    /** Room for a short adaptive think plus three repaired choices. */
    private const HAIKU_55_REPAIR_MAX_TOKENS = 2_048;

    /**
     * Tool the streaming narrator is forced to call. Its input_schema is the
     * narration schema, which moves JSON validity from something the model is
     * asked to honour to something the provider's decoder guarantees.
     */
    private const NARRATION_TOOL_NAME = 'emit_narration';

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

    /**
     * Resolve a stored narration model to one whose provider is configured.
     *
     * The production default remains Claude when its key is available. Local
     * and preview environments that only provide OpenAI continue with the
     * established Chaos Mode model instead of issuing an unauthenticated
     * Anthropic request.
     */
    public function modelForConfiguredProvider(string $model): string
    {
        if ($this->modelHasConfiguredProvider($model)) {
            return $model;
        }

        foreach ([self::DEFAULT_MODEL, self::OPENAI_FALLBACK_MODEL] as $candidate) {
            if ($this->modelHasConfiguredProvider($candidate)) {
                return $candidate;
            }
        }

        return $model;
    }

    public function defaultGameModel(): string
    {
        return $this->modelForConfiguredProvider(self::DEFAULT_MODEL);
    }

    /**
     * Model stored on a new game, and written back when that game is reset.
     * Peter Pan is the Haiku 5.5 trial. Every other title stays on Haiku 4.5.
     */
    public function gameModelFor(string $storySlug): string
    {
        $model = $storySlug === 'peter-pan' ? 'claude-haiku-5-5' : self::DEFAULT_MODEL;

        return $this->modelForConfiguredProvider($model);
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
            'must_reintroduce' => $entry['format_specific_cut']['must_reintroduce'] ?? '',
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
     * isEpisodeTransition=true → the opening turn of session 2+, where the carried
     *                        world state, alignment and symbolic memory cross an
     *                        episode boundary. Tier 3 loads here for the same reason
     *                        it loads after a climactic turn: the narrator needs the
     *                        full registry and history to honour what carried over.
     *
     * playerAction and recentNarrations (oldest first) steer which stored
     * memories are shown this turn and which phrasing the narrator must not
     * reuse. They never change what is stored.
     *
     * @param  array<string, mixed>  $sessionContext
     * @param  array<string, mixed>  $worldState
     * @param  array{chaotic:int, lawful:int, neutral:int}  $alignmentScaffold
     * @param  array<int, string>  $recentNarrations
     */
    public function renderSystemPrompt(
        array $sessionContext,
        array $worldState,
        array $alignmentScaffold,
        ?string $symbolicMemory,
        bool $isSessionStart,
        bool $isClimacticPrevious,
        bool $isClimacticThisTurn = false,
        bool $isEpisodeTransition = false,
        ?string $playerAction = null,
        array $recentNarrations = [],
        bool $isContinue = false,
    ): string {
        $prompt = $sessionContext['runtime_prompt'];

        $symbolicBlock = mb_trim((string) $symbolicMemory) !== ''
            ? "(A RECORD OF WHAT THE PROTAGONIST HAS DONE AND PROMISED. Honor it. NEVER reuse its wording or images in the narration.)\n"
                .mb_trim((string) $symbolicMemory)
            : '(Nothing recorded yet.)';

        $alignmentBlock = $this->renderAlignmentTilt(
            (array) ($sessionContext['alignment_labels'] ?? []),
            $alignmentScaffold,
        );

        // Memory relevance is scoped to the whole session, not the active room.
        // Script containment is a separate concern, already enforced by the active
        // moment injection and the Boiler session map — narrowing this haystack to
        // one scene card only demoted everything the player did earlier in the
        // session to background, which is the opposite of a world that remembers.
        $recentNarrations = array_values(array_filter(
            array_map(static fn (mixed $text): string => mb_trim((string) $text), $recentNarrations),
            static fn (string $text): bool => $text !== '',
        ));
        $lastNarration = $recentNarrations === [] ? '' : $recentNarrations[array_key_last($recentNarrations)];
        $activeContext = (array) ($sessionContext['active_moment_context'] ?? []);
        $focusText = implode(' ', [
            (string) $playerAction,
            $lastNarration,
            (string) ($activeContext['exit_condition'] ?? ''),
            (string) data_get($activeContext, 'moment.title', ''),
        ]);

        $worldStateBlock = $this->renderTieredWorldState(
            worldState: $worldState,
            isClimactic: $isClimacticPrevious || $isEpisodeTransition,
            sessionEvents: (array) ($sessionContext['full_session_events'] ?? []),
            focusText: $focusText,
            lastNarration: $lastNarration,
        );

        // On session-start: inject the full opening block into Section 13 via its token.
        // On continuation: mechanically strip the entire Section 13 block (header + "THIS IS THE
        // HARD START" instructions + FIRST-3-MINUTES PROTOCOL + injection point) so those
        // narrator instructions never reach the model mid-session. The LLM does not see them;
        // it is not asked to self-suppress them. strtr still processes the other three tokens
        // normally; [OPENING_SCENE_INJECTION_POINT] simply goes unused on continuation paths.
        $consequenceBlock = $this->buildConsequenceEchoBlock(
            $sessionContext['resolved_consequence_path'] ?? null,
            (int) ($sessionContext['session_number'] ?? 1),
        );

        $continueAtFinalCard = $isContinue && ! $isSessionStart && (bool) ($activeContext['is_final_card'] ?? false);
        $closeBlock = $isClimacticThisTurn || $continueAtFinalCard
            ? $this->buildSessionCloseBlock(
                $sessionContext['session_close_design'] ?? null,
                endingChoiceDeclined: $continueAtFinalCard && (bool) ($activeContext['card_choice_presented'] ?? false),
            )
            : '';
        $activeMomentBlock = $this->buildActiveMomentBlock(
            $sessionContext['active_moment_context'] ?? null,
            $isSessionStart,
            $isContinue,
        );

        $liveBlocks = [
            '[WORLD_STATE_TIERED_INJECTION_POINT]' => ['PERSISTENT STATE (Section 7)', $worldStateBlock],
            '[SYMBOLIC_MEMORY_INJECTION_POINT]' => ['SYMBOLIC MEMORY (Section 8)', $symbolicBlock],
            '[ALIGNMENT_TILT_INJECTION_POINT]' => ['ALIGNMENT TILT (Section 9)', $alignmentBlock],
            '[CONSEQUENCE_ECHO_INJECTION_POINT]' => ['CONSEQUENCE ECHO (Section 10A)', $consequenceBlock],
            '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]' => ['ACTIVE PLAYABLE MOMENT (Section 12)', $activeMomentBlock],
            '[SESSION_CLOSE_INJECTION_POINT]' => ['AUTHORED SESSION CLOSE (Section 16)', $closeBlock],
        ];

        if ($isSessionStart) {
            $prompt = strtr($prompt, [
                '[OPENING_SCENE_INJECTION_POINT]' => $this->buildOpeningSection($sessionContext, $worldState),
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
                    .'(Continuation turn: opening already delivered. Resume from conversation history; do not re-cold-open.)'
                    .mb_substr($prompt, $injPos + mb_strlen($injToken));
            }
        }

        return $this->withLiveTurnContext($prompt, $liveBlocks, [
            'RECENT PHRASING (already used in your last turns; do not reuse any of it)' => $isSessionStart
                ? ''
                : $this->recentPhrasingBlock($recentNarrations),
        ]);
    }

    /**
     * Append the static sparse-state contract above the live section so it
     * stays inside the cached prefix.
     */
    public function withSparseStateDeltaContract(string $systemPrompt): string
    {
        $liveOffset = mb_strpos($systemPrompt, self::LIVE_TURN_CONTEXT_MARKER);

        if ($liveOffset === false) {
            return $systemPrompt."\n\n".self::SPARSE_STATE_DELTA_CONTRACT;
        }

        return mb_substr($systemPrompt, 0, $liveOffset)
            .self::SPARSE_STATE_DELTA_CONTRACT."\n\n"
            .mb_substr($systemPrompt, $liveOffset);
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

        return [
            ...array_merge($matchedPath, $freeform ?? []),
            'choice_session_number' => $this->extractSessionNumber($definingChoiceId),
        ];
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
    public function sparseStateDeltaContract(): string
    {
        return self::SPARSE_STATE_DELTA_CONTRACT;
    }

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
        $systemPrompt = $this->withSparseStateDeltaContract($systemPrompt);
        $boilerActive = $game?->current_playable_moment_id !== null;

        $messages = $this->conversationHistoryToMessages(
            $conversationHistory,
            $playerAction,
            $protagonist,
            $isOpening,
        );

        $schemaArray = ChaosNarrationSchema::definition(new JsonSchemaTypeFactory, $boilerActive);
        $providerOptions = $this->providerOptionsFor($config);

        // The browser response has already been sent before production game
        // narration reaches this call. Do not reuse Cloudflare's HTTP timeout
        // as the model timeout: Anthropic structured responses are buffered,
        // so curl receives no bytes until the complete object is ready.
        $request = Prism::structured()
            ->using($config['provider'], $model)
            ->withSchema(new NativeObjectSchema($schemaArray))
            ->withMessages($messages)
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
            $request = $request->withMaxTokens($this->narrationMaxTokens($model));
        }

        $response = $request->asStructured();

        /** @var array<string, mixed> $structured */
        $structured = $response->structured ?? [];

        $usage = $this->normalizeProviderUsage(
            $response->usage->toArray(),
            $config['provider'],
            streamed: false,
        );
        [$structured, $repairUsage] = $this->repairMissingLegacyChoices(
            structured: $structured,
            model: $model,
            config: $config,
            promptText: $this->flattenMessagesForRepair($messages),
            playerAction: $playerAction,
            protagonist: $protagonist,
            game: $game,
            streamed: false,
        );
        $usage = $this->mergeUsage($usage, $repairUsage);

        return $this->narrationResultFromStructured(
            structured: $structured,
            usage: $usage,
            game: $game,
            isOpening: $isOpening,
        );
    }

    /**
     * Stream the Chaos narration object as a forced tool call, emit decoded
     * "response" field deltas for UX, then hand the complete object to the
     * existing commit pipeline.
     *
     * The narration schema is attached as the tool's input_schema and the
     * provider is told it must call that tool, so the JSON is produced by the
     * provider's constrained decoder rather than by the model choosing to obey
     * a formatting instruction. String escaping, brace balance, and property
     * presence stop being things the narrator can get wrong.
     *
     * @param  array<int, array{role:string, text:string}>  $conversationHistory
     * @param  callable(string): void  $onResponseDelta
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
    public function callAgentStreaming(
        string $model,
        string $systemPrompt,
        array $conversationHistory,
        ?string $playerAction,
        string $protagonist,
        callable $onResponseDelta,
        float $temperature = 1.0,
        array $worldState = [],
        ?Game $game = null,
        bool $isOpening = false,
    ): array {
        $config = self::MODEL_CONFIG[$model] ?? self::MODEL_CONFIG[self::DEFAULT_MODEL];
        $systemPrompt = $this->withSparseStateDeltaContract($systemPrompt);
        $boilerActive = $game?->current_playable_moment_id !== null;

        $messages = $this->conversationHistoryToMessages(
            $conversationHistory,
            $playerAction,
            $protagonist,
            $isOpening,
        );

        $streamingContract = implode("\n", [
            'STREAMING OUTPUT CONTRACT:',
            'Deliver this turn by calling the '.self::NARRATION_TOOL_NAME.' tool. Never answer in plain prose.',
            'Fill "response" first so the UI can stream it while the rest of the object is still generating.',
            ! $boilerActive
                ? 'LEGACY GAMEPLAY REQUIREMENT: when "session_complete" is false, "choices" MUST contain exactly three non-empty suggested actions. Only a completed session may return "choices": []. "visible_suggestions" MUST be [].'
                : 'BOILER GAMEPLAY REQUIREMENT: "choices" MUST be []. Fill visible_suggestions.option_1, option_2, and option_3 on EVERY response, including every Continue and a response that completes the session. At an authored decision map the exact A/B/C options. At every other handoff emit three contextual freeform actions with empty canonical identity.',
        ]);

        $providerOptions = $this->providerOptionsFor($config);
        if (($config['provider'] ?? null) === 'openai') {
            unset($providerOptions['schema']);
        }

        $request = Prism::text()
            ->using($config['provider'], $model)
            ->withMessages($messages)
            ->withTools([$this->narrationTool($boilerActive)])
            ->withToolChoice(self::NARRATION_TOOL_NAME)
            ->withMaxSteps(1)
            ->withClientOptions(['timeout' => 150, 'connect_timeout' => 10])
            ->withProviderOptions($providerOptions);

        $fullSystemPrompt = $systemPrompt."\n\n".$streamingContract;
        if ($config['provider'] === 'anthropic') {
            $request = $request->withSystemPrompts(
                $this->anthropicSystemPrompts($fullSystemPrompt),
            );
        } else {
            $request = $request->withSystemPrompt($fullSystemPrompt);
        }

        if ($this->modelAcceptsSamplingParams($model)) {
            $request = $request->usingTemperature($temperature);
        }

        if ($config['provider'] === 'anthropic') {
            $request = $request->withMaxTokens($this->narrationMaxTokens($model));
        }

        $text = '';
        $toolJson = '';
        $usage = [];
        $structured = null;
        $finishReason = null;
        $streamedResponse = false;
        $responseExtractor = new StreamingJsonResponseExtractor;

        foreach ($request->asStream() as $event) {
            if ($event instanceof ToolCallDeltaEvent) {
                if ($event->toolName !== '' && $event->toolName !== self::NARRATION_TOOL_NAME) {
                    continue;
                }

                $toolJson .= $event->delta;
                $delta = $responseExtractor->push($event->delta);
                if ($delta !== '') {
                    $streamedResponse = true;
                    $onResponseDelta($delta);
                }

                continue;
            }

            if ($event instanceof ToolCallEvent) {
                if ($event->toolCall->name === self::NARRATION_TOOL_NAME) {
                    $structured = $event->toolCall->arguments();
                }

                continue;
            }

            // Retained only as evidence for the contract-violation log below:
            // a forced tool call means prose here is never the turn's payload.
            if ($event instanceof TextDeltaEvent) {
                $text .= $event->delta;

                continue;
            }

            if ($event instanceof StreamEndEvent) {
                $finishReason = $event->finishReason->name;
                if ($event->usage !== null) {
                    $usage = $event->usage->toArray();
                }
            }
        }

        if (! is_array($structured) || $structured === []) {
            Log::channel('narration')->error('game.narration_tool_call_missing', [
                'game_id' => $game?->id,
                'model' => $model,
                'provider' => $config['provider'],
                'finish_reason' => $finishReason,
                'tool_json_bytes' => mb_strlen($toolJson, '8bit'),
                'tool_json_head' => mb_substr($toolJson, 0, 500),
                'text_bytes' => mb_strlen($text, '8bit'),
                'text_head' => mb_substr($text, 0, 500),
            ]);

            throw new RuntimeException('Streaming narration did not return the narration tool call.');
        }

        // Providers that deliver the tool input in one piece rather than as
        // incremental JSON never reach the extractor, so the player would sit
        // on a blank pane until the turn commits.
        if (! $streamedResponse) {
            $response = (string) ($structured['response'] ?? '');
            if ($response !== '') {
                $onResponseDelta($response);
            }
        }

        $usage = $this->normalizeProviderUsage(
            $usage,
            $config['provider'],
            streamed: true,
        );
        [$structured, $repairUsage] = $this->repairMissingLegacyChoices(
            structured: $structured,
            model: $model,
            config: $config,
            promptText: $this->flattenMessagesForRepair($messages),
            playerAction: $playerAction,
            protagonist: $protagonist,
            game: $game,
            streamed: true,
        );
        $usage = $this->mergeUsage($usage, $repairUsage);

        return $this->narrationResultFromStructured(
            structured: $structured,
            usage: $usage,
            game: $game,
            isOpening: $isOpening,
        );
    }

    /**
     * Merge a state_delta into persisted world_state.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $delta
     * @return array{0:array<string, mixed>,1:array<string, mixed>}
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
                ? $this->appendUniqueStringList(
                    (array) ($previous[$key] ?? []),
                    $value,
                    $key === 'player_style' ? self::PLAYER_STYLE_SAME_THRESHOLD : self::MEMORY_SAME_THRESHOLD,
                )
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

        foreach (self::RUNTIME_OWNED_STATE_FIELDS as $key) {
            if (array_key_exists($key, $previous)) {
                $merged[$key] = $previous[$key];
            }
        }

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

        // Any line that says the same thing as the update is superseded by it.
        // The new line is more recent and more earned, so it replaces the stale
        // variant instead of accumulating beside it. This stops the "Emmit is
        // the kind of man who..." cascade where five phrasings of one
        // observation are fed back as authoritative guidance next turn.
        $lines = array_values(array_filter(
            $lines,
            fn (string $line): bool => mb_trim($line) !== '' && ! $this->isSameMemory($line, $update, self::MEMORY_SAME_THRESHOLD),
        ));

        // Keep a rolling window of recent lines so the memory stays focused
        // on what the player is actually doing now, not what they did 20
        // turns ago.
        $recent = array_slice($lines, -8);

        return $recent === [] ? $update : implode("\n", $recent)."\n".$update;
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

    /**
     * Narration is stored as HTML, so `strip_tags` alone fuses paragraphs into a
     * single run-on block ("straight at you.Barking."). That plain text is fed
     * back to the narrator as its own conversation history and handed to TTS, so
     * the block boundaries have to survive as newlines.
     */
    public function stripHtml(string $html): string
    {
        $withBreaks = preg_replace(
            ['/<br\s*\/?>/i', '/<\/(?:p|div|h[1-6]|li|blockquote)>/i'],
            ["\n", "\n\n"],
            $html,
        ) ?? $html;

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_trim((string) preg_replace(['/[ \t]+\n/', '/\n{3,}/'], ["\n", "\n\n"], $text));
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
    /**
     * The closing narration this player actually received in a session, so the
     * next session's opening can be reconciled against it.
     */
    public function previousSessionEnding(Game $game, int $sessionNumber): string
    {
        if ($sessionNumber < 1) {
            return '';
        }

        $response = $game->prompts()
            ->where('session_number', $sessionNumber)
            ->whereNotNull('response')
            ->orderByDesc('id')
            ->value('response');

        $text = mb_trim($this->stripHtml((string) $response));

        return mb_strlen($text) > self::PREVIOUS_ENDING_MAX_CHARS
            ? '…'.mb_substr($text, -self::PREVIOUS_ENDING_MAX_CHARS)
            : $text;
    }

    /**
     * Move session-scoped state into history at an episode boundary.
     *
     * Object states, relationships, flags, notes and conditions describe the
     * world as the last session left it. Carried forward as live state they
     * read as the present, so a story that has moved on keeps being pulled
     * back. Items, knowledge, promises and player style stay live.
     *
     * @param  array<string, mixed>  $worldState
     * @return array<string, mixed>
     */
    public function archiveSessionState(array $worldState, int $endedSessionNumber): array
    {
        $entries = [];

        foreach (self::SESSION_SCOPED_STATE_FIELDS as $field) {
            foreach ((array) ($worldState[$field] ?? []) as $entry) {
                $text = is_scalar($entry) ? mb_trim((string) $entry) : '';
                if ($text !== '') {
                    $entries[] = "Session {$endedSessionNumber}: {$text}";
                }
            }
            $worldState[$field] = [];
        }

        $history = [...array_values((array) ($worldState['history'] ?? [])), ...$entries];
        $worldState['history'] = array_slice($history, -self::HISTORY_MAX_ENTRIES);

        return $worldState;
    }

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

    public function openingInstructions(): string
    {
        return "This is the opening of the adventure. Your narration MUST begin at the exact moment described in CURRENT OPENING SCENE, no earlier. Any events in your session script that precede that moment do not exist for this session. The player arrives already inside the action. Follow the CURRENT OPENING SCENE block's own delivery instructions on wording: where it states the text is authored and delivered verbatim, reproduce it exactly; otherwise render the opening in your own narrator voice. Either way, end on the live moment rather than a question aimed at the player. Return the full structured object.";
    }

    /**
     * Final user-message instructions for every non-opening turn. This is the
     * text closest to generation, so its examples are deliberately neutral:
     * example verbs placed here get imitated in the prose that follows.
     */
    public function turnInstructions(string $protagonistUpper, bool $isContinue = false): string
    {
        $continueLine = $isContinue
            ? "THIS IS A CONTINUE: the player wants the story told forward like the next pages of the book. Narrate the ordered moments meaningfully, several beats, compressing connective writing but never the events, facts, objects, relationships, promises, or consequences later material needs. {$protagonistUpper} may do and say what the author's pages have them do, but never invent a preference or claim the player selected an authored A/B/C path. Follow the Continue directive in the active moment exactly: it names the decision where you must slow down and stop so the player can steer, and how to report where the prose stopped.\n\n"
            : '';

        return "Now narrate the world's response to this action.\n\n".
            $continueLine.
            "RULE #1, ABOVE EVERY OTHER RULE: write in the author's prose, their diction, rhythm, and plainness, as if this were the next page of their book. Every rule below serves that one.\n\n".
            "SAY IT PLAINLY: state what happens and what people say directly, the way the author would. No riddles, hedges, or knowing half-statements. Do not tell the reader what a line is not (not a question, not a request, not boastful) and do not trail off on a fragment that implies more than it says. Characters speak in their canon voice and mean what they say on the surface.\n\n".
            "SHAPE THE AUTHOR'S TEXT, DO NOT DECORATE IT: the author already wrote what happens in this scene (the source extracts and the Voice Anchor show how). Your job is to fit that to what the player just did, in the author's own sentences. Any description, aside, or commentary must be the kind the author writes, at the rate the author writes it; nothing the author would not have written.\n\n".
            "SHOW, THEN STOP: after an action, a gesture, or a spoken line, do not explain it by comparing the character to an imagined person (\"the smile of a boy who has been waiting…\", \"with the determination of a boy who believes…\") or by saying what the moment is or is not (\"no mockery in it, only puzzlement\"). The action or line already said it. The author's own figures of speech are voice and stay, at the author's rate; a comparison that only re-explains what was just shown is not.\n\n".
            "DASHES FOLLOW THE ANCHOR CARD: where it allows dashes, use them only in the construction it names (for an interruption, a speaker breaking off mid-line). A dash in narration that restates or delays (\"something else, then a dash, then something that...\") is the connector it forbids; use a full stop or a comma.\n\n".
            "NOTHING BEFORE IT IS ESTABLISHED: never allude to a fact, ability, or object the protagonist has not yet seen or been told on the page. When something first matters (how someone arrived, what they can do, what a thing is), show or say it plainly the first time, then build on it.\n\n".
            "STOP WHEN THE ACTION IS ANSWERED: once the world has responded and the scene has moved, end. No closing paragraph that re-describes the room, a waiting door or window, or who is still asleep, and no closing summary of what the moment means. Length is fine only while every paragraph moves the story forward.\n\n".
            "KEEP THE SHAPE OF THE ACTION: its verb, its target, and its intensity stay exactly as the player gave them. A request stays a request, a question stays a question, a demand stays a demand, a refusal stays a refusal. Never upgrade a request into taking or commanding, and never soften a demand into asking. Resolve pronouns (it, that, him, her, them) against the previous narrator turn and answer that referent, not a substitute.\n\n".
            "A QUESTION IS NOT A DECISION: when the player asks what to do, asks for advice, or weighs two options, answer through the world and leave the decision open. Never narrate that they have already chosen.\n\n".
            "THE PROTAGONIST'S INNER LIFE BELONGS TO THE PLAYER: narrate what they did and said, then what the world does back. Do not invent their motives, their resolve, new decisions, or lines of dialogue they did not give.\n\n".
            "WRITE FORWARD: each sentence delivers something the player has not already been told. Do not re-mention what they are holding, who is still asleep, where they are, or what was already done, unless it changed this turn or this action depends on it. Do not retell or interpret a letter, speech, or sight the player has just taken in. Do not close on what the moment means or who the protagonist is becoming.\n\n".
            "Move the scene forward by however much the action deserves. End on the live moment (an image, a gesture, a spoken line) and not on a question aimed at the player.\n\n".
            "HARD CHECK BEFORE YOU RETURN. THESE WERE BROKEN IN RECENT TURNS AND ARE NOT OPTIONAL. Reread your narration and fix every hit:\n".
            "1. ANY \"[noun] of a [person] who...\" OR \"like someone who...\" GLOSS on a look, smile, voice, or tone: DELETE IT. Keep the action or the line alone.\n".
            "2. ANY SENTENCE, IMAGE, OR SAYING YOU ALREADY WROTE IN AN EARLIER TURN: CUT IT OR SAY SOMETHING NEW.\n".
            "3. ANY RULE, LAW, OR FACT ABOUT THE WORLD THAT IS NOT IN THE SOURCE OR ALREADY ON THE PAGE: REMOVE IT. DO NOT INVENT CANON.\n".
            "4. ANY CLOSING LINE ABOUT WHAT THE MOMENT MEANS OR WHO SOMEONE IS BECOMING: DELETE IT.\n".
            ($isContinue ? "5. CONTINUE: KEEP THE IMPORTANT STORY, COMPRESS THE CONNECTIVE WRITING, STOP AT THE NAMED DECISION, AND REPORT WHERE THE PROSE ACTUALLY STOPPED.\n" : '').
            "\nReturn the full structured object.";
    }

    /**
     * Gather every per-turn block into one section at the end of the prompt.
     *
     * Each injection token becomes a fixed pointer, so everything above the
     * live section is byte-identical from turn to turn and can sit inside the
     * Anthropic cache breakpoint (see anthropicSystemPrompts()). The live
     * section replaces the world-state token, which the template already
     * places last; templates without it get the section appended.
     *
     * Extra sections have no template token; each is appended only when it
     * has content.
     *
     * @param  array<string, array{0:string, 1:string}>  $liveBlocks
     * @param  array<string, string>  $extraSections
     */
    private function withLiveTurnContext(string $prompt, array $liveBlocks, array $extraSections = []): string
    {
        $pointers = [];
        $sections = [];

        foreach ($liveBlocks as $token => [$label, $content]) {
            if (! str_contains($prompt, $token)) {
                continue;
            }

            $content = mb_trim($content);
            $sections[] = "LIVE: {$label}\n".($content !== '' ? $content : '(none this turn)');

            if ($token !== '[WORLD_STATE_TIERED_INJECTION_POINT]') {
                $pointers[$token] = "(Live this turn: see {$label} in the live turn context at the end of this prompt.)";
            }
        }

        foreach ($extraSections as $label => $content) {
            $content = mb_trim($content);
            if ($content !== '') {
                $sections[] = "LIVE: {$label}\n{$content}";
            }
        }

        $liveSection = self::LIVE_TURN_CONTEXT_MARKER." [RUNTIME: changes every turn]\n\n"
            .'Everything below is this turn\'s live data for the sections that point here. It outranks any older description of the same thing above.'
            ."\n\n".implode("\n\n", $sections);

        $prompt = strtr($prompt, $pointers);

        if (str_contains($prompt, '[WORLD_STATE_TIERED_INJECTION_POINT]')) {
            return str_replace('[WORLD_STATE_TIERED_INJECTION_POINT]', $liveSection, $prompt);
        }

        return $prompt."\n\n".$liveSection;
    }

    /**
     * Repair only missing legacy choices with a small structured call while
     * preserving the narration already produced by the primary request. This
     * covers both streamed turns and buffered opening narrations: native output
     * guarantees the choices field exists, but cannot express the conditional
     * rule that an incomplete legacy turn must contain exactly three choices.
     *
     * @param  array<string, mixed>  $structured
     * @param  array{provider:string, temperature?:float, reasoning_effort:?string}  $config
     * @return array{0:array<string, mixed>,1:array<string, mixed>}
     */
    private function repairMissingLegacyChoices(
        array $structured,
        string $model,
        array $config,
        string $promptText,
        ?string $playerAction,
        string $protagonist,
        ?Game $game,
        bool $streamed,
    ): array {
        if ($game?->current_playable_moment_id !== null) {
            return [$structured, []];
        }

        if (($structured['session_complete'] ?? false) === true) {
            return [$structured, []];
        }

        $choices = $structured['choices'] ?? null;
        if ($this->hasThreeLegacyChoices($choices)) {
            return [$structured, []];
        }

        $narration = $structured['response'] ?? '';
        $narration = is_string($narration) ? $narration : '';

        $schema = new JsonSchemaTypeFactory;
        $repairRequest = Prism::structured()
            ->using($config['provider'], $model)
            ->withSchema(new NativeObjectSchema([
                'choices' => $schema->array()
                    ->required()
                    ->items($schema->string()->required()),
            ], 'narration_choices_repair'))
            ->withSystemPrompt(implode("\n", [
                'Generate exactly three concise, distinct actions the player can take next.',
                'Ground every action in the supplied narration and player context.',
                'Start each action with a strong verb. Do not repeat the narration.',
            ]))
            ->withPrompt(implode("\n\n", [
                "PROTAGONIST: {$protagonist}",
                'PLAYER ACTION: '.($playerAction ?? 'Continue the story forward.'),
                'TURN CONTEXT:',
                $promptText,
                'COMPLETED NARRATION:',
                $narration,
            ]))
            ->withClientOptions(['timeout' => 45, 'connect_timeout' => 10])
            ->withProviderOptions($this->providerOptionsFor($config));

        if ($this->modelAcceptsSamplingParams($model)) {
            $repairRequest = $repairRequest->usingTemperature(0.4);
        }

        if ($config['provider'] === 'anthropic') {
            $repairRequest = $repairRequest->withMaxTokens($this->repairMaxTokens($model));
        }

        $repairResponse = $repairRequest->asStructured();
        $repaired = $repairResponse->structured['choices'] ?? null;
        if (! $this->hasThreeLegacyChoices($repaired)) {
            throw new RuntimeException('Narration did not return three valid gameplay choices.');
        }

        Log::channel('narration')->warning(
            $streamed ? 'game.streaming_choices_repaired' : 'game.buffered_choices_repaired',
            [
                'game_id' => $game?->id,
                'model' => $model,
            ],
        );

        $structured['choices'] = array_values(array_map(static function (mixed $choice): string {
            return mb_trim($choice);
        }, $repaired));

        return [
            $structured,
            $this->normalizeProviderUsage(
                $repairResponse->usage->toArray(),
                $config['provider'],
                streamed: false,
            ),
        ];
    }

    private function hasThreeLegacyChoices(mixed $choices): bool
    {
        if (! is_array($choices) || count($choices) !== 3) {
            return false;
        }

        foreach ($choices as $choice) {
            if (! is_string($choice) || mb_trim($choice) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $structured
     * @param  array<string, mixed>  $usage
     * @return array<string, mixed>
     */
    private function narrationResultFromStructured(
        array $structured,
        array $usage,
        ?Game $game,
        bool $isOpening,
    ): array {
        $alignment = (array) ($structured['alignment_tally_delta'] ?? []);

        $result = [
            'response' => (string) ($structured['response'] ?? ''),
            'choices' => (array) ($structured['choices'] ?? []),
            'visible_suggestions' => array_key_exists('visible_suggestions', $structured)
                ? $this->visibleSuggestionsFromStructured($structured['visible_suggestions'])
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
            '_usage' => $usage,
            '_output_metrics' => $this->structuredOutputMetrics($structured),
        ];

        $this->validateBoilerNarrationResult($result, $game, $isOpening);

        return $result;
    }

    /**
     * Convert the model-facing fixed Boiler object into the existing ordered
     * array consumed by storage and the UI. Legacy responses already use [].
     *
     * @return array<int, mixed>|null
     */
    private function visibleSuggestionsFromStructured(mixed $suggestions): ?array
    {
        if (! is_array($suggestions)) {
            return null;
        }

        if (array_key_exists('option_1', $suggestions)
            && array_key_exists('option_2', $suggestions)
            && array_key_exists('option_3', $suggestions)) {
            return [
                $suggestions['option_1'],
                $suggestions['option_2'],
                $suggestions['option_3'],
            ];
        }

        return array_values($suggestions);
    }

    /**
     * Build the narration tool from the same schema the buffered structured
     * call uses, so streaming and non-streaming turns can never drift apart.
     *
     * The handler is never meaningfully invoked: maxSteps is 1, so Prism runs
     * it once to close the step and then ends the stream instead of sending
     * the result back for a second, billable round trip.
     */
    private function narrationTool(bool $boilerActive): Tool
    {
        $schema = (new NativeObjectSchema(
            ChaosNarrationSchema::definition(new JsonSchemaTypeFactory, $boilerActive),
        ))->toArray();

        /** @var array<string, array<string, mixed>> $properties */
        $properties = $schema['properties'] ?? [];
        /** @var array<int, string> $required */
        $required = $schema['required'] ?? [];

        $tool = (new Tool)
            ->as(self::NARRATION_TOOL_NAME)
            ->for('Deliver one complete Chaos narration turn.')
            ->using(static fn (): string => '');

        foreach ($properties as $name => $property) {
            $tool->withParameter(
                new NarrationToolProperty($name, $property),
                in_array($name, $required, true),
            );
        }

        return $tool;
    }

    private function normalizeMemoryLine(string $line): string
    {
        $line = mb_strtolower(mb_trim($line));
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return $line;
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
        $branchingIds = collect((array) data_get($session->session_choice_design, 'branching_choices', []))
            ->map(static fn ($choice): string => mb_strtolower((string) data_get($choice, 'choice_id', '')))
            ->filter()
            ->all();
        $cardBranchingIds = array_values(array_filter(
            array_map(static fn ($id): string => (string) $id, (array) data_get($card, 'choice_ids', [])),
            static fn (string $id): bool => $id !== '' && in_array(mb_strtolower($id), $branchingIds, true),
        ));
        $committedChoiceId = mb_strtolower(mb_trim((string) $game->defining_choice_id));
        $pendingBranchingIds = in_array($committedChoiceId, array_map('mb_strtolower', $cardBranchingIds), true)
            ? []
            : $cardBranchingIds;
        $authoredOptions = array_values(array_filter(
            $authoredOptions,
            static fn (array $choice): bool => mb_strtolower($choice['choice_id']) !== $committedChoiceId,
        ));

        // Detour accounting rides along in the per-moment footnote blob, so it
        // resets naturally whenever the pointer moves to a new room. Detour
        // allowance is deliberately independent of turn_count: engaging deeply
        // with a room must never cost the player the right to improvise later.
        $detourBeatsUsed = max(0, (int) ($footnote['detour_beats_used'] ?? 0));
        $presentedChoiceIds = $this->presentedChoiceIds($game);
        $cardChoiceIds = array_map(static fn ($id): string => mb_strtolower((string) $id), (array) data_get($card, 'choice_ids', []));

        return [
            'moment' => $card,
            'moment_key' => $moment->moment_key,
            'turn_count' => $turnCount,
            'target_turns' => $targetTurns,
            'gravity_after' => $gravityAfter,
            'pacing_state' => $pacingState,
            'target_words_per_turn' => (int) data_get($card, 'word_budget.target_per_turn', 0),
            'previous_turn_words' => $this->lastNarrationWordCount($game),
            'exit_condition' => (string) ($card['exit_condition'] ?? ''),
            'forbidden_expansions' => (array) ($card['forbidden_expansions'] ?? []),
            'next_bridge' => (string) data_get($card, 'next_bridge', ''),
            'authored_options' => $authoredOptions,
            'pending_branching_choice_ids' => $pendingBranchingIds,
            'treasure_insight' => $footnote['text'] ?? null,
            'next_moment_preview' => $preview,
            'detour_beats_used' => $detourBeatsUsed,
            'detour_beats_remaining' => max(0, self::DETOUR_BEAT_ALLOWANCE - $detourBeatsUsed),
            'is_final_card' => $nextMoment === null,
            'card_choice_presented' => array_intersect($cardChoiceIds, $presentedChoiceIds) !== [],
            'continue_decision_stop' => $this->nextContinueDecisionStop($session, $moment, $branchingIds, $committedChoiceId),
        ];
    }

    /**
     * Canonical choice ids on screen right now: the options attached to the
     * newest response, which is the one the player is answering.
     *
     * @return array<int, string>
     */
    private function presentedChoiceIds(Game $game): array
    {
        $choices = (array) ($game->prompts()->orderByDesc('id')->first(['id', 'choices'])?->choices ?? []);

        return array_values(array_filter(array_map(
            static fn ($choice): string => is_array($choice) ? mb_strtolower((string) ($choice['canonical_choice_id'] ?? '')) : '',
            $choices,
        )));
    }

    /**
     * The next later card where Continue must slow down: one that carries an
     * authored branching choice the player has not yet taken.
     *
     * @param  array<int, string>  $branchingIds
     * @return array{moment_key:string, title:string, choice_ids:array<int, string>, options:array<int, mixed>}|null
     */
    private function nextContinueDecisionStop(
        SessionAdaptation $session,
        PlayableMoment $moment,
        array $branchingIds,
        string $committedChoiceId,
    ): ?array {
        if ($branchingIds === []) {
            return null;
        }

        $laterMoments = PlayableMoment::query()
            ->where('playable_session_cut_id', $moment->playable_session_cut_id)
            ->where('moment_order', '>', $moment->moment_order)
            ->orderBy('moment_order')
            ->get();

        foreach ($laterMoments as $later) {
            $choiceIds = array_values(array_filter(
                array_map(static fn ($id): string => (string) $id, (array) data_get($later->scene_card, 'choice_ids', [])),
                static fn (string $id): bool => in_array(mb_strtolower($id), $branchingIds, true)
                    && mb_strtolower($id) !== $committedChoiceId,
            ));

            if ($choiceIds !== []) {
                return [
                    'moment_key' => $later->moment_key,
                    'title' => (string) data_get($later->scene_card, 'title', ''),
                    'choice_ids' => $choiceIds,
                    'options' => $this->authoredOptionsForMoment($session, $choiceIds),
                ];
            }
        }

        return null;
    }

    /**
     * Words in the most recent narration this game produced.
     *
     * The card's word budget is the authored compression contract, and for a
     * screenwriter voice compression IS the voice — a 90-word action line answered
     * at 270 words is out of voice before diction is even considered. A target on
     * its own drifts, so the model is also handed its own last measured length to
     * correct against. Measurement only: nothing here truncates or edits prose.
     */
    private function lastNarrationWordCount(Game $game): int
    {
        $response = $game->prompts()
            ->whereNotNull('response')
            ->orderByDesc('id')
            ->value('response');

        if (! is_string($response) || $response === '') {
            return 0;
        }

        return str_word_count($this->stripHtml($response));
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
                    'label' => mb_strtoupper((string) ($option['label'] ?? '')),
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
    private function buildActiveMomentBlock(
        ?array $context,
        bool $isSessionStart = false,
        bool $isContinue = false,
    ): string {
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
            'REQUIRED: emit valid moment_progress for this key in THIS response (moment_key exact match, boolean exit_condition_met, honest route_relation and impact_scope, and after player input a non-empty moment_footnote of one to three sentences).',
        ];

        if (! $isSessionStart && $turnCount > 0) {
            $lines[] = 'THIS CARD IS ALREADY UNDERWAY: earlier responses have narrated part of it. Pick up exactly where the last response stopped. Never restage its opening_image, re-introduce people already present, or replay beats already on the page.';
        }

        $targetWords = (int) ($context['target_words_per_turn'] ?? 0);
        $previousWords = (int) ($context['previous_turn_words'] ?? 0);
        if ($targetWords > 0) {
            $budgetLines = [
                'LENGTH CONTRACT FOR THIS ROOM (THIS OVERRIDES THE GENERIC WORD-COUNT GUIDANCE ELSEWHERE IN THIS PROMPT):',
                "Authored density for this room: about {$targetWords} words for a typical turn, rarely above ".(int) round($targetWords * 1.25).' words.',
                'This number is the author\'s compression, not an amount to fill. Length follows what the action produced: a small action gets fewer words, and going under is always fine. Only a turn where much genuinely happens may run toward the upper figure, and only if every sentence tells the player something new. At this voice, length IS style: the same content at triple this count stops being their prose and becomes generic narration, however good the diction.',
                'Answer the player completely. Compression is achieved by cutting restatement, scene-setting the player already has, and re-description of things already established, never by refusing them detail about what they actually did.',
            ];

            if ($previousWords > 0) {
                $ratio = $previousWords / $targetWords;
                $budgetLines[] = "Your previous turn measured {$previousWords} words against a {$targetWords}-word target ("
                    .number_format($ratio, 1).'× budget).';
                if ($ratio >= 1.5) {
                    $budgetLines[] = 'That is over budget. Cut hard this turn: fewer sentences carrying the same beats, no re-establishing the room, no summarising what the player already watched happen.';
                }
            }

            $lines[] = implode("\n", $budgetLines);
        }

        if ($exitCondition !== '') {
            $lines[] = 'EXIT CONDITION (semantic: outcomes, not a checklist): '.$exitCondition;
            if (! $isSessionStart && ! $isContinue) {
                $lines[] = 'EXIT CHECK (every turn, before you write): read the conversation history, the opening included, and this turn\'s narration. If EVERY outcome in the exit condition has already happened on the page, set exit_condition_met=true in THIS response, even if it happened turns ago and even if the player never mentioned it. If any part has not happened on the page (a character who must appear has not appeared, a thing that must be seen has not been seen), it is not met: keep playing the card and let that part happen. Holding a finished card is an error, and so is leaving one with its scene unplayed.';
            }
        }

        if (! $isSessionStart && ! $isContinue && $targetTurns > 0 && $turnCount >= $targetTurns) {
            $lines[] = "STORY CHECK: this card has held {$turnCount} turns against a target of {$targetTurns}. Compare it with where the prose actually is. If the story has already left this card behind, set exit_condition_met=true and report the moment it is in now as caught_up_to_moment_key (see the moment_progress contract). If the story is still here, keep playing it.";
        }

        $lines[] = implode("\n", array_filter([
            'WHERE THE PLAYER MAY GO:',
            '- Inside this room: full play. Inspect, talk, act, push, escalate.',
            '- Outside this room: permitted. If the player reaches past the card, follow them rather than blocking them. Each such beat must cost or reveal something real, and what happens there is committed history.',
            '- Never permitted: the canon walls below, and the walls in the Freedom Contract.',
            "Detour beats spent since the player was last on-spine: {$detourUsed}. Comfortable room to follow them further: {$detourRemaining}.",
            $detourRemaining > 0
                ? 'You have room to follow. Let the detour matter, then let the world, not an instruction, draw the story back toward this room\'s pressure.'
                : 'You have followed this thread a while. Bring the story back now through the world\'s own logic: someone acts, something arrives, a cost comes due. Honour what the player did; do not undo it, and do not narrate the redirection as a rule.',
        ]));

        if ($forbidden !== []) {
            $lines[] = implode("\n", [
                'STORYGUARD BOUNDARIES FOR THIS ROOM:',
                'Two different kinds live in this list; read each one and treat it accordingly.',
                '- If it names a truth of the world or a character (what is real, what someone would never do, what cannot happen): that is a wall. Never cross it.',
                $isContinue
                    ? '- If it names material that belongs to a LATER room: Continue may reach and compactly narrate it only in the authored order. This permission changes timing, never truth.'
                    : '- If it names material that belongs to a LATER room (a scene, reveal, or confrontation not yet due): that is staging, not truth. You may let it be referenced, feared, foreshadowed, or attempted and fail forward; you simply may not stage it as a scene here.',
                ...array_map(static fn (string $rule): string => '- '.$rule, $forbidden),
            ]);
        }

        if ($nextBridge !== '') {
            $lines[] = 'NEXT BRIDGE (use only when exit_condition_met=true): '.$nextBridge;
        }

        if ($authoredOptions !== []) {
            $lines[] = 'AUTHORED OPTIONS BOUND TO THIS MOMENT (map the three visible_suggestions slot identities only to these when you map):';
            $lines[] = json_encode($authoredOptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        $pendingChoiceIds = array_values(array_filter(array_map(
            static fn ($id): string => (string) $id,
            (array) ($context['pending_branching_choice_ids'] ?? []),
        )));
        if (! $isSessionStart && $isContinue) {
            $lines[] = $this->continueDirective($context, $pendingChoiceIds);
        } elseif (! $isSessionStart && $pendingChoiceIds !== []) {
            $lines[] = implode("\n", [
                'DECISION POINT: this card carries the authored choice '.implode(', ', $pendingChoiceIds).', and the player has not taken it yet.',
                '- Play the scene toward it. On the turn the story reaches the decision, visible_suggestions.option_1, option_2, and option_3 ARE its A, B, and C options: each mapped with its canonical_choice_id and canonical_path_label and worded for this exact moment.',
                '- The player may still type anything; a typed action that clearly takes one of the paths resolves it (report the defining_choice_id, label, and line).',
                '- Do not set exit_condition_met=true, and do not report a later card as caught_up_to_moment_key, until the choice is taken. The runtime holds this card until it is.',
            ]);
        }

        if (in_array($pacingState, ['AT GRAVITY', 'PAST GRAVITY'], true)) {
            $lines[] = implode("\n", [
                'PACING DIRECTIVE: THE WORLD STARTS PULLING.',
                'Answer the player fully first; this is never a reason to cut them short.',
                'Then make standing still expensive. Time moves, someone decides something, evidence shifts or degrades, a voice or a body arrives, a window begins to close.',
                'Make the next step the most attractive thing in the scene rather than announcing that it is time to move on. The pull belongs to the world; never phrase it as an instruction to the player.',
                'If the exit condition is now semantically satisfied, return exit_condition_met=true and use next_bridge to transition.',
                'Do not repeat established material or hold this room without a new consequence.',
            ]);
        }

        // On the session-start turn the authored cold open is the opening prose, and it is
        // delivered verbatim. The card's own opening image and verbatim source extracts are a
        // second, competing opening for the same beat — leaving them visible is what let the
        // narrator open on the source's first line instead of the authored one. Everything
        // else on the card (exit condition, budgets, canon walls, affordances) still applies.
        $sceneCard = (array) ($context['moment'] ?? []);
        if ($isSessionStart) {
            unset($sceneCard['opening_image'], $sceneCard['source_extracts']);
        }

        $lines[] = $isSessionStart
            ? 'FULL SCENE CARD (reference; opening_image and source_extracts withheld this turn; the authored cold open in Section 13 is the opening prose):'
            : 'FULL SCENE CARD (reference):';
        $lines[] = json_encode($sceneCard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $lines[] = implode("\n", [
            'PLAYER DIRECTION, CARRIED FORWARD FROM THE LAST TURN (private; never quote, expose, or confirm):',
            ($context['treasure_insight'] ?? null) ?: '(none yet: opening, or no matching prior footnote)',
            'This is where the player has been steering. Give those threads room: let the people and places they keep reaching for answer them, and keep their open questions and requests alive, so they feel the story bending to their choices. It is a hint about direction, never a verdict about who they are. Their latest action outranks it; if they turn somewhere new, follow them.',
        ]);
        $lines[] = implode("\n", [
            'BOUNDED NEXT-MOMENT PREVIEW (guidance only; ignore until current exit is met):',
            json_encode($context['next_moment_preview'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null',
            'Its pressure and canon are fixed. How it OPENS is not: let it open on the state this player has actually created: what they broke, said, learned, refused, or made someone believe.',
        ]);

        return implode("\n\n", $lines);
    }

    /**
     * Continue reads the story forward like a book and slows down only where
     * the player can shape it: an authored branching choice they have not seen.
     * Once they have seen it and pressed Continue again, the author's path
     * carries on to the next such decision or the session close.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $pendingChoiceIds
     */
    private function continueDirective(array $context, array $pendingChoiceIds): string
    {
        $encode = static fn (mixed $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        $momentKey = (string) ($context['moment_key'] ?? '');
        $progressContract = implode("\n", [
            'CONTINUE PROGRESS REPORT:',
            "- moment_progress.moment_key stays {$momentKey}.",
            '- caught_up_to_moment_key names the card where the prose actually stopped; leave it empty if the prose never left this card. Never name where the story is merely heading.',
            '- exit_condition_met describes the card where the prose stopped: true only if everything in that card has fully happened on the page.',
        ]);
        $neverChoose = 'Never select, infer, or report an A/B/C path, defining choice, moral preference, or player-style judgment on a Continue.';

        if ($pendingChoiceIds !== [] && ! (bool) ($context['card_choice_presented'] ?? false)) {
            return implode("\n\n", [
                implode("\n", [
                    'CONTINUE SLOWS DOWN HERE: this card carries the authored choice '.implode(', ', $pendingChoiceIds).', and the player has not been offered it yet. This is a decision they can shape.',
                    '- Narrate forward like the next page of the book until the decision is in front of the protagonist, then stop there.',
                    '- Fill visible_suggestions.option_1, option_2, and option_3 with its A, B, and C options, each mapped with its canonical_choice_id and canonical_path_label and worded for this exact moment.',
                    '- '.$neverChoose.' Keep exit_condition_met=false and caught_up_to_moment_key empty.',
                ]),
                'THE DECISION:',
                $encode($context['authored_options'] ?? []),
            ]);
        }

        $lines = [];
        $lines[] = 'Every Continue response fills visible_suggestions.option_1, option_2, and option_3. If the prose stops before an authored decision, fill them with three contextual freeform actions and leave both canonical identity fields empty. Never return an empty suggestion set.';
        if ($pendingChoiceIds !== []) {
            $lines[] = 'The player was offered '.implode(', ', $pendingChoiceIds).' and pressed Continue instead: they want the author\'s story to carry on. Follow the author\'s canonical path through it.';
        }
        $lines[] = 'CONTINUE NARRATES LIKE A BOOK: move the story forward meaningfully, several beats, through the ordered moments in the author\'s order and voice. Compress connective description, routine movement, and waiting; never compress the events, or any fact, object, relationship, promise, or consequence later material needs.';
        $lines[] = $neverChoose;

        $stop = $context['continue_decision_stop'] ?? null;
        if (is_array($stop)) {
            $lines[] = "NEXT DECISION THE PLAYER CAN SHAPE: {$stop['moment_key']} \"{$stop['title']}\" (".implode(', ', (array) $stop['choice_ids']).'). If the story reaches it, narrate up to the moment that decision is in front of the protagonist and stop there. Put its three mapped A/B/C options in visible_suggestions.option_1, option_2, and option_3. Report it as caught_up_to_moment_key with exit_condition_met=false.';
            $lines[] = $encode($stop['options'] ?? []);
            $lines[] = 'Before that card, stop earlier only where a moment arrives that clearly asks for the player\'s own hand (a confrontation, a person speaking directly to the protagonist and waiting for an answer).';
        } elseif ((bool) ($context['is_final_card'] ?? false)) {
            $lines[] = 'This is the last card of the session. Carry the story to its authored close as the AUTHORED SESSION CLOSE section directs.';
        } else {
            $lines[] = 'No authored decision remains before the session close. Stop where a moment arrives that clearly asks for the player\'s own hand, or at the session\'s last card.';
        }

        return implode("\n", [...$lines, '', $progressContract]);
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
    private function buildConsequenceEchoBlock(?array $path, int $currentSessionNumber = 1): string
    {
        if ($path === null) {
            return '';
        }

        // The full guidance belongs to the session the choice was made in. One
        // session later only its payoff and the narrator stance still apply;
        // after that only the stance, so a settled choice stops re-staging.
        $choiceSession = (int) ($path['choice_session_number'] ?? $currentSessionNumber);
        $sessionsSince = max(0, $currentSessionNumber - $choiceSession);

        $lines = ['ACTIVE CONSEQUENCE GUIDANCE (player\'s prior defining choice):'];

        if ($sessionsSince > 0) {
            $lines[] = 'This choice was made '.($sessionsSince === 1 ? 'last session' : "{$sessionsSince} sessions ago").'. It is settled history: honour what it changed, never re-stage it or treat it as still open.';
        }
        if ($sessionsSince === 0 && ! empty($path['current_session_echo'])) {
            $lines[] = 'Echo this session: '.$path['current_session_echo'];
        }
        if ($sessionsSince === 1 && ! empty($path['next_session_payoff'])) {
            $lines[] = 'Honour this session: '.$path['next_session_payoff'];
        }
        if (! empty($path['narrator_behavior'])) {
            $lines[] = 'Narrator behaviour going forward: '.$path['narrator_behavior'];
        }
        if ($sessionsSince === 0 && ! empty($path['consequence_visibility']['what_changes'])) {
            $vis = $path['consequence_visibility'];
            $target = (int) ($vis['when_visible_target_responses'] ?? 2);
            $lines[] = 'Make visible within '.$target.' responses: '.$vis['what_changes']
                .' ('.($vis['how_player_sees_it'] ?? '').')';
        }
        if ($sessionsSince === 0 && ! empty($path['fold_back_acknowledge'])) {
            $lines[] = 'Fold-back, acknowledge: "'.$path['fold_back_acknowledge']
                .'" → redirect: "'.($path['fold_back_redirect'] ?? '').'"';
        }
        if ($sessionsSince === 0 && ! empty($path['hard_limits'])) {
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
     * A Continue on the last card also receives it. When the player has already
     * seen the session-end choice and pressed Continue, they have declined it,
     * so the close is delivered along the author's path without asking again.
     *
     * @param  array<string, mixed>|null  $closeDesign
     */
    private function buildSessionCloseBlock(?array $closeDesign, bool $endingChoiceDeclined = false): string
    {
        if ($closeDesign === null) {
            return '';
        }

        $lines = ['AUTHORED SESSION CLOSE (deliver this, do not improvise):'];

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
        if ($choice !== null && $endingChoiceDeclined) {
            $lines[] = '';
            $lines[] = 'SESSION-END CHOICE DECLINED: the player was offered the session-end choice and pressed Continue. Close the session along the author\'s own path: deliver the resolution prose and hook transition, end on the final line, and set exit_condition_met=true. Do not present the choice again, do not invent a different ending, and do not report a defining choice.';

            if (! empty($choice['final_line'])) {
                $lines[] = '';
                $lines[] = 'FINAL LINE (last words of this session): '.$choice['final_line'];
            }
        } elseif ($choice !== null) {
            $lines[] = '';
            $lines[] = 'SESSION-END CHOICE: present these three options.';
            $lines[] = 'What it asks (design intent, never spoken to the player): '.($choice['choice_question'] ?? '');
            foreach (['option_a', 'option_b', 'option_c'] as $opt) {
                if (! empty($choice[$opt]['text'])) {
                    $lines[] = '  '.mb_strtoupper(str_replace('_', ' ', $opt)).': '.$choice[$opt]['text'];
                }
            }
            $lines[] = '';
            $lines[] = 'Put these same three options in visible_suggestions.option_1, option_2, and option_3 this turn, each mapped to this choice_id and its A/B/C path, so the player can take one directly. The session does not close until one is genuinely chosen, so never narrate the story as finished while still asking for this decision, and never ask for it twice as though the first answer did not count.';

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
    private function buildOpeningSection(array $sessionContext, array $worldState = []): string
    {
        $sessionNumber = (int) ($sessionContext['session_number'] ?? 1);
        $coldOpen = (string) ($sessionContext['cold_open'] ?? '');
        $openingHandoff = (string) ($sessionContext['opening_handoff'] ?? '');
        $emotionalPromise = (string) ($sessionContext['emotional_promise'] ?? '');
        $mustReintroduce = $sessionContext['must_reintroduce'] ?? '';
        $reintroducedFacts = (array) ($worldState['reintroduced_facts'] ?? []);
        $activeMomentKey = (string) data_get($sessionContext, 'active_moment_context.moment_key', '');
        $previousEnding = mb_trim((string) ($sessionContext['previous_session_ending'] ?? ''));
        $bridgeAllowed = $sessionNumber > 1 && $previousEnding !== '' && $coldOpen !== '';

        $lines = [];

        if ($bridgeAllowed) {
            $lines[] = 'HOW THE LAST SESSION ACTUALLY ENDED FOR THIS PLAYER (their last page; reference, never text to recite):';
            $lines[] = $previousEnding;
            $lines[] = '';
            $lines[] = implode("\n", [
                'CONTINUITY BRIDGE: the authored cold open below was written for the author\'s own ending of the last session, and this player may have ended somewhere else.',
                '- Compare them. If the cold open fits this ending, add nothing before it.',
                '- If the cold open assumes something this ending contradicts (where the protagonist is, who is with them, what they hold or know, what has already happened), open with one to three sentences in the author\'s voice, in the cold open\'s person and tense, that carry the protagonist from where they actually are into the cold open\'s situation. Then deliver the cold open.',
                '- The bridge is the next page of the story: no summary, no recap, no explanation, nothing outside the fiction.',
            ]);
            $lines[] = '';
        }

        // Sessions 2+: prepend the continuity handoff from the previous session close.
        if ($sessionNumber > 1 && $openingHandoff !== '') {
            $lines[] = 'SESSION HANDOFF (what the previous session left behind: context you must honour, NOT text to recite):';
            $lines[] = $openingHandoff;
            $lines[] = '';
            $lines[] = 'The authored cold open below still opens this session verbatim. Let the handoff inform what the player already knows and carries; never let it displace, precede, or paraphrase the authored opening prose.';
            $lines[] = '';
        }

        // Full Phase 3 authored cold open — always present for every session.
        //
        // DELIVERY IS VERBATIM. This mirrors the authored-close contract in Section 16:
        // the designed first words of a session are protected the same way the designed
        // last words are. Without this the narrator treats Section 13 as a starting
        // position and re-composes the opening from the Section 12 source text, which
        // drops the authored body drop-in and the in-prose identity reveal.
        if ($coldOpen !== '') {
            $lines[] = 'AUTHORED COLD OPEN: DELIVER THIS TEXT VERBATIM AS YOUR OPENING PROSE.';
            $lines[] = $coldOpen;
            $lines[] = '';
            $lines[] = implode("\n", [
                'COLD OPEN DELIVERY CONTRACT (this turn only):',
                '- Reproduce the text above word for word as the opening prose of this response. Do not rewrite, re-order, paraphrase, trim, expand, or "improve" it.',
                '- It is already written in the author\'s voice and already carries the designed opening formulas: the player lands inside their own body in the first sentence, the protagonist\'s identity arrives in the prose at a moment of weight, it runs in second-person present tense, and it ends on the first real choice. Any rewrite breaks at least one of these.',
                $bridgeAllowed
                    ? '- Add NOTHING before it except the continuity bridge, and only when the bridge rule above calls for one. No date line, no year, no establishing shot, no scene heading, no "It is early in..." preamble.'
                    : '- Add NOTHING before it. No date line, no year, no establishing shot, no scene heading, no "It is early in..." preamble. The first words the player reads are the first words above.',
                '- For this turn the text above OUTRANKS every other source of opening material in this prompt: the Voice Anchor exemplars in Section 4A, the re-anchor in Section 18, the episode script in Section 12, and the active scene card\'s opening image and source extracts. Where any of them differ, the text above wins.',
                '- This matters most for the Voice Anchor. An exemplar may render the same scene as this cold open, because both were translated from the same source passage. An exemplar is a model of TEXTURE (rhythm, diction, compression) and never a supply of sentences. Do not lift its wording, its opening clause, its date or time framing, or its dialogue into this response. The authored cold open above is already in that voice; imitating the exemplar here means overwriting a finished opening with a draft of itself.',
                '- After the final line above, stop the prose. Put the authored options in visible_suggestions.option_1, option_2, and option_3; those carry the handoff, not a question.',
                '- SINGLE EXCEPTION to reproducing the text word for word: if the authored cold open ends with a question aimed at the player ("How do you go down to meet her?", "What do you do?"), drop that final question and end on the image or action immediately before it. Everything else above is still reproduced exactly. Never add a closing question of your own.',
                '- The only text you may add is what is required to satisfy this turn\'s structured output contract (choices/suggestions and progress fields).',
            ]);
        }

        // FIRST-3-MINUTES OPENING PROTOCOL — injected on session-start turn 1 only.
        // Present in V2.2 Section 13 as static template text; restored here as a runtime
        // injection so no re-assembly is needed and the clock never leaks to later turns.
        //
        // Scoped to AFTER the authored cold open: the "under 30 words" clause was being
        // read as licence to compress the 120-180 word authored open printed directly
        // above it. The clock governs what follows the open, never the open itself.
        $lines[] = '';
        $lines[] = 'FIRST-3-MINUTES PROTOCOL (applies to the turns AFTER the authored cold open, never to the cold open itself, which is delivered verbatim at its authored length): once the opening has landed, keep the first few tiered responses tight: situation established in under 30 words, first meaningful choice or custom input opportunity within 90 seconds of play, first visible consequence within 120 seconds. After the opening sequence lands, do not re-apply this clock on later turns.';

        // Emotional promise — the engagement hook for this session.
        if ($emotionalPromise !== '') {
            $lines[] = '';
            $lines[] = 'EMOTIONAL PROMISE: '.$emotionalPromise;
        }

        // Cut material that must resurface through action, not exposition.
        // Phase A/B: only inject items not yet reintroduced. The narrator
        // reports which facts it wove in via reintroduced_facts; the
        // commit path stores them in world_state.reintroduced_facts and
        // they stop appearing here. Phase B adds target_moment_key filtering
        // so items only appear when the active moment matches their target.
        $mustReintroduceValue = $mustReintroduce;
        if ($mustReintroduceValue !== '' && $mustReintroduceValue !== []) {
            $tracker = new ReintroTracker;
            $items = $tracker->parse($mustReintroduceValue);
            $pendingItems = $tracker->pending($items, $reintroducedFacts, $activeMomentKey);
            $rendered = $tracker->render($pendingItems);

            if ($rendered !== '') {
                $lines[] = '';
                $lines[] = 'MUST REINTRODUCE (weave through action and dialogue, never as an exposition dump):';
                $lines[] = $rendered;
                $lines[] = '';
                $lines[] = 'After weaving in any of these facts, list them in your reintroduced_facts field so the runtime knows they have landed. Items you do not address this turn will remain available next turn.';
            } elseif ($reintroducedFacts !== []) {
                $lines[] = '';
                $lines[] = 'ALL MUST REINTRODUCE ITEMS HAVE LANDED. No pending reintroduction items remain.';
            }
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
            $catchUpCandidate = $hadProgressObject ? mb_trim((string) ($progress['caught_up_to_moment_key'] ?? '')) : '';
            if ($catchUpCandidate === '' && is_string($rawMomentKey) && $rawMomentKey !== $moment->moment_key) {
                $catchUpCandidate = mb_trim($rawMomentKey);
            }
            $result['moment_progress'] = [
                'moment_key' => $moment->moment_key,
                // Never trust an exit judgment that was attached to the wrong room
                // or an incomplete object — hold the player in the active moment.
                // A key naming a different card is kept as a catch-up candidate;
                // the resolver accepts it only for a reachable later card.
                'exit_condition_met' => false,
                'caught_up_to_moment_key' => $catchUpCandidate,
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

        if (! is_array($result['visible_suggestions'] ?? null)
            || count($result['visible_suggestions']) !== 3) {
            Log::channel('narration')->error('boiler.visible_suggestions_invalid_count', [
                'game_id' => $game->id,
                'count' => is_array($result['visible_suggestions'] ?? null)
                    ? count($result['visible_suggestions'])
                    : null,
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
            'caught_up_to_moment_key' => mb_trim((string) ($result['moment_progress']['caught_up_to_moment_key'] ?? '')),
            ...$this->normalizeRouteFields($result['moment_progress']),
        ];
        $progress = $result['moment_progress'];

        $footnote = $progress['moment_footnote'] ?? null;
        if ($isOpening) {
            $result['moment_progress']['route_relation'] = '';
            $result['moment_progress']['impact_scope'] = '';
            $result['moment_progress']['caught_up_to_moment_key'] = '';

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

        $lines = ['STORY-NATIVE ALIGNMENT TILT: "'.$label.'" (hidden; never surface the literal label or any RPG terminology).'];
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
    private function renderTieredWorldState(
        array $worldState,
        bool $isClimactic,
        array $sessionEvents,
        string $focusText = '',
        string $lastNarration = '',
    ): string {
        $tier1 = $this->formatTier1($worldState, $focusText, $lastNarration);
        $tier2 = $this->formatTier2($worldState, $sessionEvents, $focusText);
        $history = $this->formatHistory($worldState, $isClimactic, $focusText);

        if ($isClimactic) {
            $tier3 = $this->formatTier3($worldState);

            return implode("\n\n", array_filter([$tier1, $tier2, $tier3, $history]));
        }

        return implode("\n\n", array_filter([$tier1, $tier2, $history]));
    }

    /**
     * Archived state from earlier sessions, labelled as the past so it is
     * never mistaken for how the world stands now.
     *
     * @param  array<string, mixed>  $w
     */
    private function formatHistory(array $w, bool $loadAll, string $focusText): string
    {
        $history = $this->cleanStringList($w['history'] ?? []);

        if ($history === []) {
            return '';
        }

        $shown = $loadAll ? $history : $this->selectRelevantMemories($history, self::HISTORY_RELEVANT_ENTRIES, $focusText);

        return implode("\n", [
            'EARLIER SESSIONS (history: how things stood when those sessions ended, NOT how they stand now):',
            'The present is the conversation and the state above. Use this only to stay consistent with what already happened; never stage it again or treat an old position, condition, or object state as current.',
            $this->joinList($shown),
        ]);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function formatTier1(array $w, string $focusText = '', string $lastNarration = ''): string
    {
        $lines = [
            'PERSISTENT STATE, TIER 1 (always loaded):',
            'This is a record of what is true, kept for consistency. It is not a list of things to mention. Bring a carried item, condition, or fact into the prose only when it changes, when the player uses or asks about it, or when this turn\'s action depends on it.',
        ];
        $items = $this->cleanStringList($w['items'] ?? []);
        $conditions = $this->cleanStringList($w['conditions'] ?? []);
        $knowledge = $this->selectRelevantMemories($this->cleanStringList($w['knowledge'] ?? []), self::TIER1_RECENT_KNOWLEDGE, $focusText);
        $notes = $this->selectRelevantMemories($this->cleanStringList($w['notes'] ?? []), self::TIER1_RECENT_NOTES, $focusText);

        [$knowledge, $knowledgeOnPage] = $this->splitAlreadyOnPage($knowledge, $lastNarration);
        [$notes, $notesOnPage] = $this->splitAlreadyOnPage($notes, $lastNarration);
        [, $carriedOnPage] = $this->splitAlreadyOnPage([...$items, ...$conditions], $lastNarration);

        $location = mb_trim((string) ($w['location'] ?? ''));
        $lines[] = 'Location: '.($location !== '' ? $location : '(unset)');
        $lines[] = 'Items: '.$this->joinList($items);
        $lines[] = 'Conditions: '.$this->joinList($conditions);
        $lines[] = 'Knowledge (latest, plus older facts this turn reaches for): '.$this->joinList($knowledge);
        $lines[] = 'Notes (latest, plus older notes this turn reaches for): '.$this->joinList($notes);

        $onPage = [...$carriedOnPage, ...$knowledgeOnPage, ...$notesOnPage];
        if ($onPage !== []) {
            $lines[] = 'Already on the page in your last turn (the player has it; repeat none of it unless it changes this turn): '.$this->joinList($onPage);
        }

        $lines[] = 'Observed player tendencies (tentative; their latest action always outranks these): '
            .$this->joinList($this->mostRecent((array) ($w['player_style'] ?? []), self::TIER1_RECENT_PLAYER_STYLE));

        return implode("\n", $lines);
    }

    /**
     * Pick which stored memories to show: always the latest few, then the
     * older entries that share the most meaningful words and names with this
     * turn (the player's action, the last narration, the active card), then
     * the most recent of the rest for any slots still open. Entries keep their
     * chronological order.
     *
     * @param  array<int, string>  $entries
     * @return array<int, string>
     */
    private function selectRelevantMemories(array $entries, int $limit, string $focusText): array
    {
        $entries = array_values($entries);
        if (count($entries) <= $limit) {
            return $entries;
        }

        $recentCount = min(self::TIER1_ALWAYS_RECENT, $limit);
        $olderCount = count($entries) - $recentCount;
        $focusWords = $this->memoryWords($focusText);
        $focusNames = $this->memoryNames($focusText);

        $scores = [];
        for ($index = 0; $index < $olderCount; $index++) {
            $score = count(array_intersect($this->memoryWords($entries[$index]), $focusWords))
                + 2 * count(array_intersect($this->memoryNames($entries[$index]), $focusNames));

            if ($score >= 2) {
                $scores[$index] = $score;
            }
        }

        arsort($scores);
        $chosen = array_slice(array_keys($scores), 0, $limit - $recentCount);

        for ($index = $olderCount - 1; $index >= 0 && count($chosen) < $limit - $recentCount; $index--) {
            if (! in_array($index, $chosen, true)) {
                $chosen[] = $index;
            }
        }
        sort($chosen);

        return [
            ...array_map(static fn (int $index): string => $entries[$index], $chosen),
            ...array_slice($entries, $olderCount),
        ];
    }

    /**
     * Split out entries the last narration already stated, so the narrator
     * sees them labelled as established instead of as material to use.
     *
     * @param  array<int, string>  $entries
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function splitAlreadyOnPage(array $entries, string $lastNarration): array
    {
        if ($lastNarration === '' || $entries === []) {
            return [$entries, []];
        }

        $narrationWords = $this->memoryWords($lastNarration);
        $fresh = [];
        $onPage = [];

        foreach ($entries as $entry) {
            $words = $this->memoryWords($entry);
            $covered = $words === [] ? 0 : count(array_intersect($words, $narrationWords)) / count($words);

            if (count($words) >= 2 && $covered >= 0.75) {
                $onPage[] = $entry;
            } else {
                $fresh[] = $entry;
            }
        }

        return [$fresh, $onPage];
    }

    /**
     * Ledger lines with near-repeats collapsed to their latest phrasing.
     *
     * @param  array<int, mixed>  $ledger
     * @return array<int, string>
     */
    private function distinctLedgerLines(array $ledger, int $limit): array
    {
        $lines = array_map(
            static fn (array $entry): string => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_values(array_filter($ledger, static fn ($entry): bool => is_array($entry))),
        );

        return array_slice($this->appendUniqueStringList([], $lines), -$limit);
    }

    /**
     * Phrases and sentence openings the narrator has already leaned on in its
     * recent turns, measured from the text rather than asked of the model.
     *
     * @param  array<int, string>  $recentNarrations
     */
    private function recentPhrasingBlock(array $recentNarrations): string
    {
        $recent = array_slice(array_values($recentNarrations), -3);
        if (count($recent) < 2) {
            return '';
        }

        $gramTurns = [];
        $openers = [];
        foreach ($recent as $narration) {
            $text = mb_strtolower(strip_tags($narration));
            $words = preg_split("/[^\p{L}\p{N}'\x{2019}]+/u", $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $seen = [];
            for ($i = 0; $i + 4 <= count($words); $i++) {
                $gram = array_slice($words, $i, 4);
                $content = array_filter(
                    $gram,
                    static fn (string $word): bool => mb_strlen($word) >= 4 && ! in_array($word, self::PHRASING_FILLER, true),
                );
                if (count($content) >= 2) {
                    $seen[implode(' ', $gram)] = true;
                }
            }
            foreach (array_keys($seen) as $gram) {
                $gramTurns[$gram] = ($gramTurns[$gram] ?? 0) + 1;
            }

            foreach (preg_split('/(?<=[.!?])\s+/u', mb_trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $sentence) {
                $opening = implode(' ', array_slice(preg_split('/\s+/u', mb_trim($sentence)) ?: [], 0, 2));
                if ($opening !== '') {
                    $openers[$opening] = ($openers[$opening] ?? 0) + 1;
                }
            }
        }

        $phrases = [];
        foreach (array_keys(array_filter($gramTurns, static fn (int $turns): bool => $turns >= 2)) as $gram) {
            $gramWords = explode(' ', $gram);
            $prefix = implode(' ', array_slice($gramWords, 0, 3));

            foreach ($phrases as $index => $kept) {
                if (str_ends_with($kept, $prefix)) {
                    $phrases[$index] = $kept.' '.$gramWords[3];

                    continue 2;
                }

                if (str_contains($kept, $prefix)) {
                    continue 2;
                }
            }

            $phrases[] = $gram;
        }

        $repeatedOpeners = array_keys(array_filter($openers, static fn (int $count): bool => $count >= 3));

        $lastTurn = (string) end($recent);
        $dashes = mb_substr_count($lastTurn, "\u{2014}") + mb_substr_count($lastTurn, ' - ');

        $lines = [];
        if ($phrases !== []) {
            $lines[] = 'Phrases: "'.implode('" • "', array_slice($phrases, 0, 12)).'"';
        }
        if ($repeatedOpeners !== []) {
            $lines[] = 'Sentence openings used three or more times: "'.implode('" • "', array_slice($repeatedOpeners, 0, 8)).'"';
        }
        if ($dashes > 0) {
            $lines[] = "Your last turn used {$dashes} dash".($dashes === 1 ? '' : 'es').' as punctuation. Use none unless the Anchor Card allows them.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, mixed>
     */
    private function mostRecent(array $entries, int $limit): array
    {
        return array_slice(array_values($entries), -$limit);
    }

    /**
     * @param  array<string, mixed>  $w
     * @param  array<int, array<string, mixed>>  $sessionEvents
     */
    private function formatTier2(array $w, array $sessionEvents, string $focusText = ''): string
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
        $background = $this->selectRelevantMemories([
            ...array_values(array_diff($allObjects, $object)),
            ...array_values(array_diff($allRelationships, $relationships)),
            ...array_values(array_diff($allFlags, $flags)),
        ], self::TIER2_BACKGROUND_ENTRIES, $focusText);
        $promises = (array) ($w['unresolved_promises'] ?? []);

        $ledgerLines = $this->distinctLedgerLines((array) ($w['emotional_ledger'] ?? []), 6);

        $lines = ['PERSISTENT STATE, TIER 2 (scene-connected):'];
        $lines[] = 'Object states: '.$this->joinList($object);
        $lines[] = 'Relationships: '.$this->joinList($relationships);
        $lines[] = 'World flags: '.$this->joinList($flags);
        $lines[] = 'Other recorded state (background for consistency only; bring it in only if this turn makes it relevant): '.$this->joinList($background);
        $lines[] = 'Unresolved promises: '.$this->joinList($promises);
        $lines[] = 'Recent emotional ledger: '.$this->joinList($ledgerLines);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function formatTier3(array $w): string
    {
        $allLedger = $this->distinctLedgerLines((array) ($w['emotional_ledger'] ?? []), self::TIER3_LEDGER_ENTRIES);

        $lines = ['PERSISTENT STATE, TIER 3 (full load: an episode boundary, or the previous turn resolved a moral-weight or session-end choice):'];
        $lines[] = 'All object states: '.$this->joinList((array) ($w['object_states'] ?? []));
        $lines[] = 'All relationships: '.$this->joinList((array) ($w['relationship_updates'] ?? []));
        $lines[] = 'All world flags: '.$this->joinList((array) ($w['world_flags'] ?? []));
        $lines[] = 'Emotional ledger (latest '.self::TIER3_LEDGER_ENTRIES.' distinct entries): '.$this->joinList($allLedger);

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

    /**
     * "The Stable Door", "stable_door", and "stable-door's" name one tracked
     * thing, so a change to any of them supersedes the others.
     */
    private function stateEntryKey(string $entry): string
    {
        $name = mb_trim((string) (explode(':', $entry, 2)[0] ?? $entry));
        $key = mb_strtolower($name !== '' ? $name : $entry);
        $key = (string) preg_replace("/['\u{2019}]s\b/u", '', $key);
        $key = (string) preg_replace('/[\s_\-]+/u', ' ', $key);
        $key = (string) preg_replace('/^(the|a|an) /u', '', mb_trim($key));

        return mb_trim($key);
    }

    /**
     * Append new entries, letting each supersede any older entry that says the
     * same thing. The newer phrasing moves to the end, so the list stays in the
     * order things were last learned and recency selection stays honest.
     *
     * @param  array<int, mixed>  $previous
     * @param  array<int, mixed>  $delta
     * @return array<int, string>
     */
    private function appendUniqueStringList(array $previous, array $delta, float $threshold = self::MEMORY_SAME_THRESHOLD): array
    {
        $entries = [];

        foreach (array_merge($this->cleanStringList($previous), $this->cleanStringList($delta)) as $entry) {
            $entries = array_values(array_filter(
                $entries,
                fn (string $existing): bool => ! $this->isSameMemory($existing, $entry, $threshold),
            ));
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Two memory lines record the same fact when one's meaningful words are
     * contained in the other's (an evolved phrasing of the same observation,
     * or a later state of the same thing), or when most of their meaningful
     * words overlap. Lines about different named people never match.
     */
    private function isSameMemory(string $a, string $b, float $threshold): bool
    {
        $wordsA = $this->memoryWords($a);
        $wordsB = $this->memoryWords($b);

        if ($wordsA === [] || $wordsB === []) {
            return $this->normalizeMemoryLine($a) === $this->normalizeMemoryLine($b);
        }

        $namesA = $this->memoryNames($a);
        $namesB = $this->memoryNames($b);
        if (
            $namesA !== [] && $namesB !== []
            && array_diff($namesA, $namesB) !== []
            && array_diff($namesB, $namesA) !== []
        ) {
            return false;
        }

        $shared = count(array_intersect($wordsA, $wordsB));
        $smaller = min(count($wordsA), count($wordsB));

        if ($smaller >= 3 && $shared === $smaller) {
            return true;
        }

        return $shared / count(array_unique([...$wordsA, ...$wordsB])) >= $threshold;
    }

    /**
     * Meaningful words of a memory line: lowercase, possessives and simple
     * plurals folded, stopwords and words under four letters dropped.
     *
     * @return array<int, string>
     */
    private function memoryWords(string $text): array
    {
        $normalized = (string) preg_replace("/['\u{2019}]s\b/u", '', mb_strtolower($text));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $kept = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || in_array($word, self::MEMORY_STEM_STOPWORDS, true)) {
                continue;
            }

            if (mb_strlen($word) > 4 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
                $word = mb_substr($word, 0, -1);
            }

            $kept[$word] = true;
        }

        return array_keys($kept);
    }

    /**
     * Capitalised words other than common sentence openers: the people and
     * places a memory line is about. Erring toward more names only ever keeps
     * two lines apart, never merges them.
     *
     * @return array<int, string>
     */
    private function memoryNames(string $text): array
    {
        preg_match_all('/\b(\p{Lu}\p{Ll}+)\b/u', $text, $matches);

        $names = [];
        foreach ($matches[1] ?? [] as $word) {
            $lower = mb_strtolower($word);
            if (
                mb_strlen($lower) < 3
                || in_array($lower, self::MEMORY_STEM_STOPWORDS, true)
                || in_array($lower, self::MEMORY_SENTENCE_OPENERS, true)
            ) {
                continue;
            }

            $names[$lower] = true;
        }

        return array_keys($names);
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
        $text = $this->stripAuthoringMarkers(mb_trim((string) $value));

        return in_array(mb_strtolower($text), ['n/a', 'na', 'none', 'null', '(none)'], true)
            ? ''
            : $text;
    }

    /**
     * Strip Phase 3 authoring scaffolding from authored opening prose.
     *
     * The Phase 3 OUTPUT blocks are fenced with banner lines ("=== COLD OPEN: Title |
     * Session 1 ===" ... "=== CUT POINT === hands to ..."), and the agent sometimes
     * copies them into the prose field alongside the prose. Harmless while the narrator
     * paraphrased the slot; player-visible now that the cold open is delivered verbatim.
     *
     * Everything from a CUT POINT banner onward is pipeline metadata addressed to the
     * next phase, never player-facing, so it is dropped along with the banner.
     */
    private function stripAuthoringMarkers(string $text): string
    {
        if (! str_contains($text, '==')) {
            return $text;
        }

        // Drop the CUT POINT banner and everything after it.
        $text = (string) preg_replace('/^[ \t]*={2,}[ \t]*CUT POINT\b.*$/imsu', '', $text);

        // Drop remaining full-line banners ("=== COLD OPEN: ... ===").
        $text = (string) preg_replace('/^[ \t]*={2,}.*?={2,}[ \t]*$/mu', '', $text);

        // Collapse the blank lines the removals leave behind.
        $text = (string) preg_replace("/\n{3,}/u", "\n\n", $text);

        return mb_trim($text);
    }

    /**
     * Anthropic models in MODELS_WITHOUT_SAMPLING return 400 if temperature,
     * top_p, or top_k is set to a non-default value.
     *
     * @see https://platform.claude.com/docs/en/models/haiku-5-5/migration-guide
     */
    private function modelAcceptsSamplingParams(string $model): bool
    {
        return ! in_array($model, self::MODELS_WITHOUT_SAMPLING, true);
    }

    private function narrationMaxTokens(string $model): int
    {
        return $model === 'claude-haiku-5-5'
            ? self::HAIKU_55_NARRATION_MAX_TOKENS
            : self::NARRATION_MAX_TOKENS;
    }

    private function repairMaxTokens(string $model): int
    {
        return $model === 'claude-haiku-5-5'
            ? self::HAIKU_55_REPAIR_MAX_TOKENS
            : 512;
    }

    private function modelHasConfiguredProvider(string $model): bool
    {
        $modelConfig = self::MODEL_CONFIG[$model] ?? null;
        if (! is_array($modelConfig)) {
            return false;
        }

        $provider = $modelConfig['provider'] ?? null;
        if (! is_string($provider) || $provider === '') {
            return false;
        }

        return filled(config("prism.providers.{$provider}.api_key"));
    }

    /**
     * Prism's OpenAI structured handler reports uncached prompt tokens, while
     * its streaming handler currently reports total prompt tokens alongside the
     * cached subset. Normalize both paths to the same mutually exclusive token
     * buckets before logging, pricing, or combining a repair call.
     *
     * @param  array<string, mixed>  $usage
     * @return array<string, int>
     */
    private function normalizeProviderUsage(
        array $usage,
        string $provider,
        bool $streamed,
    ): array {
        if ($usage === []) {
            return [];
        }

        $cacheRead = $this->usageTokenCount($usage['cache_read_input_tokens'] ?? 0);
        $prompt = $this->usageTokenCount($usage['prompt_tokens'] ?? 0);
        if ($provider === 'openai' && $streamed) {
            $prompt = max(0, $prompt - $cacheRead);
        }

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $this->usageTokenCount($usage['completion_tokens'] ?? 0),
            'cache_write_input_tokens' => $this->usageTokenCount($usage['cache_write_input_tokens'] ?? 0),
            'cache_read_input_tokens' => $cacheRead,
            'thought_tokens' => $this->usageTokenCount($usage['thought_tokens'] ?? 0),
        ];
    }

    /**
     * A malformed legacy streamed response may require one small player-runtime
     * repair call. Its tokens are part of the same player turn and must not be
     * lost from that turn's usage ledger.
     *
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $repair
     * @return array<string, int>
     */
    private function mergeUsage(array $primary, array $repair): array
    {
        if ($primary === [] && $repair === []) {
            return [];
        }

        $merged = [];
        foreach ([
            'prompt_tokens',
            'completion_tokens',
            'cache_write_input_tokens',
            'cache_read_input_tokens',
            'thought_tokens',
        ] as $key) {
            $merged[$key] = $this->usageTokenCount($primary[$key] ?? 0)
                + $this->usageTokenCount($repair[$key] ?? 0);
        }

        return $merged;
    }

    private function usageTokenCount(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * Convert the windowed conversation history into native Prism chat
     * messages (alternating UserMessage / AssistantMessage), and append
     * the current player action plus narration instructions as the final
     * UserMessage. Native alternating turns give the model the conversational
     * structure it was trained on, so pronouns like "it", "that", "him", "her"
     * resolve against the immediately preceding turn the way ChatGPT does —
     * instead of being flattened into a single text block the model has to
     * parse by hand.
     *
     * @param  array<int, array{role?: string, text?: string, protagonist?: string}>  $conversationHistory
     * @return array<int, UserMessage|AssistantMessage>
     */
    private function conversationHistoryToMessages(
        array $conversationHistory,
        ?string $playerAction,
        string $protagonist,
        bool $isOpening,
    ): array {
        $messages = [];
        $protagonistUpper = mb_strtoupper($protagonist);

        foreach ($conversationHistory as $turn) {
            $text = mb_trim((string) ($turn['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            if (($turn['role'] ?? '') === 'narrator') {
                $messages[] = new AssistantMessage($text);

                continue;
            }

            $speaker = mb_strtoupper((string) ($turn['protagonist'] ?? $protagonist));
            $messages[] = new UserMessage("[{$speaker}]: {$text}");
        }

        $instructions = $isOpening
            ? $this->openingInstructions()
            : $this->turnInstructions($protagonistUpper, mb_trim((string) $playerAction) === 'Continue the story forward.');

        $actionBlock = $playerAction !== null && mb_trim($playerAction) !== ''
            ? "{$protagonistUpper}'S ACTION: ".mb_trim($playerAction)."\n\n"
            : '';

        $finalUserText = $actionBlock.$instructions;
        $messages[] = new UserMessage($finalUserText);

        return $messages;
    }

    /**
     * Flatten native messages back into a single text block for the
     * legacy choice-repair call, which still uses withPrompt().
     *
     * @param  array<int, UserMessage|AssistantMessage>  $messages
     */
    private function flattenMessagesForRepair(array $messages): string
    {
        $lines = [];
        foreach ($messages as $message) {
            if ($message instanceof AssistantMessage) {
                $lines[] = '[NARRATOR]: '.$message->content;
            } else {
                $lines[] = $message->content;
            }
        }

        return implode("\n\n", $lines);
    }

    /**
     * Keep the prompt text and order intact while placing one explicit cache
     * breakpoint immediately before the first per-turn content.
     *
     * Rendered prompts gather all per-turn data into the live turn context at
     * the end, so everything above it (all authored sections and the sparse
     * contract) is cached. Prompts without that section fall back to splitting
     * before Section 8, the first runtime injection in the older layout.
     *
     * @return array<int, SystemMessage>
     */
    private function anthropicSystemPrompts(string $systemPrompt): array
    {
        $runtimeOffset = mb_strpos($systemPrompt, self::LIVE_TURN_CONTEXT_MARKER);

        if ($runtimeOffset === false) {
            $runtimeOffset = mb_strpos($systemPrompt, '### SECTION 8:');
        }

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
