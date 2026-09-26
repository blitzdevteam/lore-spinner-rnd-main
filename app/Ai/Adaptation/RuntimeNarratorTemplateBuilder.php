<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\PlayableSessionCut;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Support\Facades\View;
use Log;
use RuntimeException;

/**
 * D8 v2 assembler — 18-section runtime narrator template.
 *
 * Reads every pipeline output the runtime narrator needs and renders the
 * template at `ai.agents.chaos.runtime-narrator-template` into a single
 * string cached on `session_adaptations.runtime_narrator_prompt`.
 *
 * Size behaviour (warn-not-fail):
 *   - The cap is set high enough that authored guidance is expected to survive
 *     intact. Compression is a last resort, not part of normal operation.
 *   - Event bodies, voice guard and story guard are never compressed. Only two
 *     things degrade, in this order: authored per-path detail (which has a
 *     runtime equivalent for the resolved path), then voice example quotes.
 *   - If a pass fits under MAX_PROMPT_CHARS the result is returned immediately.
 *   - If no pass fits, the most-compressed version is returned anyway with a
 *     warning logged — adaptation is NEVER blocked by prompt size.
 *   - WARN_MARGIN_CHARS triggers an early warning when within 15k of the cap.
 *
 * V2.3 anchor / token checks (warn-not-fail):
 *   - Missing voice_anchor / anchor_card / runtime_self_check → warning logged,
 *     assembly continues (pre-V2.3 profiles still produce a prompt).
 *   - Unmapped {{…}} tokens or missing [OPENING_SCENE_INJECTION_POINT] → warning
 *     logged, prompt still persisted.
 *
 * Injection-point tokens filled at runtime by ChaosEngineService, not here:
 *   [OPENING_SCENE_INJECTION_POINT], [SYMBOLIC_MEMORY_INJECTION_POINT],
 *   [ALIGNMENT_TILT_INJECTION_POINT], [WORLD_STATE_TIERED_INJECTION_POINT]
 */
final class RuntimeNarratorTemplateBuilder
{
    /**
     * Ceiling for the cached runtime narrator prompt, ~48k tokens.
     *
     * Sized so a large authored cut keeps its full voice guard, story guard and
     * per-path authored detail rather than compressing any of it away — losing
     * that guidance costs more than the tokens do. Narration runs on a 200k
     * context model, so this leaves ample room for history and world state.
     *
     * Prompts over this cap are logged as warnings but still persisted.
     */
    public const MAX_PROMPT_CHARS = 192_000;

    /**
     * Early-warning threshold — 15k below the cap. Prompts between this value
     * and MAX_PROMPT_CHARS get a near-cap log entry, which is the signal that a
     * session is approaching the point where detail would start degrading.
     */
    public const WARN_MARGIN_CHARS = self::MAX_PROMPT_CHARS - 15_000;

    /**
     * Never let same-scene filtering leave the Voice Anchor thinner than this.
     * Below four exemplars the narrator loses its range of modes, which costs
     * more voice than a colliding exemplar costs prose.
     */
    private const EXEMPLAR_FLOOR = 4;

    /**
     * Function words excluded when comparing an exemplar's source description
     * against the scenes this session narrates. Words shorter than four
     * characters are dropped separately, so this list only needs the longer
     * connectives that would otherwise manufacture false collisions.
     */
    private const TOKEN_STOPWORDS = [
        'about', 'after', 'again', 'against', 'along', 'also', 'among', 'around',
        'because', 'been', 'before', 'being', 'below', 'beneath',
        'between', 'both', 'during', 'each', 'else', 'even', 'ever',
        'every', 'from', 'have', 'here', 'into', 'itself', 'just', 'less',
        'like', 'made', 'make', 'many', 'more', 'most', 'much', 'must', 'near',
        'next', 'once', 'only', 'onto', 'other', 'over', 'same', 'some', 'such',
        'than', 'that', 'their', 'them', 'then', 'there', 'these', 'they',
        'this', 'those', 'through', 'toward', 'under', 'until', 'upon', 'very',
        'what', 'when', 'where', 'which', 'while', 'with', 'within', 'without',
        'would', 'your',
    ];

    /**
     * Detail levels for the authored per-path blocks (freeform manifest and
     * consequence visibility), most to least detailed. Sessions with a large
     * authored cut shed this detail to stay under the cap.
     */
    private const AUTHORED_DETAIL_LEVELS = ['full', 'compact', 'none'];

    public function __construct(
        private SessionEventLoader $eventLoader,
        private VoiceAnchorSanitizer $voiceAnchorSanitizer = new VoiceAnchorSanitizer,
    ) {}

