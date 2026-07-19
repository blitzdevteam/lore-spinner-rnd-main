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

#[Model('gpt-5.4')]
#[Temperature(0.5)]
#[Timeout(540)]
class StorySessionMapAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private bool $boilerEnabled = false) {}

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.story-session-map.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'confirmed_session_count' => $schema
                ->number()
                ->required()
                ->title('Confirmed Session Count'),
            'session_allocation' => $schema
                ->array()
                ->required()
                ->title('Session Allocation')
                ->items(
                    $schema->object([
                        'session_number' => $schema->number()->required()->title('Session Number'),
                        'chapters_covered' => $schema->string()->required()->title('Chapters Covered'),
                        'event_range' => $schema->string()->required()->title('Event Range')->description('Event position range, e.g. "1-8".'),
                        'primary_dramatic_question' => $schema->string()->required()->title('Primary Dramatic Question'),
                        'emotional_register' => $schema->string()->required()->title('Emotional Register'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'arc_progression' => $schema
                ->array()
                ->required()
                ->title('Arc Progression')
                ->items(
                    $schema->object([
                        'session_number' => $schema->number()->required()->title('Session Number'),
                        'opens_with' => $schema->string()->required()->title('Opens With')->description('Seed from previous session close. N/A for session 1.'),
                        'primary_dramatic_question' => $schema->string()->required()->title('Primary Dramatic Question'),
                        'emotional_register_shift' => $schema->string()->required()->title('Emotional Register Shift'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'branch_opportunities' => $schema
                ->array()
                ->required()
                ->title('Branch Opportunities')
                ->description('2-3 strongest branching choice opportunities per session.')
                ->items(
                    $schema->object([
                        'session_number' => $schema->number()->required()->title('Session Number'),
                        'event_position' => $schema->number()->required()->title('Event Position'),
                        'event_title' => $schema->string()->required()->title('Event Title'),
                        'choice_dimension' => $schema->string()->required()->title('Choice Dimension'),
                        'downstream_payoff_session' => $schema->number()->required()->title('Downstream Payoff Session'),
                        'payoff_event' => $schema->string()->required()->title('Payoff Event'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'cross_session_payoff_plan' => $schema
                ->array()
                ->required()
                ->title('Cross-Session Payoff Plan')
                ->items(
                    $schema->object([
                        'choice_reference' => $schema->string()->required()->title('Choice Reference')->description('Session + event reference.'),
                        'what_it_tracks' => $schema->string()->required()->title('What It Tracks'),
                        'echo_session' => $schema->number()->required()->title('Echo Session'),
                        'payoff_session' => $schema->number()->required()->title('Payoff Session'),
                        'payoff_description' => $schema->string()->required()->title('Payoff Description'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'branch_dimensions' => $schema
                ->array()
                ->required()
                ->title('Branch Dimensions')
                ->description('3-6 canonical narrative axes for the story.')
                ->items(
                    $schema->object([
                        'dimension_name' => $schema->string()->required()->title('Dimension Name')->description('Snake_case tension axis, e.g. trust_vs_caution.'),
                        'description' => $schema->string()->required()->title('Description')->description('One-sentence human-readable definition.'),
                    ])->required()->withoutAdditionalProperties()
                ),

            ...($this->boilerEnabled ? ['boiling_profile' => $schema->object([
                'compaction_level' => $schema->string()->required()->title('Compaction Level')
                    ->description('light / balanced / strong. Editorial judgment grounded in this story, never a formula.'),
                'target_total_playtime_minutes' => $schema->integer()->required()->title('Target Total Playtime Minutes'),
                'diagnosis' => $schema->string()->required()->title('Diagnosis'),
                'per_session_guidance' => $schema->array()->required()->title('Per-Session Guidance')->items(
                    $schema->object([
                        'session_number' => $schema->integer()->required()->title('Session Number'),
                        'target_playtime_minutes' => $schema->integer()->required()->title('Target Playtime Minutes'),
                        'recommended_moment_count_min' => $schema->integer()->required()->title('Recommended Moment Count Minimum'),
                        'recommended_moment_count_max' => $schema->integer()->required()->title('Recommended Moment Count Maximum'),
                        'must_compress_event_ids' => $schema->array()->required()->title('Must Compress Event IDs')
                            ->items($schema->integer()->required()),
                        'must_breathe_event_ids' => $schema->array()->required()->title('Must Breathe Event IDs')
                            ->items($schema->integer()->required()),
                        'rationale' => $schema->string()->required()->title('Rationale'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('Boiling Profile')] : []),

            // --- Pipeline Upgrade V2: Deliverable 2 Tasks 6-9 ---

            'persistent_state_schema' => $schema->object([
                'objects' => $schema->array()->required()->title('Objects / Artifacts / Inventory Items')->items(
                    $schema->object([
                        'name' => $schema->string()->required()->title('Name'),
                        'type' => $schema->string()->required()->title('Type'),
                        'initial_state' => $schema->string()->required()->title('Initial State'),
                        'possible_state_changes' => $schema->array()->required()->title('Possible State Changes')->items($schema->string()->required()),
                        'tracked_attributes' => $schema->array()->required()->title('Tracked Attributes')->description('Qualitative descriptors only — no numeric scores.')->items($schema->string()->required()),
                        'persistence_requirement' => $schema->string()->required()->title('Persistence Requirement')->description('ALL_SESSIONS / SINGLE_SESSION / SINGLE_SCENE.'),
                        'reactivity_hooks' => $schema->array()->required()->title('Reactivity Hooks')->items($schema->string()->required()),
                    ])->required()->withoutAdditionalProperties()
                ),
                'npcs' => $schema->array()->required()->title('NPC Relationship States')->items(
                    $schema->object([
                        'name' => $schema->string()->required()->title('Name'),
                        'initial_disposition' => $schema->string()->required()->title('Initial Disposition'),
                        'trust_level' => $schema->object([
                            'level' => $schema->string()->required()->title('Level')->description('LOW / MEDIUM / HIGH.'),
                            'what_raises_it' => $schema->string()->required()->title('What Raises It'),
                            'what_lowers_it' => $schema->string()->required()->title('What Lowers It'),
                        ])->required()->withoutAdditionalProperties()->title('Trust Level'),
                        'specific_knowledge_of_player_actions' => $schema->array()->required()->title('Specific Knowledge Of Player Actions')->items($schema->string()->required()),
                        'personal_stakes' => $schema->string()->required()->title('Personal Stakes'),
                        'behavioral_triggers' => $schema->array()->required()->title('Behavioral Triggers')->items(
                            $schema->object([
                                'trigger' => $schema->string()->required()->title('Trigger'),
                                'shift' => $schema->string()->required()->title('Shift')->description('How this NPC\'s behaviour changes when the trigger fires.'),
                            ])->required()->withoutAdditionalProperties()
                        ),
                        'persistence_scope' => $schema->string()->required()->title('Persistence Scope')->description('ACROSS_SESSIONS / WITHIN_SESSION / SESSION_RESET.'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'world_flags' => $schema->array()->required()->title('World State Flags')->items(
                    $schema->object([
                        'name' => $schema->string()->required()->title('Name'),
                        'initial_value' => $schema->string()->required()->title('Initial Value'),
                        'possible_values' => $schema->array()->required()->title('Possible Values')->items($schema->string()->required()),
                        'triggers_for_change' => $schema->array()->required()->title('Triggers For Change')->items($schema->string()->required()),
                        'narrative_consequences' => $schema->string()->required()->title('Narrative Consequences'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'player_historical_archive_categories' => $schema->array()->required()->title('Player Historical Archive Categories')->items(
                    $schema->object([
                        'category' => $schema->string()->required()->title('Category')->description('e.g. defining_moral_choices, npcs_helped_or_harmed, promises_made, secrets_discovered, crimes, sacrifices, key_successes_failures.'),
                        'definition' => $schema->string()->required()->title('Definition'),
                        'example_entries' => $schema->array()->required()->title('Example Entries')->items($schema->string()->required()),
                        'referenceable_scope' => $schema->string()->required()->title('Referenceable Scope'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('Persistent State Schema'),

            'world_reactivity_rules' => $schema->object([
                'reactivity_categories' => $schema->array()->required()->title('Reactivity Categories')->items(
                    $schema->object([
                        'category' => $schema->string()->required()->title('Category')->description('ENVIRONMENTAL / NPC / FACTION_SOCIETAL / SUPERNATURAL_SYSTEMIC / SYMBOLIC_THEMATIC.'),
                        'how_it_triggers' => $schema->string()->required()->title('How It Triggers'),
                        'when_it_triggers' => $schema->string()->required()->title('When It Triggers'),
                        'how_it_manifests' => $schema->string()->required()->title('How It Manifests'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'timing_rules' => $schema->array()->required()->title('Timing Rules')->items(
                    $schema->object([
                        'category' => $schema->string()->required()->title('Category'),
                        'timing' => $schema->string()->required()->title('Timing')->description('IMMEDIATE / DELAYED_LATER_SCENE / DELAYED_NEXT_SESSION / DELAYED_CLIMAX.'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'escalation_rules' => $schema->array()->required()->title('Escalation Rules')->items(
                    $schema->object([
                        'category' => $schema->string()->required()->title('Category'),
                        'compounds' => $schema->boolean()->required()->title('Compounds'),
                        'compounding_description' => $schema->string()->required()->title('Compounding Description'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'visibility_rules' => $schema->array()->required()->title('Visibility Rules')->items(
                    $schema->object([
                        'category' => $schema->string()->required()->title('Category'),
                        'visibility' => $schema->string()->required()->title('Visibility')->description('EXPLICIT / IMPLICIT / MIXED.'),
                        'when_explicit' => $schema->string()->required()->title('When Explicit'),
                        'when_implicit' => $schema->string()->required()->title('When Implicit'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('World Reactivity Rules'),

            'story_guard_canon' => $schema->object([
                'layer_1_physical_rule_canon' => $schema->array()->required()->title('Layer 1 — Physical / Rule Canon')->items(
                    $schema->object([
                        'rule' => $schema->string()->required()->title('Rule'),
                        'enforcement' => $schema->string()->required()->title('Enforcement')->description('What the runtime should refuse or bend the world to honour.'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'layer_2_character_canon' => $schema->array()->required()->title('Layer 2 — Character Canon')->items(
                    $schema->object([
                        'character' => $schema->string()->required()->title('Character'),
                        'truths' => $schema->array()->required()->title('Truths')->description('2-3 character truths.')->items($schema->string()->required()),
                    ])->required()->withoutAdditionalProperties()
                ),
                'layer_3_narrative_canon' => $schema->array()->required()->title('Layer 3 — Narrative Canon')->description('3-7 required story beats.')->items(
                    $schema->object([
                        'beat' => $schema->string()->required()->title('Beat'),
                        'why_required' => $schema->string()->required()->title('Why Required'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'layer_4_voice_tonal_canon' => $schema->object([
                    'tone_restrictions' => $schema->array()->required()->title('Tone Restrictions')->items($schema->string()->required()),
                    'language_restrictions' => $schema->array()->required()->title('Language Restrictions')->items($schema->string()->required()),
                    'thematic_restrictions' => $schema->array()->required()->title('Thematic Restrictions')->items($schema->string()->required()),
                ])->required()->withoutAdditionalProperties()->title('Layer 4 — Voice / Tonal Canon'),
            ])->required()->withoutAdditionalProperties()->title('StoryGuard Canon'),

            'alignment_labels' => $schema->array()->required()->title('Story-Native Alignment Labels')->description('3-5 IP-specific labels. NEVER the literal strings chaotic / lawful / neutral.')->items(
                $schema->object([
                    'label' => $schema->string()->required()->title('Label')->description('Story-native alignment tendency name.'),
                    'maps_to_internal' => $schema->string()->required()->title('Maps To Internal')->description('chaotic / lawful / neutral — the hidden internal counter this label corresponds to.'),
                    'behavioral_markers' => $schema->array()->required()->title('Behavioral Markers')->items($schema->string()->required()),
                    'narrative_consequences' => $schema->string()->required()->title('Narrative Consequences'),
                    'voice_signature' => $schema->string()->required()->title('Voice Signature')->description('How the narrator subtly shifts when describing a player whose actions tend toward this alignment.'),
                ])->required()->withoutAdditionalProperties()
            ),
        ];
    }
}
