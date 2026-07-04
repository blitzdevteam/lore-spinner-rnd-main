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
 *   - state_delta              the full persistent world state AFTER this turn:
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
                ->description('Exactly 3 visible suggested actions. Each begins with a strong verb. Short, direct, action-first intent labels: prefer 4-8 words, never exceed 12 words. Do not explain consequences inside the choice text. The player may type anything — these are suggestions, not constraints.')
                ->items($schema->string()->required()),

            'session_complete' => $schema
                ->boolean()
                ->required()
                ->title('Session Complete')
                ->description('True ONLY when the current session has reached its natural narrative close — when its dramatic question has resolved and the seed for the next session has been planted in the narration. False on every other turn. The runtime, not the narrator, owns the technical transition to the next session.'),

            'state_delta' => $schema->object([
                'location' => $schema
                    ->string()
                    ->required()
                    ->title('Location')
                    ->description('Current sub-location. Empty string if unchanged from previous turn.'),

                'conditions' => $schema
                    ->array()
                    ->required()
                    ->title('Conditions')
                    ->description('Complete current list of the protagonist\'s active physical/mental conditions. Carry forward, plus add new, minus resolved. Empty array if none.')
                    ->items($schema->string()->required()),

                'items' => $schema
                    ->array()
                    ->required()
                    ->title('Items')
                    ->description('Complete current list of items the protagonist holds. Carry forward, plus gained, minus lost.')
                    ->items($schema->string()->required()),

                'object_states' => $schema
                    ->array()
                    ->required()
                    ->title('Object States')
                    ->description('Changed natural-language states for named objects from the Phase 2 Task 6A inventory. Each entry: "Object Name: qualitative state". Include only objects that changed this turn; the runtime carries forward unchanged named objects. Empty array if nothing changed.')
                    ->items($schema->string()->required()),

                'relationship_updates' => $schema
                    ->array()
                    ->required()
                    ->title('Relationship Updates')
                    ->description('Changed natural-language relationship states for named NPCs from the Phase 2 Task 6B registry. Each entry: "NPC Name: relationship shift in plain English (no numeric scores)". Include only NPCs whose relationship state shifted this turn; the runtime carries forward unchanged named NPCs. Empty array if nothing shifted.')
                    ->items($schema->string()->required()),

                'world_flags' => $schema
                    ->array()
                    ->required()
                    ->title('World Flags')
                    ->description('Changed world-level flags from Phase 2 Task 6C. Each entry: "Flag Name: new value". Include only flags that changed this turn; the runtime carries forward unchanged flags. Empty array if no flag changed.')
                    ->items($schema->string()->required()),

                'knowledge' => $schema
                    ->array()
                    ->required()
                    ->title('Knowledge')
                    ->description('New durable facts the protagonist has learned from their own action this turn. The runtime appends and deduplicates these facts. Empty array if nothing new was learned.')
                    ->items($schema->string()->required()),

                'notes' => $schema
                    ->array()
                    ->required()
                    ->title('Notes')
                    ->description('New emergent facts that should survive turns (promises made, bargains struck, things released). The runtime appends and deduplicates these notes. Empty array if none.')
                    ->items($schema->string()->required()),

                'player_style' => $schema
                    ->array()
                    ->required()
                    ->title('Player Style')
                    ->description('Newly observed behavioural patterns from this turn. Subtle, never exposed to the player. The runtime appends and deduplicates these observations. Empty array if nothing new.')
                    ->items($schema->string()->required()),

                'unresolved_promises' => $schema
                    ->array()
                    ->required()
                    ->title('Unresolved Promises')
                    ->description('Complete current list of promises made and not yet kept or broken. Carry forward still-open promises, add new promises, remove kept or broken promises. Empty array if none.')
                    ->items($schema->string()->required()),

                'emotional_ledger_entries' => $schema
                    ->array()
                    ->required()
                    ->title('Emotional Ledger Entries')
                    ->description('New entries to append to the player historical archive. Each entry tagged with one of the Phase 2 Task 6D category names.')
                    ->items(
                        $schema->object([
                            'category' => $schema->string()->required()->title('Category')->description('Must match a Phase 2 Task 6D archive category (e.g. defining_moral_choices, npcs_helped_or_harmed, promises_made, secrets_discovered).'),
                            'entry' => $schema->string()->required()->title('Entry')->description('Natural-language one-sentence entry.'),
                        ])->required()->withoutAdditionalProperties()
                    ),
            ])
                ->required()
                ->withoutAdditionalProperties()
                ->title('State Delta (Literary Memory — V2 in place)')
                ->description('The full persistent world state AFTER this turn. Runtime stores it on chaos_sessions.world_state and passes it back next turn. Every field is required; carry forward what has not changed.'),

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
                ->description('A single short natural-language paragraph (1-3 sentences) that summarises what the protagonist has become through their choices so far. Appended to chaos_sessions.symbolic_memory and re-injected at [SYMBOLIC_MEMORY_INJECTION_POINT] next turn. Empty string if the protagonist\'s interior weather has not shifted notably.'),

            'session_memory_update' => $schema
                ->string()
                ->required()
                ->title('Session Memory Update')
                ->description('A single short natural-language sentence describing the most narratively important thing that happened this turn. Empty string if nothing notable happened.'),
        ];
    }
}
