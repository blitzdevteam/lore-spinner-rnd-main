<?php

declare(strict_types=1);

namespace App\Ai\Agents\Chaos;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Pipeline Upgrade V2 — JSON schema for the Chaos narration turn response.
 *
 * Per Daniel's correction (2026-05-24), this schema is an IN-PLACE upgrade.
 * There is no `world_state_v2_delta` sibling key — the single `state_delta`
 * block IS the new literary-memory shape, and the legacy V1 fields
 * (`conditions`/`items`/`relationships` keyed by NPC name) are gone.
 *
 * Each turn returns:
 *   - response                 cinematic narration (HTML).
 *   - choices                  exactly three suggested player actions.
 *   - session_complete         true only when the session's dramatic question
 *                              has resolved and the next-session seed has been
 *                              planted in narration.
 *   - state_delta              sparse persistent world-state changes this turn:
 *                                location / conditions / items
 *                                object_states              (Name: state)
 *                                relationship_updates       (NPC: shift)
 *                                world_flags                (Flag: value)
 *                                knowledge / notes / player_style
 *                                unresolved_promises
 *                                emotional_ledger_entries   (Phase 2 Task 6D)
 *   - alignment_tally_delta    hidden internal {chaotic, lawful, neutral} ints.
 *                              NEVER injected back into the narrator prompt.
 *   - is_climactic_choice      true when the turn resolved a Choice #3 moral
 *                              weight or Choice #4 session-end hook. Triggers
 *                              Tier 3 state loading on the NEXT turn.
 *   - defining_choice_id       Phase 5 choice_id when this turn resolved one.
 *   - defining_path_label      canonical path label (A/B/C) for the chosen option.
 *                              Empty string when no branch resolved this turn.
 *   - defining_choice_line     Phase 5 Task 8 defining line for the chosen path.
 *   - symbolic_memory_update   short natural-language paragraph appended to
 *                              chaos_sessions.symbolic_memory and re-injected
 *                              at [SYMBOLIC_MEMORY_INJECTION_POINT] next turn.
 *   - session_memory_update    one sentence summarising the turn (legacy log).
 *
 * Stories that have not been re-adapted under V2 cannot return a valid
 * state_delta in this shape and are intentionally unplayable until their
 * pipeline is re-run.
 */