    /**
     * Build the cached runtime narrator prompt for a given session adaptation.
     *
     * Always returns a string — adaptation is never blocked by prompt size or
     * missing anchor fields. Issues are logged as warnings so they are visible
     * without stopping the pipeline.
     *
     * Compression cascade, least to most lossy — source events are never
     * compressed, so the only live dimensions are authored detail and voice
     * quotes:
     *   authored full → compact → none, then the same three with quotes dropped.
     *
     * If a pass fits under MAX_PROMPT_CHARS the result is returned immediately.
     * If all passes exceed the cap the most-compressed version is returned with
     * a warning logged. WARN_MARGIN_CHARS triggers an early near-cap warning.
     */
    public function build(Story $story, SessionAdaptation $session): string
    {
        $adaptation = $session->storyAdaptation;
        $voiceProfile = $this->voiceAnchorSanitizer->sanitizeProfile((array) ($adaptation->voice_profile ?? []));

        // Log warnings for missing V2.3 anchor fields but continue assembly.
        $this->warnIfAnchorFieldsMissing($voiceProfile, $story, $session);

        $totalSessions = $adaptation->sessionAdaptations()->count();
        $startEventPosition = (int) ($session->entry_point_diagnosis['start_event_position'] ?? 0);
        $sessionEvents = $this->eventLoader
            ->forSession(
                $story,
                $session->session_number,
                $startEventPosition > 0 ? $startEventPosition : null,
            )
            ->reject(fn ($event): bool => (bool) $event->before_entry_point)
            ->map(fn ($event): array => [
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
            ->values()
            ->all();

        $cut = null;
        if ($adaptation->boiler_enabled) {
            $cut = $session->playableCut()
                ->where('status', 'finalized')
                ->with('moments')
                ->first();

            if ($cut === null || $cut->moments->isEmpty()) {
                throw new RuntimeException('Boiler runtime assembly requires one finalized cut with ordered moments.');
            }
        }

        // Compression cascade — event bodies are NEVER compressed.
        // Only voice example-quote arrays are eligible for stripping (drop_quotes).
        // dropVoiceQuotes never touches voice_anchor / anchor_card / runtime_self_check.
        $compressionAttempts = [
            'full' => fn () => $sessionEvents,
        ];

        $voiceCompression = [
            'full' => fn (array $v) => $v,
            'drop_quotes' => fn (array $v) => $this->dropVoiceQuotes($v),
        ];

        $lastRendered = '';
        $lastCompression = 'full+full+full';

        foreach ($compressionAttempts as $sourceMode => $sourceFn) {
            foreach ($voiceCompression as $voiceMode => $voiceFn) {
                // Authored per-path detail degrades BEFORE voice example quotes:
                // voice fidelity is the product, and the agency doctrine itself is
                // hardcoded in the template — only the authored detail is at risk.
                foreach (self::AUTHORED_DETAIL_LEVELS as $authoredDetail) {
                    $rendered = $this->render($story, $session, $totalSessions, $sourceFn(), $voiceFn($voiceProfile), $cut, $authoredDetail);
                    $charCount = mb_strlen($rendered);
                    $lastRendered = $rendered;
                    $lastCompression = "{$sourceMode}+{$voiceMode}+authored_{$authoredDetail}";

                    if ($charCount <= self::MAX_PROMPT_CHARS) {
                        if ($charCount >= self::WARN_MARGIN_CHARS) {
                            Log::channel('narration')->warning('runtime_narrator_assembly.near_cap', [
                                'story_id' => $story->id,
                                'session_number' => $session->session_number,
                                'char_count' => $charCount,
                                'cap' => self::MAX_PROMPT_CHARS,
                                'compression' => $lastCompression,
                                'headroom' => self::MAX_PROMPT_CHARS - $charCount,
                            ]);
                        }
                        $this->warnIfPromptHasIssues($rendered, $story, $session);

                        return $rendered;
                    }
                }
            }
        }

        // All passes exhausted — persist most-compressed version with a warning.
        $charCount = mb_strlen($lastRendered);
        Log::channel('narration')->warning('runtime_narrator_assembly.over_cap', [
            'story_id' => $story->id,
            'session_number' => $session->session_number,
            'char_count' => $charCount,
            'cap' => self::MAX_PROMPT_CHARS,
            'overage' => $charCount - self::MAX_PROMPT_CHARS,
            'compression' => $lastCompression,
            'action' => 'Persisting most-compressed version. Consider editorial session split.',
        ]);

        $this->warnIfPromptHasIssues($lastRendered, $story, $session);

        return $lastRendered;
    }

    /**
     * Soften [BRACKETED_TOKENS] in every authored value rendered into the prompt.
     * The runtime validator rejects unknown control tokens, so an incidental
     * bracket anywhere in generated artifacts must never block assembly. Control
     * tokens the runtime actually reads are literals in the Blade template, never
     * data, so nothing legitimate is lost here.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function withoutBracketTokens(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::withoutBracketTokens($item);
            } elseif (is_string($item)) {
                $value[$key] = preg_replace('/\[([A-Z][A-Z0-9_]*)\]/', '($1)', $item) ?? $item;
            }
        }

        return $value;
    }

    /**
     * Project the authored freeform manifest down to the detail level that fits.
     *
     * `full` is the expected level and loses nothing the author wrote. `compact`
     * keeps the spirit, the freeform that belongs at each path, and the fold-back
     * moves, dropping only the two fields that
     * ChaosEngineService::buildConsequenceEchoBlock() re-injects at runtime for
     * the path the player actually resolved. `none` omits the manifest entirely;
     * the hardcoded Freedom Contract still governs, only per-path detail is lost.
     *
     * @param  array<int, array<string, mixed>>  $guidelines
     * @return array<int, array<string, mixed>>
     */
    private function projectFreeformGuidelines(array $guidelines, string $level): array
    {
        if ($level === 'none') {
            return [];
        }

        return array_values(array_map(static function (array $g) use ($level): array {
            $projected = [
                'choice_id' => $g['choice_id'] ?? '',
                'path_label' => $g['path_label'] ?? '',
                'spirit' => $g['spirit'] ?? '',
                'freeform_alignment_input' => $g['freeform_alignment_input'] ?? '',
                'fold_back_acknowledge' => $g['fold_back_acknowledge'] ?? '',
                'fold_back_redirect' => $g['fold_back_redirect'] ?? '',
                'fold_back_arrive_at' => $g['fold_back_arrive_at'] ?? '',
            ];

            if ($level === 'full') {
                $projected['narrator_behavior'] = $g['narrator_behavior'] ?? '';
                $projected['hard_limits'] = (array) ($g['hard_limits'] ?? []);
            }

            return $projected;
        }, array_filter($guidelines, 'is_array')));
    }

    /**
     * Project consequence visibility down to the detail level that fits.
     *
     * `current_session_echo` is kept at `full` and dropped from `compact`, where
     * the runtime consequence echo block still carries it for the resolved path.
     *
     * @param  array<int, array<string, mixed>>  $branchingConsequences
     * @return array<int, array<string, mixed>>
     */
    private function projectConsequenceVisibility(array $branchingConsequences, string $level): array
    {
        if ($level === 'none') {
            return [];
        }

        $out = [];
        foreach (array_filter($branchingConsequences, 'is_array') as $consequence) {
            $paths = [];
            foreach (array_filter((array) ($consequence['paths'] ?? []), 'is_array') as $path) {
                $visibility = (array) ($path['consequence_visibility'] ?? []);
                $whatChanges = (string) ($visibility['what_changes'] ?? '');
                if ($whatChanges === '') {
                    continue;
                }

                $projectedVisibility = [
                    'what_changes' => $whatChanges,
                    'when_visible_target_responses' => $visibility['when_visible_target_responses'] ?? 2,
                    'when_visible_max_responses' => $visibility['when_visible_max_responses'] ?? 3,
                ];
                if ($level === 'full') {
                    $projectedVisibility['how_player_sees_it'] = $visibility['how_player_sees_it'] ?? '';
                }

                $paths[] = [
                    'label' => $path['label'] ?? '',
                    'current_session_echo' => $level === 'full' ? ($path['current_session_echo'] ?? '') : '',
                    'consequence_visibility' => $projectedVisibility,
                ];
            }

            if ($paths !== []) {
                $out[] = [
                    'choice_id' => $consequence['choice_id'] ?? '',
                    'paths' => $paths,
                ];
            }
        }

        return $out;
    }

    /**
     * Log a warning if the voice_profile is missing V2.3 anchor fields.
     * Assembly continues regardless — pre-V2.3 profiles still produce a prompt.
     */
    private function warnIfAnchorFieldsMissing(array $voiceProfile, Story $story, SessionAdaptation $session): void
    {
        $missing = [];

        if (empty($voiceProfile['voice_anchor'])) {
            $missing[] = 'voice_anchor';
        }
        if (empty($voiceProfile['anchor_card'])) {
            $missing[] = 'anchor_card';
        }
        if (empty($voiceProfile['runtime_self_check'])) {
            $missing[] = 'runtime_self_check';
        }

        if ($missing !== []) {
            Log::channel('narration')->warning('runtime_narrator_assembly.missing_v23_anchor_fields', [
                'story_id' => $story->id,
                'session_number' => $session->session_number,
                'missing_fields' => $missing,
                'action' => 'Assembling without V2.3 anchor fields. Re-run Voice Lock to get full D8 v2 quality.',
            ]);
        }
    }

    /**
     * Log warnings if the rendered prompt contains unmapped {{…}} tokens or is
     * missing [OPENING_SCENE_INJECTION_POINT]. Prompt is still persisted.
     */
    private function warnIfPromptHasIssues(string $rendered, Story $story, SessionAdaptation $session): void
    {
        if (preg_match('/\{\{[^}]+\}\}/', $rendered)) {
            Log::channel('narration')->warning('runtime_narrator_assembly.unmapped_tokens', [
                'story_id' => $story->id,
                'session_number' => $session->session_number,
                'action' => 'Prompt contains unmapped {{…}} tokens — template slot wiring may be incomplete.',
            ]);
        }

        if (! str_contains($rendered, '[OPENING_SCENE_INJECTION_POINT]')) {
            Log::channel('narration')->warning('runtime_narrator_assembly.missing_injection_point', [
                'story_id' => $story->id,
                'session_number' => $session->session_number,
                'action' => 'Prompt is missing [OPENING_SCENE_INJECTION_POINT] — cold open will not inject at session start.',
            ]);
        }
    }

    /**
     * Remove voice exemplars that were translated from a scene this session is
     * about to narrate.
     *
     * An exemplar is a texture model, but when it was written from the very beat
     * in front of the narrator its wording becomes the obvious thing to write —
     * so the narrator reproduces our translation of the scene instead of
     * performing the authored material. Section 4A already forbids this in
     * prose; the instruction is not reliably obeyed, so the colliding exemplar
     * is withheld rather than argued with.
     *
     * Deliberately conservative — matching needs a shared two-word phrase or
     * four shared distinctive words, so an exemplar that merely shares a
     * character name survives. If dropping would take the pool below
     * EXEMPLAR_FLOOR the exemplar is kept and flagged instead: a thin anchor
     * costs more voice than a colliding exemplar costs prose.
     *
     * @param  array<string, mixed>  $voiceProfile
     * @param  array<int, array<string, mixed>>  $boilerMoments
     * @return array<string, mixed>
     */
    private function withoutSameSceneExemplars(
        array $voiceProfile,
        array $boilerMoments,
        Story $story,
        SessionAdaptation $session,
    ): array {
        $exemplars = array_values((array) ($voiceProfile['voice_anchor'] ?? []));

        if ($exemplars === [] || $boilerMoments === []) {
            return $voiceProfile;
        }

        $sceneTokens = [];
        $sceneBigrams = [];

        foreach ($boilerMoments as $moment) {
            $sceneText = implode(' ', array_filter([
                (string) ($moment['title'] ?? ''),
                (string) ($moment['runtime_goal'] ?? ''),
                (string) ($moment['live_pressure'] ?? ''),
                (string) ($moment['opening_image'] ?? ''),
            ]));

            $sceneTokens = array_merge($sceneTokens, $this->distinctiveTokens($sceneText));
            $sceneBigrams = array_merge($sceneBigrams, $this->distinctiveBigrams($sceneText));
        }

        $sceneTokens = array_unique($sceneTokens);
        $sceneBigrams = array_unique($sceneBigrams);

        $collisions = [];

        foreach ($exemplars as $index => $exemplar) {
            $source = mb_trim((string) ($exemplar['source'] ?? ''));

            if ($source === '') {
                continue;
            }

            $sharedBigrams = array_intersect($this->distinctiveBigrams($source), $sceneBigrams);
            $sharedTokens = array_intersect($this->distinctiveTokens($source), $sceneTokens);

            if (count($sharedBigrams) >= 1 || count($sharedTokens) >= 4) {
                $collisions[$index] = [
                    'source' => $source,
                    'shared_phrases' => array_values($sharedBigrams),
                    'shared_words' => array_values($sharedTokens),
                ];
            }
        }

        if ($collisions === []) {
            return $voiceProfile;
        }

        $survivors = count($exemplars) - count($collisions);
        $withhold = $survivors >= self::EXEMPLAR_FLOOR;

        Log::channel('narration')->info('runtime_narrator_assembly.same_scene_exemplars', [
            'story_id' => $story->id,
            'session_number' => $session->session_number,
            'exemplar_count' => count($exemplars),
            'collision_count' => count($collisions),
            'collisions' => array_values($collisions),
            'action' => $withhold
                ? 'Withheld from Section 4A — the session narrates these beats.'
                : 'Kept and flagged: withholding would leave fewer than '.self::EXEMPLAR_FLOOR.' exemplars.',
        ]);

        foreach (array_keys($collisions) as $index) {
            if ($withhold) {
                unset($exemplars[$index]);

                continue;
            }

            $exemplars[$index]['same_scene_collision'] = true;
        }

        $voiceProfile['voice_anchor'] = array_values($exemplars);

        return $voiceProfile;
    }

    /**
     * Lowercase content words, punctuation and possessives stripped, stopwords
     * and short words removed. Order is preserved so callers can build phrases.
     *
     * @return array<int, string>
     */
    private function tokenSequence(string $text): array
    {
        $normalized = preg_replace("/['\u{2019}]s\\b/u", '', mb_strtolower($text)) ?? '';
        $words = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_map(
            fn (string $word): string => in_array($word, self::TOKEN_STOPWORDS, true) || mb_strlen($word) < 4
                ? ''
                : $word,
            $words,
        ));
    }

    /**
     * @return array<int, string>
     */
    private function distinctiveTokens(string $text): array
    {
        return array_values(array_unique(array_filter($this->tokenSequence($text))));
    }

    /**
     * Two-word phrases where both words are distinctive AND adjacent in the
     * original text. Requiring raw adjacency is what stops a phrase from being
     * invented across removed stopwords ("door in the wing" → "door wing").
     *
     * @return array<int, string>
     */
    private function distinctiveBigrams(string $text): array
    {
        $sequence = $this->tokenSequence($text);
        $bigrams = [];

        for ($i = 0, $max = count($sequence) - 1; $i < $max; $i++) {
            if ($sequence[$i] !== '' && $sequence[$i + 1] !== '') {
                $bigrams[] = $sequence[$i].' '.$sequence[$i + 1];
            }
        }

        return array_values(array_unique($bigrams));
    }

    /**
     * @param  array<int, array<string, mixed>>  $sessionEvents
     * @param  array<string, mixed>  $voiceProfile
     */
    private function render(
        Story $story,
        SessionAdaptation $session,
        int $totalSessions,
        array $sessionEvents,
        array $voiceProfile,
        ?PlayableSessionCut $cut,
        string $authoredDetail = 'full',
    ): string {
        $adaptation = $session->storyAdaptation;
        $trimming = (array) ($adaptation->ip_trimming ?? []);
        $sessionMap = (array) ($adaptation->story_session_map ?? []);
        $allocation = $this->findAllocationRow($sessionMap, $session->session_number);
        $architecture = (array) ($session->session_architecture ?? []);
        $choiceDesign = (array) ($session->session_choice_design ?? []);
        $consequenceMap = (array) ($session->choice_consequence_map ?? []);
        $chaptersCovered = (string) ($allocation['chapters_covered'] ?? '');
        $sessionSpine = [
            'dramatic_question' => (string) ($allocation['primary_dramatic_question'] ?? ''),
            'emotional_promise' => (string) (($session->entry_point_diagnosis['emotional_promise'] ?? '')),
            'emotional_register' => (string) ($allocation['emotional_register'] ?? ''),
            'chapters_covered' => $chaptersCovered,
            // Section 10 arc-position label — identifies the session by its chapter scope.
            'session_label' => sprintf(
                'Session %d%s',
                $session->session_number,
                $chaptersCovered !== '' ? ': '.$chaptersCovered : '',
            ),
            // Section 11 SESSION DESTINATION — the narrative close hook for this session.
            'session_destination' => (string) (
                $session->session_close_design['hook_transition']
                ?? $session->session_close_design['session_end_choice']['choice_question']
                ?? ''
            ),
            'next_session_seed' => (string) ($architecture['next_session_awareness']['seed_for_next_session'] ?? ''),
        ];
        $persistentState = $this->scopePersistentState(
            (array) ($sessionMap['persistent_state_schema'] ?? []),
            $sessionEvents,
            $sessionSpine,
            $architecture,
            $choiceDesign,
            $consequenceMap,
        );
        $boilerMoments = $cut !== null
            ? $cut->moments
                ->sortBy('moment_order')
                ->map(fn ($moment): array => (array) $moment->scene_card)
                ->values()
                ->all()
            : [];

        $rendered = View::make('ai.agents.chaos.runtime-narrator-template', self::withoutBracketTokens([
            'storyTitle' => $story->title,
            'authorName' => $story->creator?->full_name ?? 'the author',
            'sessionNumber' => $session->session_number,
            'totalSessions' => $totalSessions,
            'protagonist' => $this->protagonistName($story, $adaptation),

            'spine' => $this->normalizeSpine($trimming, $story),
            'worldRules' => (array) ($trimming['world_rules'] ?? []),
            'storyGuard' => (array) ($sessionMap['story_guard_canon'] ?? []),
            'sceneRules' => (array) ($choiceDesign['scene_rules_layer_4'] ?? []),
            'voice' => $this->withoutSameSceneExemplars($voiceProfile, $boilerMoments, $story, $session),
            'persistentState' => $persistentState,
            'reactivityRules' => (array) ($sessionMap['world_reactivity_rules'] ?? []),
            'alignmentLabels' => (array) ($sessionMap['alignment_labels'] ?? []),

            'sessionSpine' => $sessionSpine,
            'beatMap' => (array) ($architecture['beat_map'] ?? []),
            'sessionEvents' => $sessionEvents,
            'boilerActive' => $cut !== null,
            'boilerManifest' => $cut !== null ? (array) $session->boiler_final_cut : [],
            'boilerMoments' => $boilerMoments,

            'branchingChoices' => (array) ($choiceDesign['branching_choices'] ?? []),
            'emotionalChoices' => (array) ($choiceDesign['emotional_choices'] ?? []),
            'postureShifts' => (array) ($choiceDesign['posture_shifts'] ?? []),

            'consequenceMaps' => $this->projectConsequenceVisibility(
                (array) ($consequenceMap['branching_consequences'] ?? []),
                $authoredDetail,
            ),
            'freeformGuidelines' => $this->projectFreeformGuidelines(
                (array) ($consequenceMap['freeform_guidelines'] ?? []),
                $authoredDetail,
            ),

            'editorialStatus' => (string) (
                data_get($session->editorial_verification, 'final_verdict.production_status')
                ?? data_get($session->editorial_verification, 'production_status')
                ?? 'UNVERIFIED'
            ),
        ]))->render();

        return html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $adaptation
     */
    private function protagonistName(Story $story, $adaptation): string
    {
        return (string) (
            ChaosStoryConfig::find($story->slug)['protagonist'] ?? null
            ?? $adaptation->ip_trimming['story_spine']['protagonist_name'] ?? null
            ?? $adaptation->ip_trimming['story_spine']['protagonist']
            ?? $adaptation->voice_profile['author_voice_dna_profile']['dialogue_fingerprint_per_character'][0]['character']
            ?? 'the protagonist'
        );
    }

    /**
     * @param  array<string, mixed>  $trimming
     * @return array<string, mixed>
     */
    private function normalizeSpine(array $trimming, Story $story): array
    {
        $spine = (array) ($trimming['story_spine'] ?? []);

        // ChaosStoryConfig is the canonical playable protagonist — it takes
        // priority over whatever the AI wrote during the trimming merge pass.
        $protagonist = ChaosStoryConfig::find($story->slug)['protagonist']
            ?? (string) ($spine['protagonist'] ?? 'the protagonist');

        return [
            'protagonist' => $protagonist,
            'dramatic_question' => (string) ($spine['dramatic_question'] ?? '(not specified)'),
            'world' => (string) ($spine['world'] ?? '(not specified)'),
            'major_turning_points' => (array) ($spine['major_turning_points'] ?? []),
            'irreversible_events' => (array) ($spine['irreversible_events'] ?? []),
        ];
    }

    /**
     * Scope the static tracking schema to the current session without touching
     * actual runtime memory. `chaos_sessions.world_state` still carries every
     * real player interaction across sessions; this only keeps future schema
     * details from flooding early prompts.
     *
     * @param  array<string, mixed>  $persistentState
     * @param  array<int, array<string, mixed>>  $sessionEvents
     * @param  array<string, mixed>  $sessionSpine
     * @param  array<string, mixed>  $architecture
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $consequenceMap
     * @return array<string, mixed>
     */
    private function scopePersistentState(
        array $persistentState,
        array $sessionEvents,
        array $sessionSpine,
        array $architecture,
        array $choiceDesign,
        array $consequenceMap,
    ): array {
        $haystack = $this->stateScopeHaystack(
            $sessionEvents,
            $sessionSpine,
            $architecture,
            $choiceDesign,
            $consequenceMap,
        );

        $objects = (array) ($persistentState['objects'] ?? []);
        $npcs = (array) ($persistentState['npcs'] ?? []);
        $flags = (array) ($persistentState['world_flags'] ?? []);

        $activeObjects = [];
        $dormantObjects = [];
        foreach ($objects as $object) {
            $name = (string) ($object['name'] ?? '');
            if ($this->stateEntryMatches($name, $haystack, requireAllTokens: true)) {
                $activeObjects[] = $object;
            } elseif ($name !== '') {
                $dormantObjects[] = $name;
            }
        }

        $activeNpcs = [];
        $dormantNpcs = [];
        foreach ($npcs as $npc) {
            $name = (string) ($npc['name'] ?? '');
            if ($this->stateEntryMatches($name, $haystack, requireAllTokens: false)) {
                $activeNpcs[] = $npc;
            } elseif ($name !== '') {
                $dormantNpcs[] = $name;
            }
        }

        $activeFlags = [];
        $dormantFlags = [];
        foreach ($flags as $flag) {
            $name = (string) ($flag['name'] ?? '');
            if ($this->stateEntryMatches($name, $haystack, requireAllTokens: false)) {
                $activeFlags[] = $flag;
            } elseif ($name !== '') {
                $dormantFlags[] = $name;
            }
        }

        return [
            'objects' => $activeObjects,
            'npcs' => $activeNpcs,
            'world_flags' => $activeFlags,
            'dormant_objects' => $dormantObjects,
            'dormant_npcs' => $dormantNpcs,
            'dormant_world_flags' => $dormantFlags,
            'player_historical_archive_categories' => (array) ($persistentState['player_historical_archive_categories'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sessionEvents
     * @param  array<string, mixed>  $sessionSpine
     * @param  array<string, mixed>  $architecture
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $consequenceMap
     */
    private function stateScopeHaystack(
        array $sessionEvents,
        array $sessionSpine,
        array $architecture,
        array $choiceDesign,
        array $consequenceMap,
    ): string {
        $payload = [
            'events' => array_map(static fn (array $event) => [
                'title' => $event['title'] ?? '',
                'objectives' => $event['objectives'] ?? '',
                'content' => $event['content'] ?? '',
                'chapter_title' => $event['chapter_title'] ?? '',
            ], $sessionEvents),
            'session_spine' => $sessionSpine,
            'beat_map' => $architecture['beat_map'] ?? [],
            'choice_design' => [
                'branching_choices' => $choiceDesign['branching_choices'] ?? [],
                'emotional_choices' => $choiceDesign['emotional_choices'] ?? [],
                'posture_shifts' => $choiceDesign['posture_shifts'] ?? [],
            ],
            'consequence_map' => $consequenceMap,
        ];

        return $this->normalizeStateText(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function stateEntryMatches(string $name, string $haystack, bool $requireAllTokens): bool
    {
        $tokens = $this->stateNameTokens($name);
        if ($tokens === []) {
            return false;
        }

        $hits = array_filter($tokens, static fn (string $token) => str_contains($haystack, $token));

        return $requireAllTokens
            ? count($hits) === count($tokens)
            : $hits !== [];
    }

    /**
     * @return array<int, string>
     */
    private function stateNameTokens(string $name): array
    {
        $normalized = $this->normalizeStateText($name);
        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $stopwords = [
            'a', 'an', 'and', 'as', 'by', 'current', 'for', 'in', 'of', 'or',
            'state', 'status', 'the', 'to', 'with',
        ];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token) => mb_strlen($token) >= 3 && ! in_array($token, $stopwords, true),
        )));
    }

    private function normalizeStateText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower($text);

        return mb_trim((string) preg_replace('/[^a-z0-9]+/u', ' ', $text));
    }

    /**
     * @param  array<string, mixed>  $sessionMap
     * @return array<string, mixed>
     */
    private function findAllocationRow(array $sessionMap, int $sessionNumber): array
    {
        foreach ((array) ($sessionMap['session_allocation'] ?? []) as $row) {
            if ((int) ($row['session_number'] ?? 0) === $sessionNumber) {
                return (array) $row;
            }
        }

        return [];
    }

    /**
     * Compression strategy 1: keep first + last event verbatim; replace
     * middle events with title + objective summaries only.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function compressSourceEvents(array $events): array
    {
        if (count($events) <= 2) {
            return $events;
        }

        $first = array_shift($events);
        $last = array_pop($events);

        $middle = array_map(static fn ($e) => [
            'id' => $e['id'] ?? null,
            'chapter_id' => $e['chapter_id'] ?? null,
            'chapter_position' => $e['chapter_position'] ?? null,
            'chapter_title' => $e['chapter_title'] ?? null,
            'position' => $e['position'],
            'session_number' => $e['session_number'] ?? null,
            'title' => $e['title'],
            'objectives' => $e['objectives'],
            'content' => '(content compressed to save context budget — see objective above for dramatic shape; this beat falls between the first and last events.)',
        ], $events);

        return array_merge([$first], $middle, [$last]);
    }

    /**
     * Compression strategy 2: titles + objectives only, all events.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function titlesOnlySourceEvents(array $events): array
    {
        return array_map(static fn ($e) => [
            'id' => $e['id'] ?? null,
            'chapter_id' => $e['chapter_id'] ?? null,
            'chapter_position' => $e['chapter_position'] ?? null,
            'chapter_title' => $e['chapter_title'] ?? null,
            'position' => $e['position'],
            'session_number' => $e['session_number'] ?? null,
            'title' => $e['title'],
            'objectives' => $e['objectives'],
            'content' => '(source compressed to title + objective only.)',
        ], $events);
    }

    /**
     * Voice compression: drop the source-quote arrays from the voice DNA
     * profile (keep technique names, ban list, sentence/diction summary).
     *
     * D8 v2 ANCHOR PROTECTION: This method operates exclusively on
     * author_voice_dna_profile and NEVER touches the top-level anchor fields:
     *   - voice_anchor   (Section 4A — cut last, floor of 5 exemplars)
     *   - anchor_card    (Section 18 — never cut)
     *   - runtime_self_check (Section 18 — never cut)
     * These are load-bearing and must survive every compression pass intact.
     *
     * @param  array<string, mixed>  $voice
     * @return array<string, mixed>
     */
    private function dropVoiceQuotes(array $voice): array
    {
        $dna = $voice['author_voice_dna_profile'] ?? [];

        // ── Existing quote-strip passes ────────────────────────────────
        foreach ((array) ($dna['signature_writing_techniques'] ?? []) as $i => $t) {
            $dna['signature_writing_techniques'][$i]['quotes'] = [];
        }
        if (isset($dna['sentence_level_patterns']['demonstrative_quotes'])) {
            $dna['sentence_level_patterns']['demonstrative_quotes'] = [];
        }
        if (isset($dna['diction_fingerprint']['distinctive_diction_quotes'])) {
            $dna['diction_fingerprint']['distinctive_diction_quotes'] = [];
        }
        if (isset($dna['paragraph_architecture']['demonstrative_quotes'])) {
            $dna['paragraph_architecture']['demonstrative_quotes'] = [];
        }
        foreach ((array) ($dna['emotional_range_map'] ?? []) as $key => $entry) {
            $dna['emotional_range_map'][$key]['quote'] = '';
        }
        foreach ((array) ($dna['collocation_fingerprint'] ?? []) as $i => $col) {
            $dna['collocation_fingerprint'][$i]['quotes'] = [];
        }
        foreach ((array) ($dna['comparative_exclusion'] ?? []) as $i => $ex) {
            $dna['comparative_exclusion'][$i]['differentiating_techniques'] = array_map(
                fn ($t) => is_string($t) ? strtok($t, '—') ?: $t : $t,
                (array) ($ex['differentiating_techniques'] ?? [])
            );
        }

        // ── 1B v2 screenwriter quote-strip passes ──────────────────────
        // Action line metrics — evidence quotes
        if (isset($dna['action_line_metrics']['demonstrative_quotes'])) {
            $dna['action_line_metrics']['demonstrative_quotes'] = [];
        }

        // Emotional vocabulary hierarchy — representative quotes per category
        foreach ((array) ($dna['emotional_vocabulary_hierarchy'] ?? []) as $i => $cat) {
            $dna['emotional_vocabulary_hierarchy'][$i]['representative_quotes'] = [];
        }

        // Scene Transition Compression Protocol — closing-line example quotes
        // (guidance text and distribution data are preserved)
        if (isset($dna['scene_transition_compression_protocol']['closing_line_examples'])) {
            $dna['scene_transition_compression_protocol']['closing_line_examples'] = [];
        }

        // Dialogue fingerprint — evidence/quote fields
        foreach ((array) ($dna['dialogue_fingerprint_per_character'] ?? []) as $i => $char) {
            // signature_line is load-bearing runtime content; keep it
            // verbal_tics and distinguishing_markers are compact arrays; keep them
            // No direct quote arrays on character fingerprint in v2 schema
            unset($char); // suppress unused-variable
        }

        // ── Numeric enforcement fields — DO NOT TOUCH ──────────────────
        // numerical_enforcement_layer, rhythm_transition_architecture,
        // beat_architecture_protocol, scene_transition_compression_protocol
        // (minus closing_line_examples already stripped above) are all
        // preserved because they carry machine-checkable constraints.

        // ── voice_decay_prevention_protocol (top-level) — DO NOT TOUCH ─
        // The structured re_anchoring_trigger, passage_level_enforcement_checks,
        // and drift_detection_metrics are live runtime self-audit instructions.

        $voice['author_voice_dna_profile'] = $dna;

        return $voice;
    }
}
