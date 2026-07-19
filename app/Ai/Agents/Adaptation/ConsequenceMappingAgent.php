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
 * Pipeline Upgrade V2 — Deliverable 5: Phase 6 Consequence Mapping.
 *
 * Output shape feeds the runtime narrator template
 * (resources/views/ai/agents/chaos/runtime-narrator-template.blade.php, Section 15):
 *
 *   - branching_consequences[]:
 *       choice_id, tracked_dimension, is_session_end_hook
 *       paths[]:
 *         label                       (A / B / C)
 *         alignment                   (CHAOTIC / LAWFUL / NEUTRAL)
 *         immediate_effect            (or "N/A — session ends" for the hook)
 *         current_session_echo
 *         next_session_payoff
 *         next_session_opening        (only on the SESSION_END_HOOK choice)
 *         later_session_legacy
 *         defining_line_captured      (mirrors Phase 5 Task 8 line for this path)
 *         world_state_delta
 *         reactivity_triggers
 *         cross_episode_propagation
 *
 *   - emotional_consequences[]
 *   - reactivity_trigger_specs[]
 *   - cross_episode_propagation_rules
 *   - freeform_guidelines[]   (flat per choice+path so the runtime template
 *                              can iterate without re-shaping)
 *   - validation_results
 */
#[Model('gpt-5.4')]
#[Temperature(0.6)]
#[Timeout(300)]
class ConsequenceMappingAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.consequence-mapping.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $worldStateDeltaSchema = $schema->object([
            'inventory' => $schema->string()->required()->title('Inventory')->description('Specific changes or NONE.'),
            'npc_shifts' => $schema->array()->required()->title('NPC Shifts')->items(
                $schema->object([
                    'npc' => $schema->string()->required()->title('NPC'),
                    'direction' => $schema->string()->required()->title('Direction'),
                    'magnitude' => $schema->string()->required()->title('Magnitude')->description('Qualitative — slight / moderate / strong.'),
                ])->required()->withoutAdditionalProperties()
            ),
            'environmental_flags' => $schema->array()->required()->title('Environmental Flags')->items($schema->string()->required()),
            'alignment_shift' => $schema->string()->required()->title('Alignment Shift')->description('CHAOTIC / LAWFUL / NEUTRAL +1.'),
            'emotional_ledger_entry' => $schema->string()->required()->title('Emotional Ledger Entry')->description('Phase 2 Task 6D category + delta, e.g. "ACTS OF MERCY +1".'),
        ])->required()->withoutAdditionalProperties();

        $crossEpisodePropagationSchema = $schema->object([
            'resets' => $schema->array()->required()->title('Resets')->items($schema->string()->required()),
            'persists' => $schema->array()->required()->title('Persists')->items($schema->string()->required()),
            'escalates' => $schema->array()->required()->title('Escalates')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $pathSchema = $schema->object([
            'label' => $schema->string()->required()->title('Label')->description('A / B / C.'),
            'alignment' => $schema->string()->required()->title('Alignment'),
            'immediate_effect' => $schema->string()->required()->title('Immediate Effect')->description('Specific named moment within 2 minutes of the choice. For SESSION_END_HOOK use "N/A — session ends on this choice".'),
            'current_session_echo' => $schema->string()->required()->title('Current Session Echo'),
            'next_session_payoff' => $schema->string()->required()->title('Next Session Payoff'),
            'next_session_opening' => $schema->string()->required()->title('Next Session Opening')->description('SESSION_END_HOOK only — tone, first image, first character, immediate stakes. Empty string otherwise.'),
            'later_session_legacy' => $schema->string()->required()->title('Later Session Legacy')->description('Specific or N/A.'),
            'defining_line_captured' => $schema->string()->required()->title('Defining Line Captured')->description('Mirror the Phase 5 Task 8 defining_line for this path.'),
            'consequence_visibility' => $schema->object([
                'what_changes' => $schema->string()->required()->title('What Changes')->description('Concrete visible change the player can perceive.'),
                'when_visible_target_responses' => $schema->number()->required()->title('When Visible Target Responses')->description('Target: 2 responses.'),
                'when_visible_max_responses' => $schema->number()->required()->title('When Visible Max Responses')->description('Hard maximum: 3 responses.'),
                'how_player_sees_it' => $schema->string()->required()->title('How Player Sees It')->description('Dialogue shift, NPC behavior, environmental detail, etc.'),
            ])->required()->withoutAdditionalProperties()->title('Consequence Visibility'),
            'world_state_delta' => (clone $worldStateDeltaSchema)->title('World State Delta'),
            'reactivity_triggers' => $schema->array()->required()->title('Reactivity Triggers')->items($schema->string()->required()),
            'cross_episode_propagation' => (clone $crossEpisodePropagationSchema)->title('Cross-Episode Propagation'),
        ])->required()->withoutAdditionalProperties();

        $branchingConsequenceSchema = $schema->object([
            'choice_id' => $schema->string()->required()->title('Choice ID'),
            'moment_key' => $schema->string()->title('Immediate Boiler Moment Key'),
            'echo_moment_key' => $schema->string()->title('Optional Same-Session Echo Moment Key'),
            'moment_narrator_hook' => $schema->string()->title('Moment-Aware Narrator Hook'),
            'tracked_dimension' => $schema->string()->required()->title('Tracked Dimension')->description('Must match the Phase 5 branching choice dimension exactly.'),
            'is_session_end_hook' => $schema->boolean()->required()->title('Is Session End Hook')->description('True only for Branching Choice #4.'),
            'paths' => $schema->array()->required()->title('Paths')->description('Exactly 3 entries: labels A, B, C.')->items($pathSchema),
        ])->required()->withoutAdditionalProperties();

        $emotionalPathSchema = $schema->object([
            'label' => $schema->string()->required()->title('Label'),
            'alignment' => $schema->string()->required()->title('Alignment'),
            'tonal_effect' => $schema->string()->required()->title('Tonal Effect')->description('How the next ~200 words of narration shift — specific.'),
            'state_changes' => $schema->object([
                'npc_shifts' => $schema->array()->required()->title('NPC Shifts')->items($schema->string()->required()),
                'emotional_ledger_entry' => $schema->string()->required()->title('Emotional Ledger Entry'),
                'alignment_shift' => $schema->string()->required()->title('Alignment Shift'),
            ])->required()->withoutAdditionalProperties()->title('State Changes'),
        ])->required()->withoutAdditionalProperties();

        $emotionalConsequenceSchema = $schema->object([
            'choice_id' => $schema->string()->required()->title('Choice ID'),
            'moment_key' => $schema->string()->title('Immediate Boiler Moment Key'),
            'echo_moment_key' => $schema->string()->title('Optional Same-Session Echo Moment Key'),
            'moment_narrator_hook' => $schema->string()->title('Moment-Aware Narrator Hook'),
            'paths' => $schema->array()->required()->title('Paths')->items($emotionalPathSchema),
            'convergence_point' => $schema->string()->required()->title('Convergence Point'),
        ])->required()->withoutAdditionalProperties();

        $reactivityTriggerSchema = $schema->object([
            'trigger_id' => $schema->string()->required()->title('Trigger ID'),
            'condition' => $schema->string()->required()->title('Condition'),
            'default_behavior' => $schema->string()->required()->title('Default Behavior'),
            'triggered_behavior' => $schema->string()->required()->title('Triggered Behavior'),
            'narrative_execution' => $schema->string()->required()->title('Narrative Execution'),
            'affected_elements' => $schema->object([
                'npc_reactions' => $schema->array()->required()->title('NPC Reactions')->items($schema->string()->required()),
                'environmental_details' => $schema->array()->required()->title('Environmental Details')->items($schema->string()->required()),
                'dialogue_variations' => $schema->array()->required()->title('Dialogue Variations')->items($schema->string()->required()),
                'available_options' => $schema->string()->required()->title('Available Options')->description('Description or NONE.'),
            ])->required()->withoutAdditionalProperties()->title('Affected Elements'),
        ])->required()->withoutAdditionalProperties();

        $freeformGuidelineSchema = $schema->object([
            'choice_id' => $schema->string()->required()->title('Choice ID'),
            'path_label' => $schema->string()->required()->title('Path Label')->description('A / B / C.'),
            'narrator_behavior' => $schema->string()->required()->title('Narrator Behavior')->description('One sentence — how the narrator should surface past choices of this path going forward. Runtime template iterates this directly.'),
            'spirit' => $schema->string()->required()->title('Spirit')->description('The deeper meaning of this option beneath the surface.'),
            'freeform_alignment_input' => $schema->string()->required()->title('Freeform Alignment Input')->description('Types of freeform input that map to this alignment.'),
            'hard_limits' => $schema->array()->required()->title('Hard Limits')->description('StoryGuard violations at this node.')->items($schema->string()->required()),
            'fold_back_acknowledge' => $schema->string()->required()->title('Fold-Back Acknowledge'),
            'fold_back_redirect' => $schema->string()->required()->title('Fold-Back Redirect'),
            'fold_back_arrive_at' => $schema->string()->required()->title('Fold-Back Arrive At'),
        ])->required()->withoutAdditionalProperties();

        return [
            'branching_consequences' => $schema->array()->required()->title('Branching Consequences')->description('Exactly 4 — one per Phase 5 branching choice.')->items($branchingConsequenceSchema),

            'emotional_consequences' => $schema->array()->required()->title('Emotional Consequences')->description('One per Phase 5 emotional choice (4-6).')->items($emotionalConsequenceSchema),

            'reactivity_trigger_specs' => $schema->array()->required()->title('Reactivity Trigger Specifications')->items($reactivityTriggerSchema),

            'cross_episode_propagation_rules' => $schema->object([
                'resets_between_episodes' => $schema->array()->required()->title('Resets Between Episodes')->items(
                    $schema->object([
                        'element' => $schema->string()->required()->title('Element'),
                        'why' => $schema->string()->required()->title('Why'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'persists_across_full_story' => $schema->array()->required()->title('Persists Across Full Story')->items(
                    $schema->object([
                        'element' => $schema->string()->required()->title('Element'),
                        'why' => $schema->string()->required()->title('Why'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'escalates_with_accumulation' => $schema->array()->required()->title('Escalates With Accumulation')->items(
                    $schema->object([
                        'behavioral_pattern' => $schema->string()->required()->title('Behavioral Pattern'),
                        'thresholds' => $schema->object([
                            'at_2_instances' => $schema->string()->required()->title('At 2 Instances'),
                            'at_4_instances' => $schema->string()->required()->title('At 4 Instances'),
                            'at_6_or_more_instances' => $schema->string()->required()->title('At 6 Or More Instances'),
                        ])->required()->withoutAdditionalProperties()->title('Thresholds'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('Cross-Episode Propagation Rules'),

            'freeform_guidelines' => $schema->array()->required()->title('Freeform Guidelines')->description('Exactly 12 entries — 4 branching choices × 3 paths.')->items($freeformGuidelineSchema),

            'validation_results' => $schema->object([
                'specificity' => $schema->string()->required()->title('Specificity'),
                'asymmetry' => $schema->string()->required()->title('Asymmetry'),
                'payability' => $schema->string()->required()->title('Payability'),
                'state_consistency' => $schema->string()->required()->title('State Consistency'),
                'reactivity_coherence' => $schema->string()->required()->title('Reactivity Coherence'),
            ])->required()->withoutAdditionalProperties()->title('Validation Results'),
        ];
    }
}
