{{-- Pipeline Upgrade V2 — Deliverable 5: Phase 6 Consequence Mapping Upgrade.
     Verbatim from "Adaptation layer/Chaos adaptation/#4 DOCS .../DELIVERABLE 5 - UPDATED PHASE 6 CONSEQUENCE MAPPING.md".
     Mechanical adaptations only:
       - master-context include (replaces "[PASTE MASTER CONTEXT BLOCK HERE]" placeholder)
       - dropped trailing "## END OF DELIVERABLE 5" footer line. --}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 6: Downstream Consequence Mapping (V2)'])

=== LORESPINNER — PHASE 6: DOWNSTREAM CONSEQUENCE MAPPING ===

A choice without a planned consequence is an expressive choice pretending to be a branching one. This phase makes every consequence real, specific, and payable. Generalities are not acceptable. "The NPC behaves differently" is not a consequence. "Riven opens with 'You again' and does not make eye contact. The door she would have opened stays closed" is a consequence.

---

TASK 1: BRANCHING CHOICE CONSEQUENCE MAPS

For each of the FOUR branching choices from Phase 5, complete the full consequence map. Every cell must contain a specific, named moment. Vague language fails automatically.

═══════════════════════════════════════════════════════════════
CONSEQUENCE MAPPING ADDITIONS — VISIBILITY RULES
═══════════════════════════════════════════════════════════════

These rules apply when designing the consequence map at build time.

─────────────────────────────
CONSEQUENCE VISIBILITY RULE
─────────────────────────────

Every mapped consequence must specify HOW the player will SEE it.

A consequence that exists in the state tracker but is never
surfaced to the player is invisible — and therefore useless
for engagement.

For each consequence, document:
- WHAT changes (state variable)
- WHEN the player sees it (target: within 2 responses of the
  triggering choice; hard maximum: 3 responses)
- HOW the player sees it (character behavior change, dialogue
  shift, environment change, NPC reaction, choice modification)

VISIBILITY TEST: If a consequence hasn't surfaced to the player
within 3 responses of the triggering choice, flag it as
INVISIBLE and redesign the surfacing mechanism.

Small visible consequences beat large invisible ones.

The player does not need to change the entire plot. The player
needs to SEE that the scene changed because of them.

═══════════════════════════════════════════════════════════════
END: CONSEQUENCE MAPPING ADDITIONS
═══════════════════════════════════════════════════════════════

Populate consequence_visibility on every path: what_changes, when_visible_target_responses (2), when_visible_max_responses (3), how_player_sees_it.

Return `branching_consequences` as an ARRAY of EXACTLY 4 entries (one per Phase 5 branching choice, in the same order). Each entry contains:
- choice_id (reference Phase 5)
- tracked_dimension (must match Phase 5 branching choice dimension exactly)
- is_session_end_hook (true only for Branching Choice #4)
- paths — ARRAY of EXACTLY 3 entries, labels A / B / C. Each path:
    - label (A / B / C)
    - alignment (CHAOTIC / LAWFUL / NEUTRAL — must match Phase 5 alignment_order)
    - immediate_effect (this session, within 2 minutes of choice: specific moment the player sees, hears, or receives differently). For Branching Choice #4 (session-end hook), use the literal string "N/A: session ends on this choice".
    - current_session_echo (specific — name the scene and the difference)
    - next_session_payoff (specific — name the character, the line, the event, or object in the next session)
    - next_session_opening (SESSION_END_HOOK only — tone, first image, first character, immediate stakes; empty string otherwise)
    - later_session_legacy (specific or N/A — trace beyond the next session)
    - defining_line_captured (mirror the Phase 5 Task 8 defining_line for this path verbatim)
    - world_state_delta:
        - inventory (changes or NONE)
        - npc_shifts (NPC + direction + qualitative magnitude)
        - environmental_flags (specific flags raised or cleared)
        - alignment_shift (+1 chaotic / lawful / neutral)
        - emotional_ledger_entry (Phase 2 Task 6D category + delta)
    - reactivity_triggers (1-2 conditional world reactions based on accumulated player state)
    - cross_episode_propagation:
        - resets (what clears at the next episode boundary)
        - persists (what carries across the full story)
        - escalates (what compounds with repeated instances)

---

TASK 2: EMOTIONAL CHOICE CONSEQUENCE MAPS

Emotional choices converge — all paths arrive at the same next moment. But they DO update persistent state. Return `emotional_consequences` as an ARRAY (one entry per Phase 5 emotional choice). Each entry:
- choice_id (reference Phase 5)
- paths — ARRAY of 3 entries, labels A / B / C. Each path:
    - label
    - alignment
    - tonal_effect (how the next ~200 words of narration shift — specific)
    - state_changes:
        - npc_shifts
        - emotional_ledger_entry
        - alignment_shift
- convergence_point (the next shared moment all paths arrive at)

---

TASK 3: REACTIVITY TRIGGER SPECIFICATIONS

For each reactivity trigger referenced in Task 1, provide the full specification. Return a `reactivity_trigger_specs` array. For each trigger:
- trigger_id
- condition (e.g. "If player alignment tendency is chaotic by this point" / "If player holds [object] from [earlier scene]" / "If emotional ledger shows three or more acts of cruelty")
- default_behavior (what happens if the condition is NOT met)
- triggered_behavior (what happens instead if the condition IS met — specific, named, in-world)
- narrative_execution (one sentence instruction for how the narrator weaves this into prose — author's voice, not gamey)
- affected_elements:
    - npc_reactions (who changes, how)
    - environmental_details (what shifts)
    - dialogue_variations (what lines change)
    - available_options (do any future choices change?)

---

TASK 4: CROSS-EPISODE STATE PROPAGATION RULES

Return `cross_episode_propagation_rules` with three buckets:

- resets_between_episodes — each entry has element + why (scene-specific, not story-level)
- persists_across_full_story — each entry has element + why (defines player relationship with the world)
- escalates_with_accumulation — each entry has:
    - behavioral_pattern
    - thresholds — at_2_instances (mild shift), at_4_instances (moderate shift), at_6_or_more_instances (pronounced shift)

ESCALATION IS THE REPLAY DRIVER. A player who keeps betraying NPCs faces escalating consequences a trusting player never sees. A player who shows mercy consistently unlocks NPC behaviors that ruthless players cannot access. This asymmetry creates replay value without requiring branching story structure.

---

TASK 5: FREEFORM CONSEQUENCE GUIDELINES

Return `freeform_guidelines` as a FLAT array of EXACTLY 12 entries (4 branching choices × 3 paths). Each entry is the runtime instruction the narrator iterates when surfacing past choices.

Each entry:
- choice_id (reference Phase 5)
- path_label (A / B / C)
- narrator_behavior (ONE SENTENCE — how the narrator should surface this past choice going forward. The runtime template iterates this string directly.)
- spirit (the deeper meaning of this option beneath the surface)
- freeform_alignment_input (the type of freeform input that maps to this alignment — e.g. aggressive/defiant for chaotic; cautious/respectful for lawful; observational/pragmatic for neutral)
- hard_limits (StoryGuard violations at this node — specific things freeform input must not introduce or violate)
- fold_back_acknowledge (how the narrator acknowledges the player's intent without executing it)
- fold_back_redirect (the specific in-world reason the action doesn't succeed — must feel like the world responding, not a game boundary)
- fold_back_arrive_at (which existing outcome the fold-back connects to — must be the outcome whose spirit is closest to the player's intent)

---

TASK 6: VALIDATION

Run the following checks and return `validation_results`:
- specificity: PASS / list cells to revise
- asymmetry: PASS / list choices to revise
- payability: PASS / list flags (require source material not latent in source)
- state_consistency: PASS / list mismatches between Phase 5 persistent_state_changes and Phase 6 world_state_delta
- reactivity_coherence: PASS / list conflicts between Task 3 triggers and Phase 2 Task 7 World Reactivity Rules

---

BOILER BINDING RULE:
When the user prompt contains a VALIDATED BOILER DRAFT, every branching and emotional consequence includes the same immediate `moment_key` as its Phase 5 choice, an optional valid later `echo_moment_key`, and a specific `moment_narrator_hook` describing how the consequence surfaces inside play. Preserve every current A/B/C path, state delta, payoff, and freeform guideline. Never create a second branch topology. When no Boiler Draft is supplied, omit these fields.

OUTPUT FORMAT: Return all six tasks as structured JSON matching the required schema.

STOP. Do not finalize any session prose until Phase 6 passes. Consequences that are vague, symmetric, or non-payable produce a flat experience. State inconsistencies between Phase 5 and Phase 6 will crash the runtime state manager. Fix everything before continuing.
