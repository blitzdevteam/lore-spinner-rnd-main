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
use RuntimeException;
use Throwable;

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
    private const COMPLETE_LIST_STATE_FIELDS = ['conditions', 'items', 'unresolved_promises'];

    private const KEYED_DELTA_STATE_FIELDS = ['object_states', 'relationship_updates', 'world_flags'];

    private const APPEND_ONLY_STATE_FIELDS = ['knowledge', 'notes', 'player_style'];

    // Base budget for prose + choices + scalars + JSON overhead, holding the
    // three COMPLETE_LIST_STATE_FIELDS empty. See ChaosNarrationSchema: those
    // three fields are re-emitted in full every turn and grow across a game,
    // so they are budgeted separately in maxTokensFor() instead of folded
    // into one fixed constant (that undercount is what caused the truncation
    // soft-brick on long-running games — 2026-07-04 production audit).
    private const MAX_TOKENS_BASE = 1_400;

    // ~4 chars/token plus per-entry JSON quoting/comma overhead, rounded up.
    private const MAX_TOKENS_PER_STATE_LIST_CHAR = 0.4;

    private const MAX_TOKENS_FLOOR = 2_000;

    // Hard ceiling so a runaway list (bad turn, adversarial input) still
    // can't reserve an unbounded output buffer.
    private const MAX_TOKENS_RETRY_CEILING = 8_000;

    public const MODEL_CONFIG = [
        'gpt-5.5'           => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.4'           => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.4-mini'      => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.2'           => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-4.1'           => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'chat-latest'       => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'claude-opus-4-8'   => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-opus-4-7'   => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-sonnet-5'   => ['provider' => 'anthropic', 'reasoning_effort' => null],
        'claude-sonnet-4-6' => ['provider' => 'anthropic', 'temperature' => 0.9,  'reasoning_effort' => null],
        'claude-haiku-4-5'  => ['provider' => 'anthropic', 'temperature' => 0.95, 'reasoning_effort' => null],
    ];

    // public const DEFAULT_MODEL = 'claude-haiku-4-5';
    // /** Production /user/games narration — tighter than chaos-mode lab defaults. */
    // public const GAME_TEMPERATURE = 0.4;

    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /** Production /user/games narration — tighter than chaos-mode lab defaults. */
    public const GAME_TEMPERATURE = 0.6;

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
    ): ?array
    {
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
            $arcRow          = $this->findArcProgressionRow($adaptation, $sessionNumber);
            $resolvedHandoff = $this->normalizeOpeningScene($arcRow['opens_with'] ?? '');
        }

        $totalSessions = (int) ($adaptation?->sessionAdaptations?->count() ?? 0);
        $activeMomentContext = $this->resolveActiveMomentContext($game, $sessionAdaptation);

        return [
            'session_number'          => $sessionNumber,
            'total_sessions'          => $totalSessions,
            'cold_open'               => $this->normalizeOpeningScene($entry['cold_open'] ?? ''),
            'opening_handoff'         => $resolvedHandoff,
            'emotional_promise'       => trim((string) ($entry['emotional_promise'] ?? '')),
            'must_reintroduce'        => trim((string) ($entry['format_specific_cut']['must_reintroduce'] ?? '')),
            'runtime_prompt'          => (string) $sessionAdaptation->runtime_narrator_prompt,
            'alignment_labels'        => (array) ($storyMap['alignment_labels'] ?? []),
            'full_session_events'     => $events,
            'resolved_consequence_path' => $resolvedConsequencePath,
            'session_close_design'    => $sessionCloseDesign,
            'active_moment_context'   => $activeMomentContext,
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

        $symbolicBlock = trim((string) $symbolicMemory) !== ''
            ? trim((string) $symbolicMemory)
            : '(No symbolic memory yet. The protagonist is still becoming.)';

        $alignmentBlock = $this->renderAlignmentTilt(
            (array) ($sessionContext['alignment_labels'] ?? []),
            $alignmentScaffold,
        );

        $stateScopeEvents = (array) ($sessionContext['full_session_events'] ?? []);
        $activeSourceEventIds = (array) data_get(
            $sessionContext,
            'active_moment_context.moment.source_event_ids',
            [],
        );
        if ($activeSourceEventIds !== []) {
            $stateScopeEvents = array_values(array_filter(
                $stateScopeEvents,
                fn (array $event): bool => in_array((int) ($event['id'] ?? 0), $activeSourceEventIds, true),
            ));
        }

        $worldStateBlock = $this->renderTieredWorldState(
            worldState:    $worldState,
            isClimactic:   $isClimacticPrevious,
            sessionEvents: $stateScopeEvents,
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
                '[SYMBOLIC_MEMORY_INJECTION_POINT]'    => $symbolicBlock,
                '[ALIGNMENT_TILT_INJECTION_POINT]'     => $alignmentBlock,
                '[OPENING_SCENE_INJECTION_POINT]'      => $this->buildOpeningSection($sessionContext),
                '[WORLD_STATE_TIERED_INJECTION_POINT]' => $worldStateBlock,
                '[CONSEQUENCE_ECHO_INJECTION_POINT]'   => $consequenceBlock,
                '[SESSION_CLOSE_INJECTION_POINT]'      => $closeBlock,
                '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]' => $activeMomentBlock,
            ]);
        } else {
            // Strip Section 13 entirely before substitution. Find the section header and the
            // injection point token and replace the whole span with a single continuation marker.
            // D8 v2 template uses '### SECTION 13:' heading format.
            $sec13Anchor = '### SECTION 13:';
            $injToken    = '[OPENING_SCENE_INJECTION_POINT]';
            $sec13Start  = strpos($prompt, $sec13Anchor);
            $injPos      = strpos($prompt, $injToken);

            if ($sec13Start !== false && $injPos !== false && $injPos > $sec13Start) {
                $prompt = substr($prompt, 0, $sec13Start)
                    . '(Continuation turn — opening already delivered. Resume from conversation history; do not re-cold-open.)'
                    . substr($prompt, $injPos + strlen($injToken));
            }

            $prompt = strtr($prompt, [
                '[SYMBOLIC_MEMORY_INJECTION_POINT]'    => $symbolicBlock,
                '[ALIGNMENT_TILT_INJECTION_POINT]'     => $alignmentBlock,
                '[WORLD_STATE_TIERED_INJECTION_POINT]' => $worldStateBlock,
                '[CONSEQUENCE_ECHO_INJECTION_POINT]'   => $consequenceBlock,
                '[SESSION_CLOSE_INJECTION_POINT]'      => $closeBlock,
                '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]' => $activeMomentBlock,
            ]);
        }

        return $prompt;
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

        if (
            $moment === null
            || $moment->cut->id !== $game->current_playable_session_cut_id
            || $moment->cut->session_adaptation_id !== $session->id
        ) {
            throw new RuntimeException('Game playable-moment pointer does not belong to the active session cut.');
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
        $preview = $nextMoment === null ? null : [
            'moment_key' => $nextMoment->moment_key,
            'title' => $nextCard['title'] ?? '',
            'runtime_goal' => $nextCard['runtime_goal'] ?? '',
            'opening_image' => $nextCard['opening_image'] ?? '',
            'live_pressure' => $nextCard['live_pressure'] ?? '',
            'player_affordances' => $nextCard['player_affordances'] ?? [],
            'choice_ids' => $nextCard['choice_ids'] ?? [],
            'canon_must_keep' => $nextCard['canon_must_keep'] ?? [],
            'next_bridge' => data_get($moment->scene_card, 'next_bridge'),
        ];

        return [
            'moment' => (array) $moment->scene_card,
            'turn_count' => (int) $game->playable_moment_turn_count,
            'treasure_insight' => $footnote['text'] ?? null,
            'next_moment_preview' => $preview,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function buildActiveMomentBlock(?array $context): string
    {
        if ($context === null) {
            return '';
        }

        return implode("\n\n", [
            'ACTIVE PLAYABLE MOMENT (the only room you may perform now):',
            json_encode($context['moment'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'SOFT MOMENT TURN COUNT: ' . (int) ($context['turn_count'] ?? 0),
            'HIDDEN TREASURE INSIGHT (never quote or expose): '
                . (($context['treasure_insight'] ?? null) ?: '(none — opening or no matching prior insight)'),
            'BOUNDED NEXT-MOMENT PREVIEW (guidance only; ignore until current exit is met):',
            json_encode($context['next_moment_preview'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null',
        ]);
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
        $pathLabel = strtoupper(trim($definingPathLabel));

        if ($definingChoiceId === '' || $pathLabel === '') {
            return null;
        }

        $branchingEntry = null;
        foreach ((array) ($consequenceMap['branching_consequences'] ?? []) as $bc) {
            if (strtolower((string) ($bc['choice_id'] ?? '')) === strtolower($definingChoiceId)) {
                $branchingEntry = $bc;
                break;
            }
        }

        if ($branchingEntry === null) {
            Log::warning('consequence_path.choice_not_found', [
                'choice_id'  => $definingChoiceId,
                'path_label' => $pathLabel,
            ]);
            return null;
        }

        $matchedPath = null;
        foreach ((array) ($branchingEntry['paths'] ?? []) as $path) {
            if (strtoupper((string) ($path['label'] ?? '')) === $pathLabel) {
                $matchedPath = $path;
                break;
            }
        }

        if ($matchedPath === null) {
            Log::warning('consequence_path.label_not_found', [
                'choice_id'        => $definingChoiceId,
                'path_label'       => $pathLabel,
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
                strtolower((string) ($fg['choice_id'] ?? '')) === strtolower($definingChoiceId)
                && strtoupper((string) ($fg['path_label'] ?? '')) === $pathLabel
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
     * @param  array<string, mixed>  $result            callAgent() return value
     * @param  \Illuminate\Database\Eloquent\Collection<int, SessionAdaptation>|null  $sessionAdaptations
     * @return array{defining_choice_id:string, defining_path_label:string, defining_choice_line:string}|null
     */
    public function resolveBranchCommit(
        array $result,
        Game $game,
        ?object $sessionAdaptations,
    ): ?array {
        $newChoiceId = trim((string) ($result['defining_choice_id'] ?? ''));

        if ($newChoiceId === '') {
            return null;
        }

        $pathLabel = strtoupper(trim((string) ($result['defining_path_label'] ?? '')));

        if (! in_array($pathLabel, ['A', 'B', 'C'], true)) {
            Log::warning('branch_commit.invalid_label', [
                'game_id'   => $game->id,
                'choice_id' => $newChoiceId,
                'raw_label' => $result['defining_path_label'] ?? '',
                'note'      => 'Label is not A/B/C — branch triple not committed; prior state preserved.',
            ]);
            return null;
        }

        // Validate label exists in the authored options for this choice's own session.
        if ($sessionAdaptations !== null) {
            $choiceSession = $this->extractSessionNumber($newChoiceId);
            $targetSA      = $sessionAdaptations->firstWhere('session_number', $choiceSession);
            $authoredLabels = [];

            if ($targetSA && ! empty($targetSA->session_choice_design['branching_choices'])) {
                foreach ((array) $targetSA->session_choice_design['branching_choices'] as $bc) {
                    if (strtolower((string) ($bc['choice_id'] ?? '')) === strtolower($newChoiceId)) {
                        $authoredLabels = array_map(
                            fn ($opt) => strtoupper((string) ($opt['label'] ?? '')),
                            (array) ($bc['options'] ?? [])
                        );
                        break;
                    }
                }
            }

            if ($authoredLabels !== [] && ! in_array($pathLabel, $authoredLabels, true)) {
                Log::warning('branch_commit.invalid_label', [
                    'game_id'         => $game->id,
                    'choice_id'       => $newChoiceId,
                    'raw_label'       => $result['defining_path_label'] ?? '',
                    'authored_labels' => $authoredLabels,
                    'note'            => 'Label not found in authored options — branch triple not committed; prior state preserved.',
                ]);
                return null;
            }
        }

        $newLine = trim((string) ($result['defining_choice_line'] ?? ''));

        return [
            'defining_choice_id'   => $newChoiceId,
            'defining_path_label'  => $pathLabel,
            'defining_choice_line' => $newLine !== '' ? $newLine : null,
        ];
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
            $lines[] = 'Echo this session: ' . $path['current_session_echo'];
        }
        if (! empty($path['next_session_payoff'])) {
            $lines[] = 'Honour this session: ' . $path['next_session_payoff'];
        }
        if (! empty($path['narrator_behavior'])) {
            $lines[] = 'Narrator behaviour going forward: ' . $path['narrator_behavior'];
        }
        if (! empty($path['consequence_visibility']['what_changes'])) {
            $vis     = $path['consequence_visibility'];
            $target  = (int) ($vis['when_visible_target_responses'] ?? 2);
            $lines[] = 'Make visible within ' . $target . ' responses: ' . $vis['what_changes']
                . ' (' . ($vis['how_player_sees_it'] ?? '') . ')';
        }
        if (! empty($path['fold_back_acknowledge'])) {
            $lines[] = 'Fold-back — acknowledge: "' . $path['fold_back_acknowledge']
                . '" → redirect: "' . ($path['fold_back_redirect'] ?? '') . '"';
        }
        if (! empty($path['hard_limits'])) {
            $lines[] = 'Hard limits at this node: ' . implode(' / ', (array) $path['hard_limits']);
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
            $lines[] = 'Q: ' . ($choice['choice_question'] ?? '');
            foreach (['option_a', 'option_b', 'option_c'] as $opt) {
                if (! empty($choice[$opt]['text'])) {
                    $lines[] = '  ' . strtoupper(str_replace('_', ' ', $opt)) . ': ' . $choice[$opt]['text'];
                }
            }
            if (! empty($choice['final_line'])) {
                $lines[] = '';
                $lines[] = 'FINAL LINE (last words of this session): ' . $choice['final_line'];
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
        $sessionNumber    = (int) ($sessionContext['session_number'] ?? 1);
        $coldOpen         = (string) ($sessionContext['cold_open'] ?? '');
        $openingHandoff   = (string) ($sessionContext['opening_handoff'] ?? '');
        $emotionalPromise = (string) ($sessionContext['emotional_promise'] ?? '');
        $mustReintroduce  = (string) ($sessionContext['must_reintroduce'] ?? '');

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
            $lines[] = 'EMOTIONAL PROMISE: ' . $emotionalPromise;
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
     * Call the Chaos narration agent via Prism. Retries once on failure.
     *
     * Pass $playerAction = null for the opening turn (session start).
     * Pass the string 'Continue the story forward.' for the continue button.
     *
     * @param  array<int, array{role:string, text:string}>  $conversationHistory
     * @return array{
     *     response:string,
     *     choices:array<int,string>,
     *     session_complete:bool,
     *     state_delta:array<string,mixed>,
     *     alignment_tally_delta: array{chaotic:int, lawful:int, neutral:int},
     *     is_climactic_choice:bool,
     *     defining_choice_id:string,
     *     defining_path_label:string,
     *     defining_choice_line:string,
     *     symbolic_memory_update:string,
     *     session_memory_update:string,
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

        $promptText = view('ai.agents.chaos.turn-prompt', [
            'conversationHistory' => $conversationHistory,
            'playerAction'        => $playerAction,
            'protagonist'         => $protagonist,
        ])->render();

        $schemaArray     = ChaosNarrationSchema::definition(new JsonSchemaTypeFactory);
        $providerOptions = $this->providerOptionsFor($config);

        // 55s keeps the full round-trip (Cloudflare→Laravel→Anthropic→Laravel→Cloudflare)
        // safely below Cloudflare's 100s upstream timeout. Server-side retries are
        // removed: if the call times out, the controller catches the exception and
        // shows the player a retry prompt, which fires a fresh HTTP request and
        // resets Cloudflare's timer — no 504s.
        $request = Prism::structured()
            ->using($config['provider'], $model)
            ->withSchema(new NativeObjectSchema($schemaArray))
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($promptText)
            ->withClientOptions(['timeout' => 55])
            ->withProviderOptions($providerOptions);

        // Sonnet 5 / Opus 4.7+ reject non-default temperature, top_p, top_k (400).
        // Omit sampling params; tone is governed by the runtime narrator system prompt.
        if ($this->modelAcceptsSamplingParams($model)) {
            $request = $request->usingTemperature($temperature);
        }

        if ($config['provider'] === 'anthropic') {
            $request = $request->withMaxTokens($this->maxTokensFor($worldState));
        }

        try {
            $response = $request->asStructured();
        } catch (Throwable $e) {
            if ($config['provider'] === 'anthropic' && $this->looksLikeTruncation($e)) {
                $request  = $request->withMaxTokens(self::MAX_TOKENS_RETRY_CEILING);
                $response = $request->asStructured();
            } else {
                throw $e;
            }
        }

        $structured = $response->structured ?? [];

        $alignment = (array) ($structured['alignment_tally_delta'] ?? []);

        $result = [
            'response'               => (string) ($structured['response'] ?? ''),
            'choices'                => (array)  ($structured['choices'] ?? []),
            'visible_suggestions'    => array_key_exists('visible_suggestions', $structured)
                ? (array) $structured['visible_suggestions']
                : null,
            'moment_progress'        => isset($structured['moment_progress'])
                ? (array) $structured['moment_progress']
                : null,
            'session_complete'       => (bool)   ($structured['session_complete'] ?? false),
            'state_delta'            => (array)  ($structured['state_delta'] ?? []),
            'alignment_tally_delta'  => [
                'chaotic' => (int) ($alignment['chaotic'] ?? 0),
                'lawful'  => (int) ($alignment['lawful'] ?? 0),
                'neutral' => (int) ($alignment['neutral'] ?? 0),
            ],
            'is_climactic_choice'    => (bool)   ($structured['is_climactic_choice'] ?? false),
            'defining_choice_id'     => (string) ($structured['defining_choice_id'] ?? ''),
            'defining_path_label'    => strtoupper(trim((string) ($structured['defining_path_label'] ?? ''))),
            'defining_choice_line'   => (string) ($structured['defining_choice_line'] ?? ''),
            'symbolic_memory_update' => (string) ($structured['symbolic_memory_update'] ?? ''),
            'session_memory_update'  => (string) ($structured['session_memory_update'] ?? ''),
        ];

        $this->validateBoilerNarrationResult($result, $game, $isOpening);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function validateBoilerNarrationResult(array $result, ?Game $game, bool $isOpening): void
    {
        if ($game === null || $game->current_playable_moment_id === null) {
            return;
        }

        $moment = $game->currentPlayableMoment()->first();
        $progress = $result['moment_progress'] ?? null;

        if (
            $moment === null
            || ! is_array($progress)
            || ($progress['moment_key'] ?? null) !== $moment->moment_key
            || ! array_key_exists('exit_condition_met', $progress)
            || ! is_bool($progress['exit_condition_met'])
        ) {
            throw new RuntimeException('Boiler narration returned missing or stale moment_progress.');
        }

        if (! is_array($result['visible_suggestions'] ?? null) || count($result['visible_suggestions']) > 3) {
            throw new RuntimeException('Boiler narration must return visible_suggestions with zero to three entries.');
        }
        if ((array) ($result['choices'] ?? []) !== []) {
            throw new RuntimeException('Boiler narration must not emit legacy choices alongside visible_suggestions.');
        }

        $footnote = $progress['moment_footnote'] ?? null;
        if ($isOpening) {
            if ($progress['exit_condition_met'] !== false || $footnote !== null) {
                throw new RuntimeException('Boiler opening narration must report exit_condition_met=false and a null footnote.');
            }

            return;
        }

        if (! is_string($footnote) || trim($footnote) === '') {
            throw new RuntimeException('Boiler player turns require one hidden natural moment footnote.');
        }
    }

    /**
     * Size the output token budget off the state the model must carry forward.
     *
     * conditions/items/unresolved_promises are re-emitted in full every turn
     * (see COMPLETE_LIST_STATE_FIELDS), so their serialised size grows across
     * a game. Budgeting a fixed constant for them undercounts late-game turns
     * and causes a deterministic truncate-throw-retry-truncate loop. Early
     * turns still get a small, fast-scheduling budget; only games that have
     * accumulated real state pay for a larger one.
     */
    private function maxTokensFor(array $worldState): int
    {
        $carriedChars = 0;
        foreach (self::COMPLETE_LIST_STATE_FIELDS as $key) {
            foreach ((array) ($worldState[$key] ?? []) as $entry) {
                $carriedChars += strlen((string) $entry);
            }
        }

        $budget = self::MAX_TOKENS_BASE + (int) ceil($carriedChars * self::MAX_TOKENS_PER_STATE_LIST_CHAR);

        return max(self::MAX_TOKENS_FLOOR, $budget);
    }

    /**
     * Prism/Anthropic surfaces a truncated structured response as a decode
     * failure, not a distinct exception type — match on message content.
     * Conservative: only retries when the failure looks token-budget shaped,
     * so a real schema/validation bug still surfaces immediately.
     */
    private function looksLikeTruncation(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'max_tokens')
            || str_contains($message, 'maximum context length')
            || str_contains($message, 'incomplete')
            || str_contains($message, 'unterminated')
            || (str_contains($message, 'json') && str_contains($message, 'decod'));
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

        $merged['location'] = trim((string) ($delta['location'] ?? '')) !== ''
            ? (string) $delta['location']
            : (string) ($previous['location'] ?? '');

        foreach (self::COMPLETE_LIST_STATE_FIELDS as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value)
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
        $newLedger      = [];
        foreach ((array) ($delta['emotional_ledger_entries'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $category = trim((string) ($entry['category'] ?? ''));
            $text     = trim((string) ($entry['entry'] ?? ''));
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
            'lawful'  => max(0, (int) ($previous['lawful'] ?? 0) + (int) ($delta['lawful'] ?? 0)),
            'neutral' => max(0, (int) ($previous['neutral'] ?? 0) + (int) ($delta['neutral'] ?? 0)),
        ];
    }

    public function appendMemory(?string $existing, string $update): ?string
    {
        $update = trim($update);
        if ($update === '') {
            return $existing;
        }

        return $existing ? trim($existing) . "\n" . $update : $update;
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
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
            'location'             => '',
            'conditions'           => [],
            'items'                => [],
            'object_states'        => [],
            'relationship_updates' => [],
            'world_flags'          => [],
            'knowledge'            => [],
            'notes'                => [],
            'player_style'         => [],
            'unresolved_promises'  => [],
            'emotional_ledger'     => [],
        ];
    }

    /**
     * @return array{chaotic:int, lawful:int, neutral:int}
     */
    public function emptyAlignmentScaffold(): array
    {
        return ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0];
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
        $lawful  = (int) ($scaffold['lawful'] ?? 0);
        $neutral = (int) ($scaffold['neutral'] ?? 0);

        $total = $chaotic + $lawful + $neutral;
        if ($total === 0) {
            return 'No player tendency has declared itself yet. Keep all story-native tonal registers available without naming alignment.';
        }

        $dominant  = 'mixed';
        $threshold = (int) ceil($total * 0.4);
        $top       = max($chaotic, $lawful, $neutral);
        $tieCount  = (int) ($chaotic === $top) + (int) ($lawful === $top) + (int) ($neutral === $top);

        if ($tieCount === 1 && $top >= $threshold) {
            $dominant = match (true) {
                $chaotic === $top => 'chaotic',
                $lawful === $top  => 'lawful',
                default           => 'neutral',
            };
        }

        $label          = null;
        $description    = null;
        $voiceSignature = null;
        foreach ($alignmentLabels as $row) {
            $mapsTo = strtolower((string) ($row['maps_to_internal'] ?? ''));
            if ($mapsTo === $dominant) {
                $label          = (string) ($row['label'] ?? '');
                $description    = (string) ($row['narrative_consequences'] ?? ($row['description'] ?? ''));
                $voiceSignature = (string) ($row['voice_signature'] ?? '');
                break;
            }
        }

        if ($label === null || $label === '') {
            return 'The player\'s alignment has tilted, but no story-native label is configured for "' . $dominant
                . '". Narrate without surfacing the alignment, but lean toward this tendency in tone.';
        }

        $lines   = ['STORY-NATIVE ALIGNMENT TILT: "' . $label . '" (hidden — never surface the literal label or any RPG terminology).'];
        if ($description !== '') {
            $lines[] = 'Behavioural shape: ' . $description;
        }
        if ($voiceSignature !== '') {
            $lines[] = 'Voice signature: ' . $voiceSignature;
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
        $lines    = ['PERSISTENT STATE — TIER 1 (always loaded):'];
        $location = trim((string) ($w['location'] ?? ''));
        $lines[]  = 'Location: ' . ($location !== '' ? $location : '(unset)');
        $lines[]  = 'Items: ' . $this->joinList((array) ($w['items'] ?? []));
        $lines[]  = 'Conditions: ' . $this->joinList((array) ($w['conditions'] ?? []));
        $lines[]  = 'Knowledge: ' . $this->joinList((array) ($w['knowledge'] ?? []));
        $lines[]  = 'Notes: ' . $this->joinList((array) ($w['notes'] ?? []));
        $lines[]  = 'Player style: ' . $this->joinList((array) ($w['player_style'] ?? []));

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $w
     * @param  array<int, array<string, mixed>>  $sessionEvents
     */
    private function formatTier2(array $w, array $sessionEvents): string
    {
        $haystack = strtolower(implode(' ', array_map(
            static fn ($e) => (string) ($e['title'] ?? '') . ' ' . (string) ($e['content'] ?? ''),
            $sessionEvents,
        )));

        $allObjects       = (array) ($w['object_states'] ?? []);
        $allRelationships = (array) ($w['relationship_updates'] ?? []);
        $allFlags         = (array) ($w['world_flags'] ?? []);

        $object               = $this->filterByHaystack($allObjects, $haystack);
        $relationships        = $this->filterByHaystack($allRelationships, $haystack);
        $flags                = $this->filterByHaystack($allFlags, $haystack);
        $dormantObjects       = array_values(array_diff($allObjects, $object));
        $dormantRelationships = array_values(array_diff($allRelationships, $relationships));
        $dormantFlags         = array_values(array_diff($allFlags, $flags));
        $promises             = (array) ($w['unresolved_promises'] ?? []);

        $ledger       = (array) ($w['emotional_ledger'] ?? []);
        $recentLedger = array_slice($ledger, -6);
        $ledgerLines  = array_map(
            static fn (array $entry) => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_filter($recentLedger, static fn ($e) => is_array($e)),
        );

        $lines   = ['PERSISTENT STATE — TIER 2 (scene-connected):'];
        $lines[] = 'Object states: ' . $this->joinList($object);
        $lines[] = 'Relationships: ' . $this->joinList($relationships);
        $lines[] = 'World flags: ' . $this->joinList($flags);
        $lines[] = 'Other carried object states: ' . $this->joinList($dormantObjects);
        $lines[] = 'Other carried relationships: ' . $this->joinList($dormantRelationships);
        $lines[] = 'Other carried world flags: ' . $this->joinList($dormantFlags);
        $lines[] = 'Unresolved promises: ' . $this->joinList($promises);
        $lines[] = 'Recent emotional ledger: ' . $this->joinList($ledgerLines);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function formatTier3(array $w): string
    {
        $ledger    = (array) ($w['emotional_ledger'] ?? []);
        $allLedger = array_map(
            static fn (array $entry) => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_filter($ledger, static fn ($e) => is_array($e)),
        );

        $lines   = ['PERSISTENT STATE — TIER 3 (climactic load — previous turn resolved a moral-weight or session-end choice):'];
        $lines[] = 'All object states: ' . $this->joinList((array) ($w['object_states'] ?? []));
        $lines[] = 'All relationships: ' . $this->joinList((array) ($w['relationship_updates'] ?? []));
        $lines[] = 'All world flags: ' . $this->joinList((array) ($w['world_flags'] ?? []));
        $lines[] = 'Full emotional ledger: ' . $this->joinList($allLedger);

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
            $name = strtolower((string) (explode(':', (string) $entry, 2)[0] ?? ''));
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
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function cleanStringList(mixed $value): array
    {
        return array_values(array_filter(
            array_map(static fn ($item) => trim((string) $item), (array) $value),
            static fn (string $item) => $item !== '',
        ));
    }

    /**
     * @param  array<int, mixed>  $previous
     * @param  array<int, mixed>  $delta
     * @return array<int, string>
     */
    private function mergeKeyedStateList(array $previous, array $delta): array
    {
        $entriesByKey = [];
        $orderedKeys  = [];

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
        $name = trim((string) (explode(':', $entry, 2)[0] ?? $entry));

        return strtolower($name !== '' ? $name : $entry);
    }

    /**
     * @param  array<int, mixed>  $previous
     * @param  array<int, mixed>  $delta
     * @return array<int, string>
     */
    private function appendUniqueStringList(array $previous, array $delta): array
    {
        $seen   = [];
        $merged = [];

        foreach (array_merge($this->cleanStringList($previous), $this->cleanStringList($delta)) as $entry) {
            $key = strtolower($entry);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[]   = $entry;
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
        $text = trim((string) $value);

        return in_array(strtolower($text), ['n/a', 'na', 'none', 'null', '(none)'], true)
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