final class ChaosNarrationSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function definition(JsonSchema $schema): array
    {
        return [
            'response' => $schema
                ->string()
                ->required()
                ->title('Response')
                ->description('Cinematic narration in the author\'s voice as an HTML string. Use <p> tags for paragraphs. Use <em> for italics (sparingly). Use <strong> only for a single impactful moment. 2-4 paragraphs. Second-person ("you") throughout. Write AS the author, not as a narrator summarising them.'),

            'choices' => $schema
                ->array()
                ->required()
                ->title('Choices')
                ->description('Outside Boiler mode, return exactly three non-empty suggested action strings whenever session_complete is false; return [] after the session completes. In Boiler mode return []; Boiler prompts emit visible_suggestions instead. Never emit both.')
                ->items($schema->string()->required()),

            'visible_suggestions' => $schema
                ->array()
                ->required()
                ->title('Visible Suggestions')
                ->description('Boiler-only contextual suggestions: exactly three on any turn where the player can act, each with a different function (pursuit / provocation / deepening) and a different consequence domain. Freeform input remains available. Mapped canonical identity is optional and must reference the active moment. Return [] outside Boiler mode.')
                ->items(
                    $schema->object([
                        'text' => $schema->string()->required()->title('Suggestion Text')->description('10 to 15 words maximum. One clause, an action the player takes, strong verb early. A button label, never a sentence of narration.'),
                        'canonical_choice_id' => $schema->string()->required()->title('Canonical Choice ID')->description('Empty string for a freeform suggestion.'),
                        'canonical_path_label' => $schema->string()->required()->title('Canonical Path Label')->description('A, B, or C for a mapped suggestion; empty string otherwise.'),
                    ])->required()->withoutAdditionalProperties()
                ),

            'moment_progress' => $schema->object([
                'moment_key' => $schema->string()->required()->title('Active Moment Key'),
                'exit_condition_met' => $schema->boolean()->required()->title('Exit Condition Met'),
                'route_relation' => $schema->string()->required()->title('Route Relation')
                    ->description('How this turn related to the active room: "advanced" (its purpose moved materially forward), "deepened" (stayed and enriched it), "detoured" (you followed the player somewhere the card did not anticipate), "reconverged" (you carried the story back to the spine after a detour). A detour is legitimate — report it honestly rather than mislabelling it. Empty string outside Boiler mode.'),
                'impact_scope' => $schema->string()->required()->title('Impact Scope')
                    ->description('What this turn actually changed: "expressive" (tone and texture only), "local" (real but contained in this scene), "persistent" (the world, a relationship, or the protagonist\'s standing genuinely changed and must stay changed). When "persistent", the change must also appear in state_delta. Empty string outside Boiler mode.'),
                'moment_footnote' => $schema->string()->required()->title('Hidden Moment Footnote')
                    ->description('REQUIRED after every player turn. One probabilistic sentence of at most 30 words, written for the NEXT narrator, naming what would land hardest on this specific player next — the feeling to aim for, the nerve worth pressing, what they are quietly hoping the world noticed. Read the player, not just the action: separate what they did from what they wanted. MUST be true to what the player actually submitted: never attribute to them a choice, restraint, or change of heart that the narration made on their behalf — if the world stopped them, say that the world stopped them and name what they were reaching for. A hunch, never a verdict; never a score, diagnosis, permanent label, or pacing command. Never shown to the player. Empty string ONLY for opening narration or outside Boiler mode. Never invent PHP placeholders — the model must produce this.'),
            ])->required()->withoutAdditionalProperties()->title('Boiler Moment Progress')
                ->description('Boiler-only progress. Outside Boiler mode return moment_key="", exit_condition_met=false, route_relation="", impact_scope="", and moment_footnote="". Runtime soft-holds the active room only when this block is malformed — emit correct progress every turn.'),

            'session_complete' => $schema
                ->boolean()
                ->required()
                ->title('Session Complete')
                ->description('True ONLY when the current session has reached its natural narrative close — when its dramatic question has resolved and the seed for the next session has been planted in the narration. False on every other turn. The runtime, not the narrator, owns the technical transition to the next session.'),

            'state_delta' => $schema->object([
                'location' => $schema
                    ->string()
                    ->required()
                    ->nullable()
                    ->title('Location')
                    ->description('New current sub-location only when it changed this turn. Return null when unchanged.'),

                'conditions' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Conditions')
                    ->description('Complete replacement list only when active physical/mental conditions changed this turn. Return null when unchanged. Return [] only when the last active condition ended.')
                    ->items($schema->string()->required()),

                'items' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Items')
                    ->description('Complete replacement list only when held items changed this turn. Return null when unchanged. Return [] only when the protagonist now holds nothing.')
                    ->items($schema->string()->required()),

                'object_states' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Object States')
                    ->description('Only changed natural-language states for named objects from the Phase 2 Task 6A inventory. Each entry: "Object Name: qualitative state". Return null when no object changed; runtime carries forward all unchanged named objects.')
                    ->items($schema->string()->required()),

                'relationship_updates' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Relationship Updates')
                    ->description('Only changed natural-language relationship states for named NPCs from the Phase 2 Task 6B registry. Each entry: "NPC Name: relationship shift in plain English (no numeric scores)". Return null when nothing shifted; runtime carries forward unchanged NPCs.')
                    ->items($schema->string()->required()),

                'world_flags' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('World Flags')
                    ->description('Only changed world-level flags from Phase 2 Task 6C. Each entry: "Flag Name: new value". Return null when no flag changed; runtime carries forward unchanged flags.')
                    ->items($schema->string()->required()),

                'knowledge' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Knowledge')
                    ->description('Only new durable facts learned from the player action this turn. Return null when nothing new was learned. Runtime appends and deduplicates.')
                    ->items($schema->string()->required()),

                'notes' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Notes')
                    ->description('Only new emergent facts from this turn that should persist (promises made, bargains struck, things released). Return null when there are none. Runtime appends and deduplicates.')
                    ->items($schema->string()->required()),

                'player_style' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Player Style')
                    ->description('Only newly observed behavioural patterns from this turn. Return null when nothing new was observed. Runtime appends and deduplicates.')
                    ->items($schema->string()->required()),

                'unresolved_promises' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Unresolved Promises')
                    ->description('Complete replacement list only when unresolved promises changed this turn. Return null when unchanged. Return [] only when all promises are now resolved.')
                    ->items($schema->string()->required()),

                'emotional_ledger_entries' => $schema
                    ->array()
                    ->required()
                    ->nullable()
                    ->title('Emotional Ledger Entries')
                    ->description('Only new entries from this turn to append to the player historical archive. Return null when there are none.')
                    ->items(
                        $schema->object([
                            'category' => $schema->string()->required()->title('Category')->description('Must match a Phase 2 Task 6D archive category (e.g. defining_moral_choices, npcs_helped_or_harmed, promises_made, secrets_discovered).'),
                            'entry' => $schema->string()->required()->title('Entry')->description('Natural-language one-sentence entry.'),
                        ])->required()->withoutAdditionalProperties()
                    ),
            ])
                ->required()
                ->withoutAdditionalProperties()
                ->title('Sparse State Delta (Literary Memory — V2 in place)')
                ->description('Sparse changes from this turn only. Every property is present for native schema stability, but null means unchanged. Runtime safely preserves prior state for null or malformed values. Never repeat unchanged state.'),

            'alignment_tally_delta' => $schema->object([
                'chaotic' => $schema->number()->required()->title('Chaotic')->description('Hidden internal counter increment. 0 or positive integer. NEVER appears in the response field.'),
                'lawful' => $schema->number()->required()->title('Lawful')->description('Hidden internal counter increment.'),
                'neutral' => $schema->number()->required()->title('Neutral')->description('Hidden internal counter increment.'),
            ])->required()->withoutAdditionalProperties()->title('Alignment Tally Delta')->description('Hidden alignment scaffold — never narrated, never surfaced. Used at runtime to derive the story-native alignment label that subtly tunes voice.'),

            'is_climactic_choice' => $schema
                ->boolean()
                ->required()
                ->title('Is Climactic Choice')
                ->description('True when this turn resolved a Choice #3 moral-weight moment OR a Choice #4 session-end hook. Triggers Tier 3 state loading on the NEXT turn.'),

            'defining_choice_id' => $schema
                ->string()
                ->required()
                ->title('Defining Choice ID')
                ->description('When this turn resolved a branching choice, copy the Phase 5 choice_id (e.g. "S2_C3"). Empty string otherwise.'),

            'defining_path_label' => $schema
                ->string()
                ->required()
                ->title('Defining Path Label')
                ->description('When this turn resolved a branching choice (player clicked a presented option or freeform was BRANCH-ALIGNED), copy the canonical label — A, B, or C — exactly as shown in AUTHORED CHOICES for that option. Empty string otherwise.'),

            'defining_choice_line' => $schema
                ->string()
                ->required()
                ->title('Defining Choice Line')
                ->description('When this turn resolved a branching choice, copy the matching Phase 5 Task 8 defining line for the chosen option. Empty string otherwise.'),

            'symbolic_memory_update' => $schema
                ->string()
                ->required()
                ->title('Symbolic Memory Update')
                ->description('A single short natural-language paragraph (1-3 sentences) only when the protagonist\'s interior weather shifted notably this turn. Runtime appends it to symbolic memory. Empty string otherwise.'),

            'session_memory_update' => $schema
                ->string()
                ->required()
                ->title('Session Memory Update')
                ->description('One short sentence only when something narratively important happened this turn. Empty string otherwise.'),
        ];
    }
}
