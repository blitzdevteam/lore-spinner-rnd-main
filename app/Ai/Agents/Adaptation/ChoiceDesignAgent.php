<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

/**
 * Pipeline Upgrade V2 — Deliverable 4: Phase 5 Choice Design.
 *
 * Hard quotas from Deliverable 4:
 *   - exactly 4 branching choices (Identity / Methodology / Moral Weight / Session-End Hook)
 *   - 4-6 emotional/expressive choices
 *   - 6-10 posture shifts
 *
 * Output shape is designed to feed both the RuntimeNarratorTemplate blade
 * (resources/views/ai/agents/chaos/runtime-narrator-template.blade.php) and
 * `ChoiceDesignJob::enrichBranchDimensionRegistry()` directly. Each branching
 * choice carries:
 *   - choice_id (S{session}_C{n})
 *   - category (IDENTITY / METHODOLOGY / MORAL_WEIGHT / SESSION_END_HOOK)
 *   - beat (SETUP / ESCALATION / TWIST / RESOLUTION)
 *   - what_this_choice_tracks (Phase 2 branch dimension by name)
 *   - alignment_order (randomised A/B/C -> chaotic/lawful/neutral mapping)
 *   - narrative_setup, choice_question, all_paths_arrive_at
 *   - options[]: label, text, alignment, outcome, persistent_state_changes,
 *               world_noticed_signal, defining_line, downstream_effect, and
 *               (Choice #4 only) next_session_opens.
 *   - storyguard_manifest (Layer 1/3/4 + fold-back + freeform alignment).
 *   - values_in_tension / moral_weight_confirmation (Choice #3 only).
 *   - session_end_confirmation (Choice #4 only).
 *
 * Plus Task 7 scene_rules_layer_4 per scene and Task 8's 12 defining lines
 * (which already live inside each option, so this collection is a roll-up
 * convenience for Phase 8 / runtime template).
 */
