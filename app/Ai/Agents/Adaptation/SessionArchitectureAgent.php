<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use App\Ai\Agents\Adaptation\Concerns\SetsOpenAiReasoningEffort;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

/**
 * Pipeline Upgrade V2 — Deliverable 3: Phase 4 Session Architecture.
 *
 * Schema reflects the canonical Deliverable's six tasks:
 *   1. five-beat identification
 *   2. interaction count verification (exact quotas: 4 branching / 4-6 emotional / 6-10 posture)
 *   3. content budget declaration
 *   4. session beat map (with new choice_slot taxonomy)
 *   5. posture shift placement strategy
 *   6. next-session awareness
 */
#[Model('gpt-6.1-sol')]
#[Timeout(600)]
final class SessionArchitectureAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, SetsOpenAiReasoningEffort;

    private const string REASONING_EFFORT = 'low';

    public function __construct(private bool $boilerEnabled = false) {}

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.session-architecture.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $beatSchema = fn (string $name) => $schema->object([
            'source_moment' => $schema->string()->required()->title('Source Moment'),
            'why_it_qualifies' => $schema->string()->required()->title('Why It Qualifies'),
            'editorial_intervention' => $schema->string()->required()->title('Editorial Intervention')->description('Minimal / Moderate / Heavy / INVENTION REQUIRED'),
        ])->required()->withoutAdditionalProperties()->title($name);

        $branchingChoiceSlotSchema = $schema->object([
            'choice_number' => $schema->number()->required()->title('Choice Number')->description('1-4. Choice 1 = Identity (Setup). Choice 4 = Session-End Hook (Resolution).'),
            'target_beat' => $schema->string()->required()->title('Target Beat')->description('SETUP / ESCALATION / BREATH / TWIST / RESOLUTION.'),
            'dramatic_function' => $schema->string()->required()->title('Dramatic Function')->description('Identity / Methodology / Moral Weight / Future Commitment / etc. — must reference a Phase 2 branch dimension.'),
            'approximate_minute' => $schema->string()->required()->title('Approximate Minute'),
            'dramatic_question' => $schema->string()->required()->title('Dramatic Question'),
        ])->required()->withoutAdditionalProperties();

        $emotionalChoiceSlotSchema = $schema->object([
            'beat' => $schema->string()->required()->title('Beat')->description('ESCALATION or BREATH.'),
            'emotional_register' => $schema->string()->required()->title('Emotional Register')->description('e.g. curiosity, defiance, tenderness, restraint.'),
            'source_attachment' => $schema->string()->required()->title('Source Attachment'),
        ])->required()->withoutAdditionalProperties();

        $postureShiftSlotSchema = $schema->object([
            'placement' => $schema->string()->required()->title('Placement')->description('e.g. "early Setup", "mid Escalation", "during Breath beat".'),
            'type' => $schema->string()->required()->title('Type')->description('observation / deflection / softening / intensifying / joking / withdrawing / leaning in / etc.'),
            'player_attitude_exposed' => $schema->string()->required()->title('Player Attitude Exposed'),
        ])->required()->withoutAdditionalProperties();

        return [
            'beat_identification' => $schema
                ->object([
                    'setup' => $beatSchema('Setup Beat'),
                    'escalation' => $beatSchema('Escalation Beat'),
                    'breath' => $beatSchema('Breath Beat'),
                    'twist' => $beatSchema('Twist Beat'),
                    'resolution' => $beatSchema('Resolution Beat'),
                ])->required()->withoutAdditionalProperties()->title('Beat Identification'),

            'interaction_count_verification' => $schema->object([
                'branching_choices' => $schema->array()->required()->title('Branching Choices')->description('EXACTLY 4 entries.')->items($branchingChoiceSlotSchema),
                'emotional_choices' => $schema->array()->required()->title('Emotional Choices')->description('4-6 entries.')->items($emotionalChoiceSlotSchema),
                'posture_shifts' => $schema->array()->required()->title('Posture Shifts')->description('6-10 entries.')->items($postureShiftSlotSchema),
                'total_interaction_density' => $schema->number()->required()->title('Total Interaction Density')->description('Sum of all three categories. Should be 14-20.'),
            ])->required()->withoutAdditionalProperties()->title('Interaction Count Verification'),

            'content_budget' => $schema->object([
                'narration_token_budget' => $schema->string()->required()->title('Narration Token Budget')->description('Target 3,000-5,000 words of narrator output across all turns.'),
                'distinct_scene_count' => $schema->number()->required()->title('Distinct Scene Count'),
                'compression_targets' => $schema->array()->required()->title('Compression Targets')->description('Source sequences that will become single beats.')->items($schema->string()->required()),
                'expansion_targets' => $schema->array()->required()->title('Expansion Targets')->description('Moments that deserve full breathing room.')->items($schema->string()->required()),
                'dialogue_treatment' => $schema->object([
                    'must_survive_verbatim' => $schema->array()->required()->title('Must Survive Verbatim')->items($schema->string()->required()),
                    'can_be_reshaped' => $schema->array()->required()->title('Can Be Reshaped')->items($schema->string()->required()),
                    'can_be_cut' => $schema->array()->required()->title('Can Be Cut')->items($schema->string()->required()),
                ])->required()->withoutAdditionalProperties()->title('Dialogue Treatment'),
            ])->required()->withoutAdditionalProperties()->title('Content Budget'),

            'beat_map' => $schema
                ->array()
                ->required()
                ->title('Beat Map')
                ->description('Complete session beat map. Time ranges use MM:SS-MM:SS. Must include exactly 4 BRANCHING slots and exactly 1 BREATH beat wholly inside minutes 10-13.')
                ->items(
                    $schema->object([
                        'time_range' => $schema->string()->required()->title('Time Range')
                            ->description('Exact MM:SS-MM:SS format, for example 10:00-12:30.'),
                        'moment' => $schema->string()->required()->title('Moment'),
                        'beat_type' => $schema->string()->required()->title('Beat Type'),
                        'choice_slot' => $schema->string()->required()->title('Choice Slot')->description('BRANCHING / EMOTIONAL / POSTURE / none.'),
                        'dramatic_function' => $schema->string()->required()->title('Dramatic Function'),
                        ...($this->boilerEnabled ? [
                            'moment_key' => $schema->string()->required()->title('Boiler Moment Key')
                                ->description('Must reference an existing draft moment.'),
                        ] : []),
                    ])->required()->withoutAdditionalProperties()
                ),

            ...($this->boilerEnabled ? ['boiler_budget_alignment' => $schema->object([
                'aligned' => $schema->boolean()->required()->title('Aligned'),
                'conflict' => $schema->string()->required()->title('Conflict')
                    ->description('Empty when aligned; otherwise state the unresolved conflict without changing the Draft count.'),
            ])->required()->withoutAdditionalProperties()->title('Boiler Budget Alignment')] : []),

            'posture_shift_placement_strategy' => $schema->object([
                'pressure_points' => $schema->array()->required()->title('Pressure Points')->description('3-4 emotional pressure points where posture shifts cluster.')->items(
                    $schema->object([
                        'source_moment' => $schema->string()->required()->title('Source Moment'),
                        'posture_shifts_clustered' => $schema->number()->required()->title('Posture Shifts Clustered'),
                        'emotional_pressure' => $schema->string()->required()->title('Emotional Pressure'),
                        'micro_agency_without_diverting_spine' => $schema->string()->required()->title('Micro Agency Without Diverting Spine'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('Posture Shift Placement Strategy'),

            'next_session_awareness' => $schema
                ->object([
                    'seed_for_next_session' => $schema->string()->required()->title('Seed For Next Session'),
                    'connects_to_next_dramatic_question' => $schema->string()->required()->title('Connects To Next Dramatic Question')->description('YES or NEEDS EDITORIAL BRIDGE.'),
                    'bridge_description' => $schema->string()->required()->title('Bridge Description')->description('Empty string if connects naturally.'),
                ])->required()->withoutAdditionalProperties()->title('Next Session Awareness'),
        ];
    }
}
