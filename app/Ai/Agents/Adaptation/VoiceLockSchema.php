<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Pipeline Upgrade V2.3 — Voice Lock structured output schemas.
 *
 * Novelist (1A v2) and screenwriter (1B v3) profiles share a base shape with
 * format-specific extensions.
 *
 * V2.3 additions (additive — existing runtime keys preserved):
 *   TOP-LEVEL (runtime-critical, REQUIRED for V2.3 profiles):
 *     voice_anchor[]            — locked prose exemplars (Task 3); loaded verbatim into D8 v2
 *     anchor_card[]             — binary/local rules re-read every turn (Task 4)
 *     runtime_self_check[]      — ordered pre-delivery check steps (Task 5)
 *   TOP-LEVEL (build-time only, REQUIRED for V2.3 profiles):
 *     build_time_qa_protocol    — QA gate run before IP ships; never reaches runtime (Task 6)
 *   LEGACY (preserved if present, NOT required for V2.3 profiles):
 *     fourteen_point_audit_protocol  — old V2.2 audit; kept so old profiles keep working
 *     voice_decay_prevention_protocol — old V2.2 VDPP (screenwriter only); kept for backward compat
 */
final class VoiceLockSchema
{
    /**
     * Route to the 1B (screenwriter) path for any screenplay-family format label.
     * Defensive: FormatDetectionAgent currently only emits SCREENPLAY | NOVEL, but
     * future subtypes (TELEPLAY, PILOT, LIMITED_SERIES) will route correctly here
     * without touching FormatDetectionAgent or pipeline wiring.
     */
    public static function isScreenwriter(?string $detectedFormat): bool
    {
        return in_array(mb_strtoupper((string) $detectedFormat), [
            'SCREENPLAY', 'TELEPLAY', 'PILOT', 'LIMITED_SERIES',
        ], true);
    }

    public static function mergeSchema(JsonSchema $schema, ?string $detectedFormat): array
    {
        return self::isScreenwriter($detectedFormat)
            ? self::screenwriterSchema($schema)
            : self::novelistSchema($schema);
    }

    public static function novelistSchema(JsonSchema $schema): array
    {
        return self::buildSchema($schema, 'NOVELIST', includeNovelistFields: true, includeScreenwriterFields: false);
    }