#[Model('gpt-5.4')]
#[Temperature(0.7)]
#[Timeout(600)]
final class ChoiceDesignAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private bool $boilerEnabled = false) {}

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.choice-design.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $persistentStateChangesSchema = $schema->object([
            'inventory' => $schema->string()->required()->title('Inventory')->description('Specific changes or NONE.'),
            'npc_dispositions' => $schema->array()->required()->title('NPC Dispositions')->items(
                $schema->object([
                    'npc' => $schema->string()->required()->title('NPC'),
                    'shift' => $schema->string()->required()->title('Shift'),
                ])->required()->withoutAdditionalProperties()
            ),
            'environmental_flags' => $schema->array()->required()->title('Environmental Flags')->items($schema->string()->required()),
            'emotional_ledger_entries' => $schema->array()->required()->title('Emotional Ledger Entries')->description('e.g. "ACTS OF MERCY +1". Reference Phase 2 Task 6D category names.')->items($schema->string()->required()),
            'alignment_shift' => $schema->string()->required()->title('Alignment Shift')->description('CHAOTIC / LAWFUL / NEUTRAL +1. Internal only.'),
        ])->required()->withoutAdditionalProperties();

        $branchingOptionSchema = $schema->object([
            'label' => $schema->string()->required()->title('Label')->description('A / B / C.'),
            'text' => $schema->string()->required()->title('Option Text')->description('One declarative sentence, second-person present tense.'),
            'alignment' => $schema->string()->required()->title('Alignment')->description('CHAOTIC / LAWFUL / NEUTRAL — internal only, never surfaced to the player.'),
            'outcome' => $schema->string()->required()->title('Outcome')->description('115-125 words in the author\'s voice.'),
            'downstream_effect' => $schema->string()->required()->title('Downstream Effect')->description('One sentence summarising what immediately changes in the world if this option is taken.'),
            'persistent_state_changes' => (clone $persistentStateChangesSchema)->title('Persistent State Changes'),
            'world_noticed_signal' => $schema->string()->required()->title('World Noticed Signal')->description('1-2 sentences of in-world prose acknowledging the change. Never gamey, never meta.'),
            'defining_line' => $schema->string()->required()->title('Defining Line')->description('Task 8 — Social Echo, ≤20 words, author\'s voice, provocative without spoiling.'),
            'next_session_opens' => $schema->string()->required()->title('Next Session Opens')->description('Choice #4 only. One vivid sentence — tone, first image, first stakes. Empty string for other choices.'),
        ])->required()->withoutAdditionalProperties();

        $storyguardManifestSchema = $schema->object([
            'canon_boundaries' => $schema->array()->required()->title('Canon Boundaries')->items($schema->string()->required()),
            'character_truth' => $schema->array()->required()->title('Character Truth')->items(
                $schema->object([
                    'npc' => $schema->string()->required()->title('NPC'),
                    'authentic_reaction_to_unexpected_input' => $schema->string()->required()->title('Authentic Reaction To Unexpected Input'),
                ])->required()->withoutAdditionalProperties()
            ),
            'scene_integrity' => $schema->object([
                'available_objects' => $schema->array()->required()->title('Available Objects')->items($schema->string()->required()),
                'character_knowledge_limits' => $schema->string()->required()->title('Character Knowledge Limits'),
                'emotional_context' => $schema->string()->required()->title('Emotional Context'),
            ])->required()->withoutAdditionalProperties()->title('Scene Integrity'),
            'fold_back_path' => $schema->object([
                'nearest_safe_outcome' => $schema->string()->required()->title('Nearest Safe Outcome'),
                'why' => $schema->string()->required()->title('Why'),
            ])->required()->withoutAdditionalProperties()->title('Fold-Back Path'),
            'freeform_alignment_mapping' => $schema->object([
                'maps_to_chaotic' => $schema->string()->required()->title('Maps To Chaotic'),
                'maps_to_lawful' => $schema->string()->required()->title('Maps To Lawful'),
                'maps_to_neutral' => $schema->string()->required()->title('Maps To Neutral'),
                'spirit_at_this_moment' => $schema->string()->required()->title('Spirit At This Moment'),
            ])->required()->withoutAdditionalProperties()->title('Freeform Alignment Mapping'),
        ])->required()->withoutAdditionalProperties();

        $branchingChoiceSchema = $schema->object([
            'choice_id' => $schema->string()->required()->title('Choice ID')->description('S{session}_C{number}.'),
            'category' => $schema->string()->required()->title('Category')->description('IDENTITY / METHODOLOGY / MORAL_WEIGHT / SESSION_END_HOOK.'),
            'beat' => $schema->string()->required()->title('Beat')->description('SETUP / ESCALATION / TWIST / RESOLUTION.'),
            ...($this->boilerEnabled ? [
                'moment_key' => $schema->string()->required()->title('Boiler Moment Key'),
            ] : []),
            'source_moment' => $schema->string()->required()->title('Source Moment'),
            'what_this_choice_tracks' => $schema->string()->required()->title('What This Choice Tracks')->description('Must reference a Phase 2 branch dimension by name.'),
            'alignment_order' => $schema->object([
                'a' => $schema->string()->required()->title('A')->description('chaotic / lawful / neutral.'),
                'b' => $schema->string()->required()->title('B'),
                'c' => $schema->string()->required()->title('C'),
            ])->required()->withoutAdditionalProperties()->title('Alignment Order (randomised)'),
            'narrative_setup' => $schema->string()->required()->title('Narrative Setup')->description('2-4 sentences of second-person prose in the author\'s voice.'),
            'choice_question' => $schema->string()->required()->title('Choice Question'),
            'options' => $schema->array()->required()->title('Options')->description('Exactly 3 entries: labels A, B, C.')->items($branchingOptionSchema),
            'all_paths_arrive_at' => $schema->string()->required()->title('All Paths Arrive At'),
            'storyguard_manifest' => (clone $storyguardManifestSchema)->title('StoryGuard Manifest'),
            'values_in_tension' => $schema->array()->required()->title('Values In Tension')->description('Required for MORAL_WEIGHT — exactly 3 entries. Empty array for other choices.')->items($schema->string()->required()),
            'moral_weight_confirmation' => $schema->object([
                'each_option_reflects_a_genuine_value' => $schema->boolean()->required()->title('Each Option Reflects A Genuine Value'),
                'no_option_is_objectively_wrong' => $schema->boolean()->required()->title('No Option Is Objectively Wrong'),
                'talkability_test_passes' => $schema->boolean()->required()->title('Talkability Test Passes'),
            ])->required()->withoutAdditionalProperties()->title('Moral Weight Confirmation')->description('MORAL_WEIGHT only; other categories may report all true.'),
            'session_end_confirmation' => $schema->object([
                'does_not_resolve_within_session' => $schema->boolean()->required()->title('Does Not Resolve Within Session'),
                'user_closes_session_mid_decision' => $schema->boolean()->required()->title('User Closes Session Mid Decision'),
            ])->required()->withoutAdditionalProperties()->title('Session End Confirmation')->description('SESSION_END_HOOK only; other categories may report both false.'),
            'cross_session_payoff_reference' => $schema->string()->required()->title('Cross-Session Payoff Reference')->description('Reference Phase 2 cross-session payoff plan or N/A.'),
        ])->required()->withoutAdditionalProperties();

        $emotionalOptionSchema = $schema->object([
            'label' => $schema->string()->required()->title('Label'),
            'text' => $schema->string()->required()->title('Option Text'),
            'alignment' => $schema->string()->required()->title('Alignment'),
            'outcome' => $schema->string()->required()->title('Outcome')->description('80-100 words in the author\'s voice.'),
            'tonal_effect' => $schema->string()->required()->title('Tonal Effect')->description('How narration voice shifts after this option.'),
            'persistent_state_changes' => (clone $persistentStateChangesSchema)->title('Persistent State Changes')->description('Lighter than branching — NPC dispositions / emotional ledger / alignment shift.'),
            'world_noticed_signal' => $schema->string()->required()->title('World Noticed Signal')->description('Signal text or "NO SIGNAL — state change is minor".'),
        ])->required()->withoutAdditionalProperties();

        $emotionalChoiceSchema = $schema->object([
            'choice_id' => $schema->string()->required()->title('Choice ID')->description('S{session}_E{n}.'),
            'beat' => $schema->string()->required()->title('Beat')->description('ESCALATION / BREATH / TWIST / RESOLUTION.'),
            ...($this->boilerEnabled ? [
                'moment_key' => $schema->string()->required()->title('Boiler Moment Key'),
            ] : []),
            'source_moment' => $schema->string()->required()->title('Source Moment'),
            'emotional_register' => $schema->string()->required()->title('Emotional Register'),
            'alignment_order' => $schema->object([
                'a' => $schema->string()->required()->title('A'),
                'b' => $schema->string()->required()->title('B'),
                'c' => $schema->string()->required()->title('C'),
            ])->required()->withoutAdditionalProperties()->title('Alignment Order'),
            'narrative_lead_in' => $schema->string()->required()->title('Narrative Lead-In')->description('1-2 sentences in the author\'s voice.'),
            'choice_question' => $schema->string()->required()->title('Choice Question'),
            'options' => $schema->array()->required()->title('Options')->description('Exactly 3 entries.')->items($emotionalOptionSchema),
            'all_paths_arrive_at' => $schema->string()->required()->title('All Paths Arrive At'),
        ])->required()->withoutAdditionalProperties();

        $postureOptionSchema = $schema->object([
            'label' => $schema->string()->required()->title('Label'),
            'text' => $schema->string()->required()->title('Option Text')->description('Natural-language response, 5-15 words. NOT a menu item.'),
            'stance_revealed' => $schema->string()->required()->title('Stance Revealed')->description('One phrase — what this option exposes about the player\'s current stance.'),
            'narration_adjustment' => $schema->string()->required()->title('Narration Adjustment')->description('The actual 2-3 adjusted sentences in the author\'s voice.'),
            'state_update' => $schema->string()->required()->title('State Update')->description('player_style change, e.g. "player_style.emotional_openness +1".'),
        ])->required()->withoutAdditionalProperties();

        $postureShiftSchema = $schema->object([
            'shift_id' => $schema->string()->required()->title('Shift ID')->description('S{session}_P{n}.'),
            'beat' => $schema->string()->required()->title('Beat'),
            ...($this->boilerEnabled ? [
                'moment_key' => $schema->string()->required()->title('Boiler Moment Key'),
            ] : []),
            'placement' => $schema->string()->required()->title('Placement')->description('Source moment + emotional pressure context.'),
            'narrator_line' => $schema->string()->required()->title('Narrator Line')->description('Single line in the author\'s voice that invites the response. Never menu-shaped.'),
            'options' => $schema->array()->required()->title('Options')->description('2-3 entries.')->items($postureOptionSchema),
        ])->required()->withoutAdditionalProperties();

        $sceneRuleSchema = $schema->object([
            'scene_number' => $schema->number()->required()->title('Scene Number'),
            'beat' => $schema->string()->required()->title('Beat'),
            'available_objects' => $schema->array()->required()->title('Available Objects')->items($schema->string()->required()),
            'present_npcs' => $schema->array()->required()->title('Present NPCs')->items(
                $schema->object([
                    'npc' => $schema->string()->required()->title('NPC'),
                    'current_disposition' => $schema->string()->required()->title('Current Disposition'),
                ])->required()->withoutAdditionalProperties()
            ),
            'character_knowledge_limits' => $schema->string()->required()->title('Character Knowledge Limits'),
            'emotional_context' => $schema->string()->required()->title('Emotional Context'),
            'canon_boundaries_active' => $schema->array()->required()->title('Canon Boundaries Active')->items($schema->string()->required()),
            'freeform_risk_areas' => $schema->array()->required()->title('Freeform Risk Areas')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        return [
            'branching_choices' => $schema->array()->required()->title('Branching Choices')->description('EXACTLY 4 entries — IDENTITY (Setup), METHODOLOGY (Escalation), MORAL_WEIGHT (Twist), SESSION_END_HOOK (Resolution).')->items($branchingChoiceSchema),

            'emotional_choices' => $schema->array()->required()->title('Emotional Choices')->description('4-6 entries.')->items($emotionalChoiceSchema),

            'posture_shifts' => $schema->array()->required()->title('Posture Shifts')->description('6-10 entries.')->items($postureShiftSchema),

            'scene_rules_layer_4' => $schema->array()->required()->title('Scene Rules — StoryGuard Layer 4')->description('One entry per scene of the episode.')->items($sceneRuleSchema),

            'interaction_count_verification' => $schema->object([
                'branching_choices' => $schema->number()->required()->title('Branching Choices')->description('Must be 4.'),
                'emotional_choices' => $schema->number()->required()->title('Emotional Choices')->description('Must be 4-6.'),
                'posture_shifts' => $schema->number()->required()->title('Posture Shifts')->description('Must be 6-10.'),
                'storyguard_manifests_written' => $schema->number()->required()->title('StoryGuard Manifests Written')->description('Must be 4.'),
                'world_noticed_signals_written' => $schema->number()->required()->title('World Noticed Signals Written'),
                'scene_rules_populated' => $schema->number()->required()->title('Scene Rules Populated'),
                'defining_lines_written' => $schema->number()->required()->title('Defining Lines Written')->description('Must be 12 (4 × 3).'),
            ])->required()->withoutAdditionalProperties()->title('Interaction Count Verification'),
        ];
    }
}