    public static function screenwriterSchema(JsonSchema $schema): array
    {
        return self::buildSchema($schema, 'SCREENWRITER', includeNovelistFields: false, includeScreenwriterFields: true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildSchema(
        JsonSchema $schema,
        string $profileType,
        bool $includeNovelistFields,
        bool $includeScreenwriterFields,
    ): array {
        $signatureTechniqueSchema = $schema->object([
            'name' => $schema->string()->required()->title('Name')->description('2-4 words.'),
            'quotes' => $schema->array()->required()->title('Quotes')->description('2-3 direct quotes from the source.')->items($schema->string()->required()),
            'why_this_author' => $schema->string()->required()->title('Why This Author')->description('One sentence: what makes this technique specific to THIS author.'),
            'frequency' => $schema->string()->required()->title('Frequency')->description('Approximate rate this technique appears in the source.'),
        ])->required()->withoutAdditionalProperties();

        $sentencePatternsSchema = $schema->object([
            'average_sentence_length' => $schema->string()->required()->title('Average Sentence Length'),
            'cadence_variation' => $schema->string()->required()->title('Cadence Variation Pattern'),
            'clause_structure_preference' => $schema->string()->required()->title('Clause Structure Preference'),
            'punctuation_habits' => $schema->string()->required()->title('Punctuation Habits'),
            'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $dictionFingerprintSchema = $schema->object([
            'vocabulary_clusters' => $schema->array()->required()->title('Vocabulary Clusters')->items($schema->string()->required()),
            'register_and_formality' => $schema->string()->required()->title('Register And Formality'),
            'word_frequency_patterns' => $schema->string()->required()->title('Word Frequency Patterns'),
            'distinctive_diction_quotes' => $schema->array()->required()->title('Distinctive Diction Quotes')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $characterDialogueSchema = $schema->object([
            'character' => $schema->string()->required()->title('Character'),
            'speech_rhythm' => $schema->string()->required()->title('Speech Rhythm'),
            'verbal_tics_or_recurring_phrases' => $schema->array()->required()->title('Verbal Tics Or Recurring Phrases')->items($schema->string()->required()),
            'words_they_would_never_say' => $schema->array()->required()->title('Words They Would Never Say')->items($schema->string()->required()),
            'emotional_range_in_dialogue' => $schema->string()->required()->title('Emotional Range In Dialogue'),
            'distinguishing_markers' => $schema->array()->required()->title('Distinguishing Markers')->description('At least 3 linguistic markers unique to this character.')->items($schema->string()->required()),
            'signature_line' => $schema->string()->required()->title('Signature Line'),
        ])->required()->withoutAdditionalProperties();

        $emotionalRegisterSchema = $schema->object([
            'register' => $schema->string()->required()->title('Register'),
            'quote' => $schema->string()->required()->title('Quote'),
            'technique' => $schema->string()->required()->title('Technique'),
            'rendering_method' => $schema->string()->required()->title('Rendering Method'),
        ])->required()->withoutAdditionalProperties();

        $collocationSchema = $schema->object([
            'pair' => $schema->string()->required()->title('Pair')->description('Author exact collocation, e.g. "clay pipe".'),
            'quotes' => $schema->array()->required()->title('Quotes')->items($schema->string()->required()),
            'frequency' => $schema->string()->required()->title('Frequency'),
            'ai_substitution' => $schema->string()->required()->title('AI Substitution')->description('What a generic AI would write instead.'),
            'category' => $schema->string()->required()->title('Category'),
        ])->required()->withoutAdditionalProperties();

        $negativeSpaceSchema = $schema->object([
            'technique' => $schema->string()->required()->title('Technique'),
            'absence_evidence' => $schema->string()->required()->title('Absence Evidence'),
            'why_ai_defaults' => $schema->string()->required()->title('Why AI Defaults'),
        ])->required()->withoutAdditionalProperties();

        $comparativeExclusionSchema = $schema->object([
            'neighbor_author' => $schema->string()->required()->title('Neighbor Author'),
            'overlapping_quality' => $schema->string()->required()->title('Overlapping Quality'),
            'differentiating_techniques' => $schema->array()->required()->title('Differentiating Techniques')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $ipSpecificBanSchema = $schema->object([
            'ban' => $schema->string()->required()->title('Ban'),
            'evidence' => $schema->string()->required()->title('Evidence'),
            'positive_replacement' => $schema->string()->required()->title('Positive Replacement'),
        ])->required()->withoutAdditionalProperties();

        $auditPointSchema = $schema->object([
            'point_number' => $schema->number()->required()->title('Point Number'),
            'point_name' => $schema->string()->required()->title('Point Name'),
            'pass_fail_definition' => $schema->string()->required()->title('Pass Fail Definition'),
            'detection_method' => $schema->string()->required()->title('Detection Method'),
            'repair_instruction' => $schema->string()->required()->title('Repair Instruction'),
        ])->required()->withoutAdditionalProperties();

        $dnaProfileFields = [
            'signature_writing_techniques' => $schema->array()->required()->title('Signature Writing Techniques')->description('8-12 entries.')->items($signatureTechniqueSchema),
            'sentence_level_patterns' => $sentencePatternsSchema->title('Sentence-Level Patterns'),
            'diction_fingerprint' => $dictionFingerprintSchema->title('Diction Fingerprint'),
            'dialogue_fingerprint_per_character' => $schema->array()->required()->title('Dialogue Fingerprint Per Character')->items($characterDialogueSchema),
            'emotional_range_map' => $schema->object([
                'tension' => (clone $emotionalRegisterSchema)->title('Tension'),
                'humor' => (clone $emotionalRegisterSchema)->title('Humor'),
                'grief' => (clone $emotionalRegisterSchema)->title('Grief'),
                'wonder' => (clone $emotionalRegisterSchema)->title('Wonder'),
                'fear' => (clone $emotionalRegisterSchema)->title('Fear'),
                'violence' => (clone $emotionalRegisterSchema)->title('Violence'),
                'intimacy' => (clone $emotionalRegisterSchema)->title('Intimacy'),
            ])->required()->withoutAdditionalProperties()->title('Emotional Range Map'),
            'collocation_fingerprint' => $schema->array()->required()->title('Collocation Fingerprint')->description('15-20 characteristic word pairs.')->items($collocationSchema),
            'negative_space_map' => $schema->array()->required()->title('Negative Space Map')->description('Minimum 5 genre-default techniques this author never uses.')->items($negativeSpaceSchema),
            'show_explain_ratio' => $schema->object([
                'show_language_description' => $schema->string()->required()->title('Show Language Description'),
                'explain_language_description' => $schema->string()->required()->title('Explain Language Description'),
                'approximate_balance' => $schema->string()->required()->title('Approximate Balance'),
                'enforcement_note' => $schema->string()->required()->title('Enforcement Note'),
            ])->required()->withoutAdditionalProperties()->title('Show Explain Ratio'),
            'comparative_exclusion' => $schema->array()->required()->title('Comparative Exclusion')->description('2-3 stylistic neighbors.')->items($comparativeExclusionSchema),
        ];

        if ($includeNovelistFields) {
            $dnaProfileFields['narrator_perspective'] = $schema->object([
                'point_of_view' => $schema->string()->required()->title('Point Of View'),
                'reliability' => $schema->string()->required()->title('Reliability'),
                'distance' => $schema->string()->required()->title('Distance'),
                'commentary' => $schema->string()->required()->title('Commentary'),
                'tense' => $schema->string()->required()->title('Tense'),
                'interior_monologue' => $schema->string()->required()->title('Interior Monologue'),
                'representative_quotes' => $schema->array()->required()->title('Representative Quotes')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Narrator Perspective');

            $dnaProfileFields['paragraph_architecture'] = $schema->object([
                'pattern' => $schema->string()->required()->title('Pattern'),
                'transition_method' => $schema->string()->required()->title('Transition Method'),
                'chapter_opening_style' => $schema->string()->required()->title('Chapter Opening Style'),
                'chapter_closing_style' => $schema->string()->required()->title('Chapter Closing Style'),
                'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Paragraph Architecture');

            $dnaProfileFields['dialogue_tag_patterns'] = $schema->object([
                'said_percentage' => $schema->string()->required()->title('Said Percentage'),
                'other_tags' => $schema->array()->required()->title('Other Tags')->items($schema->string()->required()),
                'action_beats_frequency' => $schema->string()->required()->title('Action Beats Frequency'),
                'banned_tags' => $schema->array()->required()->title('Banned Tags')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Dialogue Tag Patterns');
        }

        if ($includeScreenwriterFields) {
            $dnaProfileFields['action_line_metrics'] = $schema->object([
                'average_words_per_line' => $schema->string()->required()->title('Average Words Per Line'),
                'fragment_percentage' => $schema->string()->required()->title('Fragment Percentage'),
                'verb_first_percentage' => $schema->string()->required()->title('Verb First Percentage'),
                'all_caps_density' => $schema->string()->required()->title('All Caps Density'),
                'paragraph_rhythm' => $schema->string()->required()->title('Paragraph Rhythm'),
                'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Action Line Metrics');

            $dnaProfileFields['screenplay_structure_metrics'] = $schema->object([
                'scene_density' => $schema->string()->required()->title('Scene Density'),
                'int_ext_ratio' => $schema->string()->required()->title('Int Ext Ratio'),
                'action_to_dialogue_ratio' => $schema->string()->required()->title('Action To Dialogue Ratio'),
                'transition_types' => $schema->string()->required()->title('Transition Types'),
                'parenthetical_vocabulary' => $schema->string()->required()->title('Parenthetical Vocabulary'),
                'character_introduction_patterns' => $schema->string()->required()->title('Character Introduction Patterns'),
            ])->required()->withoutAdditionalProperties()->title('Screenplay Structure Metrics');

            $dnaProfileFields['emotional_vocabulary_hierarchy'] = $schema->array()->required()->title('Emotional Vocabulary Hierarchy')->description('Ranked categories with representative quotes.')->items(
                $schema->object([
                    'category' => $schema->string()->required()->title('Category'),
                    'rank' => $schema->number()->required()->title('Rank'),
                    'representative_quotes' => $schema->array()->required()->title('Representative Quotes')->items($schema->string()->required()),
                ])->required()->withoutAdditionalProperties()
            );

            // --- 1B v2 Section M: Numerical Enforcement Layer ---
            // Each metric carries target/floor/ceiling/confidence/sample_size.
            // Derived by merge from summed raw chapter counts (1C contract).
            $metricSpecSchema = $schema->object([
                'target' => $schema->string()->required()->title('Target')->description('Target range aim for.'),
                'floor' => $schema->string()->required()->title('Floor')->description('Minimum acceptable — violation triggers rejection.'),
                'ceiling' => $schema->string()->required()->title('Ceiling')->description('Maximum acceptable — violation triggers rejection.'),
                'confidence' => $schema->string()->required()->title('Confidence')->description('ABSOLUTE | HIGH | MEDIUM | LOW'),
                'sample_size' => $schema->string()->required()->title('Sample Size')->description('Data points or instance count.'),
            ])->required()->withoutAdditionalProperties();

            $dialogueCeilingSchema = $schema->object([
                'character' => $schema->string()->required()->title('Character'),
                'avg_words' => $schema->string()->required()->title('Avg Words'),
                'p90_words' => $schema->string()->required()->title('P90 Words'),
                'p95_words' => $schema->string()->required()->title('P95 Words'),
                'max_words' => $schema->string()->required()->title('Max Words — Hard Ceiling'),
                'speech_count' => $schema->number()->required()->title('Speech Count'),
                'confidence' => $schema->string()->required()->title('Confidence'),
            ])->required()->withoutAdditionalProperties();

            $openerTypeSpecSchema = $schema->object([
                'opener_type' => $schema->string()->required()->title('Opener Type'),
                'target' => $schema->string()->required()->title('Target %'),
                'floor' => $schema->string()->required()->title('Floor %'),
                'ceiling' => $schema->string()->required()->title('Ceiling %'),
                'confidence' => $schema->string()->required()->title('Confidence'),
            ])->required()->withoutAdditionalProperties();

            $dnaProfileFields['numerical_enforcement_layer'] = $schema->object([
                'punctuation' => $schema->object([
                    'period_density_per_100w' => (clone $metricSpecSchema)->title('Period Density Per 100w'),
                    'comma_density_per_100w' => (clone $metricSpecSchema)->title('Comma Density Per 100w'),
                    'semicolons' => (clone $metricSpecSchema)->title('Semicolons')->description('If zero across source: ABSOLUTE HARD BAN.'),
                    'exclamation_marks_narration' => (clone $metricSpecSchema)->title('Exclamation Marks Narration'),
                    'em_dashes' => (clone $metricSpecSchema)->title('Em Dashes'),
                    'question_marks_narration' => (clone $metricSpecSchema)->title('Question Marks Narration'),
                    'question_marks_dialogue' => (clone $metricSpecSchema)->title('Question Marks Dialogue'),
                    'ellipses_narration' => (clone $metricSpecSchema)->title('Ellipses Narration'),
                    'ellipses_dialogue' => (clone $metricSpecSchema)->title('Ellipses Dialogue'),
                    'period_to_comma_ratio' => (clone $metricSpecSchema)->title('Period To Comma Ratio'),
                ])->required()->withoutAdditionalProperties()->title('Punctuation Enforcement'),
                'rhythm' => $schema->object([
                    'sentence_length_1_3w' => (clone $metricSpecSchema)->title('Sentence Length 1-3w %'),
                    'sentence_length_4_5w' => (clone $metricSpecSchema)->title('Sentence Length 4-5w %'),
                    'sentence_length_6_8w' => (clone $metricSpecSchema)->title('Sentence Length 6-8w %'),
                    'sentence_length_9_12w' => (clone $metricSpecSchema)->title('Sentence Length 9-12w %'),
                    'sentence_length_13_18w' => (clone $metricSpecSchema)->title('Sentence Length 13-18w %'),
                    'sentence_length_19_25w' => (clone $metricSpecSchema)->title('Sentence Length 19-25w %'),
                    'sentence_length_26_plus_w' => (clone $metricSpecSchema)->title('Sentence Length 26+w %'),
                    'fragment_rate' => (clone $metricSpecSchema)->title('Fragment Rate'),
                    'verb_first_percentage' => (clone $metricSpecSchema)->title('Verb First Opening %'),
                    'ing_opening_percentage' => (clone $metricSpecSchema)->title('-ing Opening % (AI over-deploys — ceiling enforced)'),
                    'rhythm_change_frequency' => (clone $metricSpecSchema)->title('Rhythm Change Frequency %'),
                ])->required()->withoutAdditionalProperties()->title('Rhythm Enforcement'),
                'dialogue_ceilings_per_character' => $schema->array()->required()->title('Dialogue Ceilings Per Character')->items($dialogueCeilingSchema),
                'opener_distribution' => $schema->array()->required()->title('Opener Distribution')->items($openerTypeSpecSchema),
                'word_length' => $schema->object([
                    'average_chars' => (clone $metricSpecSchema)->title('Average Word Length (chars)'),
                    'bucket_1_3_chars_pct' => (clone $metricSpecSchema)->title('1-3 Char Words %'),
                    'bucket_4_5_chars_pct' => (clone $metricSpecSchema)->title('4-5 Char Words %'),
                    'bucket_6_8_chars_pct' => (clone $metricSpecSchema)->title('6-8 Char Words %'),
                    'bucket_9_plus_chars_pct' => (clone $metricSpecSchema)->title('9+ Char Words %'),
                ])->required()->withoutAdditionalProperties()->title('Word Length Enforcement'),
            ])->required()->withoutAdditionalProperties()->title('Numerical Enforcement Layer — Section M');

            // --- 1B v2 Section N: Rhythm Transition Architecture ---
            // 4x4 transition probability matrix (ultra_short / short / medium / long)
            $rhythmRowSchema = $schema->object([
                'ultra_short' => $schema->number()->required()->title('→ Ultra-Short %'),
                'short' => $schema->number()->required()->title('→ Short %'),
                'medium' => $schema->number()->required()->title('→ Medium %'),
                'long' => $schema->number()->required()->title('→ Long %'),
            ])->required()->withoutAdditionalProperties();

            $dnaProfileFields['rhythm_transition_architecture'] = $schema->object([
                'transition_matrix' => $schema->object([
                    'ultra_short' => (clone $rhythmRowSchema)->title('After Ultra-Short (1-3w)'),
                    'short' => (clone $rhythmRowSchema)->title('After Short (4-6w)'),
                    'medium' => (clone $rhythmRowSchema)->title('After Medium (7-12w)'),
                    'long' => (clone $rhythmRowSchema)->title('After Long (13+w)'),
                ])->required()->withoutAdditionalProperties()->title('4x4 Transition Matrix (probabilities, %)'),
                'rhythm_change_frequency' => $schema->string()->required()->title('Rhythm Change Frequency')->description('% consecutive lines that change length category.'),
                'max_consecutive_same_category' => $schema->string()->required()->title('Max Consecutive Same-Category')->description('Hard ceiling; e.g. "never >3 consecutive ultra-short".'),
                'signature_moves' => $schema->array()->required()->title('Signature Moves')->description('2-3 characteristic transition patterns with evidence.')->items($schema->string()->required()),
                'anti_patterns' => $schema->array()->required()->title('Anti-Patterns')->description('Transitions never or rarely made in source.')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Rhythm Transition Architecture — Section N');

            // --- 1B v2 Section O: Beat Architecture Protocol ---
            $beatVocabularySchema = $schema->object([
                'status_beats' => $schema->array()->required()->title('Status Beats')->items($schema->string()->required()),
                'action_beats' => $schema->array()->required()->title('Action Beats')->items($schema->string()->required()),
                'transition_beats' => $schema->array()->required()->title('Transition Beats')->items($schema->string()->required()),
                'emphasis_beats' => $schema->array()->required()->title('Emphasis Beats')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties();

            $dnaProfileFields['beat_architecture_protocol'] = $schema->object([
                'beat_frequency' => $schema->string()->required()->title('Beat Frequency')->description('% of total action lines that are 1-2 word standalone beats.'),
                'beat_vocabulary' => $beatVocabularySchema->title('Beat Vocabulary'),
                'beat_placement' => $schema->string()->required()->title('Beat Placement')->description('Where beats appear: before scene changes, after action, etc.'),
                'beat_density_by_context' => $schema->string()->required()->title('Beat Density By Context')->description('Whether beats cluster more in action, emotional, or transition scenes.'),
            ])->required()->withoutAdditionalProperties()->title('Beat Architecture Protocol — Section O');

            // --- 1B v2 Section P: Scene Transition Compression Protocol ---
            $dnaProfileFields['scene_transition_compression_protocol'] = $schema->object([
                'closing_line_avg_length' => $schema->string()->required()->title('Closing Line Avg Length')->description('Average word count of last action line before scene change.'),
                'closing_line_type_distribution' => $schema->object([
                    'image' => $schema->string()->required()->title('Image %'),
                    'action' => $schema->string()->required()->title('Action %'),
                    'status' => $schema->string()->required()->title('Status %'),
                    'dialogue_adjacent' => $schema->string()->required()->title('Dialogue-Adjacent %'),
                    'beat' => $schema->string()->required()->title('Beat %'),
                ])->required()->withoutAdditionalProperties()->title('Closing Line Type Distribution'),
                'closing_line_examples' => $schema->array()->required()->title('Closing Line Examples')->description('8-10 representative scene-closing action lines.')->items($schema->string()->required()),
                'transition_guidance' => $schema->string()->required()->title('Transition Guidance')->description('How the runtime narrator should end scenes.'),
            ])->required()->withoutAdditionalProperties()->title('Scene Transition Compression Protocol — Section P');

            // --- 1B v2 Section 4: Screenplay-to-Prose Protocol (object shape, replaces flat array) ---
            // Canonical path: author_voice_dna_profile.screenplay_to_prose_protocol.element_rules
            //                 author_voice_dna_profile.screenplay_to_prose_protocol.quantitative_translation_mappings
            $dnaProfileFields['screenplay_to_prose_protocol'] = $schema->object([
                'element_rules' => $schema->array()->required()->title('Element Rules')
                    ->description('Element-by-element rules: scene headings, action lines, dialogue, parentheticals, transitions.')
                    ->items(
                        $schema->object([
                            'screenplay_element' => $schema->string()->required()->title('Screenplay Element'),
                            'prose_translation_rule' => $schema->string()->required()->title('Prose Translation Rule'),
                        ])->required()->withoutAdditionalProperties()
                    ),
                'quantitative_translation_mappings' => $schema->array()->required()->title('Quantitative Translation Mappings')
                    ->description('Numerical translations from screenplay metrics to prose targets. Minimum 6 entries per deliverable table.')
                    ->items(
                        $schema->object([
                            'screenplay_metric' => $schema->string()->required()->title('Screenplay Metric'),
                            'source_value' => $schema->string()->required()->title('Source Value'),
                            'prose_target' => $schema->string()->required()->title('Prose Target'),
                            'drift_ceiling' => $schema->string()->required()->title('Drift Ceiling'),
                            'rationale' => $schema->string()->required()->title('Rationale'),
                        ])->required()->withoutAdditionalProperties()
                    ),
            ])->required()->withoutAdditionalProperties()->title('Screenplay To Prose Protocol — Section 4');
        }

        // --- V2.3 top-level runtime-critical fields (SCREENWRITER + NOVELIST) ---
        // voice_anchor: each element is a locked prose exemplar loaded verbatim into D8 v2.
        $voiceAnchorExemplarSchema = $schema->object([
            'mode' => $schema->string()->required()->title('Mode')
                ->description('cold_tension | physical_action | quiet_aftermath | environmental | dialogue_bearing | emotional_weight'),
            'source' => $schema->string()->required()->title('Source Moment')
                ->description('The source passage this exemplar was translated from.'),
            'techniques' => $schema->string()->required()->title('Techniques Demonstrated')
                ->description('2–3 signature techniques demonstrated in this exemplar.'),
            'prose' => $schema->string()->required()->title('Prose')
                ->description('90–150 words, second-person present-tense, where "you" is always the protagonist. Passes every ban. Loaded verbatim into D8 v2 Section 4A. Prose only: never append metadata or other field values to this string.'),
            'session_relevance' => $schema->string()->required()->title('Session Relevance')
                ->description('session_1_primary | session_2_primary | ... | all_sessions | later_session. Which sessions this exemplar suits. A separate property, never appended to prose.'),
        ])->required()->withoutAdditionalProperties();

        // --- V2.3 top-level build-time QA protocol (never ships to runtime) ---
        $buildTimeQaSchema = $schema->object([
            'quantitative_checks' => $schema->array()->required()->title('Quantitative Checks')
                ->description('8+ measurable or linter-assisted checks (fragment distribution, ABSOLUTE punctuation bans, speech ceilings, frequency drift).')
                ->items($schema->string()->required()),
            'judgment_checks' => $schema->array()->required()->title('Judgment Checks')
                ->description('Model or human reviewer criteria (blind attribution, comparative exclusion, character swap test).')
                ->items($schema->string()->required()),
            'decay_test_procedure' => $schema->string()->required()->title('Decay Test Procedure')
                ->description('How to compare first/last 200 words of a 600+ word continuous sample to detect voice drift.'),
        ])->required()->withoutAdditionalProperties()->title('Build-Time QA Protocol — Task 6 (never ships to runtime)');

        $topLevel = [
            'profile_type' => $schema->string()->required()->title('Profile Type')->description($profileType),

            'author_voice_dna_profile' => $schema->object($dnaProfileFields)
                ->required()
                ->withoutAdditionalProperties()
                ->title('Author Voice DNA Profile'),

            'master_rule_1_hard_bans' => $schema->object([
                'universal_bans_acknowledged' => $schema->boolean()->required()->title('Universal Bans Acknowledged'),
                'ip_specific_bans' => $schema->array()->required()->title('IP-Specific Bans')->description('Minimum 6 entries.')->items($ipSpecificBanSchema),
            ])->required()->withoutAdditionalProperties()->title('Master Rule 1 Hard Bans'),

            // --- V2.3 runtime-critical fields (★ required for V2.3 profiles) ---
            'voice_anchor' => $schema->array()->required()
                ->title('Voice Anchor — Task 3 ★ RUNTIME-CRITICAL')
                ->description('6–8 locked prose exemplars loaded verbatim into D8 v2 Section 4A. Last voice material cut under token pressure. Never cut anchor_card or runtime_self_check before this.')
                ->items($voiceAnchorExemplarSchema),

            'anchor_card' => $schema->array()->required()
                ->title('Anchor Card — Task 4 ★ RUNTIME-CRITICAL')
                ->description('8–12 binary/local commands re-read by narrator every turn. ABSOLUTE/HIGH-confidence only. Never cut.')
                ->items($schema->string()->required()),

            'runtime_self_check' => $schema->array()->required()
                ->title('Runtime Self-Check — Task 5 ★ RUNTIME-CRITICAL')
                ->description('Ordered sequence of 7 discrete/local check steps the narrator runs silently before each passage. No rate computations.')
                ->items($schema->string()->required()),

            // --- V2.3 build-time QA (never ships to runtime) ---
            'build_time_qa_protocol' => $buildTimeQaSchema,
        ];

        // LEGACY V2.2 fields (fourteen_point_audit_protocol, voice_decay_prevention_protocol)
        // are intentionally omitted from the merge schema. OpenAI structured output requires
        // every property key to appear in `required`; optional legacy fields break validation.
        // V2.3 profiles use build_time_qa_protocol + voice_anchor/anchor_card/runtime_self_check.
        // Downstream readers already tolerate absence via ?? [] / ?? null.

        return $topLevel;
    }
}
