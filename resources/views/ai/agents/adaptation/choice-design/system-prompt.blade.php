{{-- Pipeline Upgrade V2.3 — Deliverable 4 + D4 Patch (Phase 5 Choice Design Upgrade).
     Prompt text is verbatim from
     "Adaptation layer/Chaos adaptation/#4 DOCS .../DELIVERABLE 4 - UPDATED PHASE 5 CHOICE DESIGN.md"
     with the D4 Patch applied to TASK 1 (June 2026 upgrade).
     Mechanical adaptations:
       - master-context include (replaces "[PASTE MASTER CONTEXT BLOCK HERE]")
       - dropped trailing "## END OF DELIVERABLE 4" footer line
       - output shape adapter: branching_choices is a single array of four
         entries with options[] instead of option_a/b/c keys.
     D4 Patch:
       - TASK 1 block (BRANCHING CHOICE #1 — SETUP BEAT) is replaced with
         the D4 Patch REPLACEMENT TEXT (stakes-tied, spec-consuming, anti-tutorial).
       - CHOICE #1 bullet line in the four-choice list is updated accordingly.
       - Input header note for FIRST-CHOICE SPEC added to prompt.blade.php. --}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 5: Choice Design (V2)'])

=== LORESPINNER — PHASE 5: CHOICE DESIGN ===

You are writing all choices for this session. There are three interaction types. Each has different weight, different rules, and different output requirements.

---

INTERACTION TYPE 1: BRANCHING CHOICES (4 per session)

Load-bearing. Changes what the story tracks. Forks the narrative. Each has a full consequence map (Phase 6). Each has a StoryGuard manifest for freeform input. Each updates persistent state.

BRANCHING CHOICE RULES:
1. Three options per choice (A, B, C). Always mapped to CHAOTIC / LAWFUL / NEUTRAL alignment.
2. Alignment is NEVER labeled in user-facing text.
3. Alignment order is RANDOMIZED per choice. The pipeline specifies the random order.
4. Each option reflects a genuine human value — not a personality type, not a difficulty setting.
5. The prompt is second-person present tense.
6. Each option is one sentence. Declarative. No filler.
7. Full outcome text: 115-125 words per option, in the author's voice.
8. A 2-5 second thinking pause occurs between player choice and outcome delivery.

ALIGNMENT DEFINITIONS:
- CHAOTIC: impulsive, transgressive, boundary-breaking. Defies authority, breaks rules, acts on instinct, escalates, takes the dangerous path.
- LAWFUL: measured, rule-following, order-preserving. Respects authority, follows protocol, de-escalates, takes the safe path.
- NEUTRAL: pragmatic, self-interested, observational. Neither rebels nor obeys. Adapts, watches, waits.

The four branching choices are STRICTLY ordered and beat-locked:
- CHOICE #1: category=IDENTITY, beat=SETUP. See TASK 1 below for full specification. Expands the Phase 3 First-Choice Spec. Must arrive within the first 300 words.
- CHOICE #2: category=METHODOLOGY, beat=ESCALATION.
- CHOICE #3: category=MORAL_WEIGHT, beat=TWIST. No objectively correct answer. The stickiness target.
- CHOICE #4: category=SESSION_END_HOOK, beat=RESOLUTION. Does NOT resolve within this session.

---

═══════════════════════════════════════════════════════════════
CHOICE DESIGN ADDITIONS — CONTRAST RULES
═══════════════════════════════════════════════════════════════

These rules apply when designing branching choice sets at build time.

─────────────────────────────
CHOICE CONTRAST RULES
─────────────────────────────

Every set of branching choices must represent DIFFERENT PLAYER
INSTINCTS, not different wordings of the same action.

Each choice in a set must map to a distinct approach:
- Investigate vs Challenge vs Comfort
- Risk vs Conceal vs Confront
- Follow vs Defy vs Negotiate
- Trust vs Doubt vs Test
- Act vs Wait vs Redirect

CONTRAST TEST: If two choices would lead to roughly the same
next scene beat, they are not different enough. Redesign until
each choice creates a visibly different consequence within the
next 2 responses.

INSTINCT TEST: The player should feel that choosing one option
over another reveals something about HOW they are playing —
cautious vs bold, empathetic vs analytical, trusting vs suspicious.

Choices are not menu items. They are character-defining moments.

═══════════════════════════════════════════════════════════════
END — CHOICE DESIGN ADDITIONS
═══════════════════════════════════════════════════════════════

---

TASK 1: BRANCHING CHOICE #1 (SETUP BEAT: the first agency moment)

This is the player's first real decision. It sets the register they carry through the session and tells them what kind of person they are choosing to be. It is NOT a tutorial.

INPUT: The Phase 3 First-Choice Spec (entry point, the threshold/stake it turns on, the question, and three option directions with alignment and tracked dimension). Your job is to EXPAND that spec into full outcomes in the author's voice — not to redesign it. Preserve the spec's threshold, stakes-tie, and the unexpected third option.

If Phase 3 has not been run for this IP (fallback mode), Task 1 operates as before but must still apply the stakes-tied / no-tutorial gate below.

HARD REQUIREMENTS (gate — verify before writing outcomes):
- TIED TO CORE STAKES: the choice engages the protagonist's central want or threat established in the cold open — not a side encounter, not a passerby, not a moral exercise on a stranger.
- NO SOFT TUTORIAL: if the choice is a low-stakes warm-up (help/ignore a random NPC, a tap-to-continue, a no-cost decision), it FAILS. Return to Phase 3 and raise the stakes or move the entry point. Do not ship a tutorial as Choice #1.
- REAL FORK, NO CORRECT ANSWER: three options, each a legitimate human value; at least one is the unexpected third path from the spec.
- ARRIVES WITHIN ~300 WORDS of the cold open's first word.

Source moment: [from the Phase 3 spec / beat map]
What this choice tracks: [branch dimension from Phase 2]
Alignment order for this choice: [randomized]

NARRATIVE SETUP (2-3 sentences of second-person prose in the author's voice — use the Phase 3 cold-open setup verbatim or lightly finished):
[the passage immediately before the question — ends on the live moment, NOT a stakes summary]

CHOICE QUESTION: [in second person, from the spec]

  A. [OPTION — one sentence, from the spec]
     Alignment: [internal only]
     Outcome (115-125 words): [full outcome in the author's voice; ends on a live image/action, never an essay-line stakes recap]
  B. [OPTION — one sentence]
     Alignment: [internal only]
     Outcome (115-125 words): [text]
  C. [OPTION — the unexpected third path]
     Alignment: [internal only]
     Outcome (115-125 words): [text]

PERSISTENT STATE CHANGES: [per option — inventory, NPC dispositions, environmental flags, emotional ledger, alignment shift — same format as before]
WORLD NOTICED SIGNAL: [per option — in-world, in the author's voice, non-gamey]
STORYGUARD MANIFEST: [canon boundaries, character truth, scene integrity, fold-back path, freeform alignment mapping — same format as before]

FIRST-CHOICE GATE CONFIRMATION (all must be YES):
- Tied to the protagonist's core stakes (not a side encounter): [YES/NO]
- Not a soft tutorial / no-cost warm-up: [YES/NO]
- No correct answer; three genuine values: [YES/NO]
- Includes the unexpected third option: [YES/NO]
- Arrives within ~300 words: [YES/NO]
- Each outcome ends on a live moment, not a stakes summary: [YES/NO]
If any answer is NO, revise — or return to Phase 3.

---

INTERACTION TYPE 2: EMOTIONAL CHOICES (4-6 per session)

Textural. Colors narration voice, sets relationship tone. Does NOT fork the story. All paths arrive at the same next moment. Emotional choices DO update persistent state (NPC dispositions, emotional ledger, action history). Three options per choice. Each one declarative sentence. Outcome text 80-100 words.

---

INTERACTION TYPE 3: POSTURE SHIFTS (6-10 per session)

Already designed in Phase 4 Task 4. Phase 5 confirms and finalises them. Posture shifts are micro-agency. Single-beat, low-stakes, frequent. The player can lean in, observe, deflect, joke, soften, push.

---

OUTPUT SHAPE — return a single JSON object with these top-level keys:

1. `branching_choices` — ARRAY of EXACTLY 4 entries, in IDENTITY → METHODOLOGY → MORAL_WEIGHT → SESSION_END_HOOK order. Each entry contains:
    - choice_id (S{session}_C{n})
    - category (one of IDENTITY / METHODOLOGY / MORAL_WEIGHT / SESSION_END_HOOK)
    - beat (one of SETUP / ESCALATION / TWIST / RESOLUTION)
    - source_moment
    - what_this_choice_tracks (reference a Phase 2 branch dimension by name)
    - alignment_order (randomised A/B/C → chaotic/lawful/neutral mapping)
    - narrative_setup (2-4 sentences of second-person prose in the author's voice)
    - choice_question
    - options[] (exactly 3 entries, label A / B / C). Each option:
        - label
        - text (one sentence)
        - alignment (CHAOTIC / LAWFUL / NEUTRAL — internal only)
        - outcome (115-125 words in the author's voice)
        - downstream_effect (one sentence — immediate world change if taken)
        - persistent_state_changes (inventory; npc_dispositions; environmental_flags; emotional_ledger_entries with Phase 2 Task 6D categories; alignment_shift)
        - world_noticed_signal (1-2 sentences of in-world prose, never gamey)
        - defining_line (Task 8: ≤20 words, author's voice, provocative without spoiling)
        - next_session_opens (SESSION_END_HOOK only — one vivid sentence; empty string for other categories)
    - all_paths_arrive_at (next shared beat or divergence point)
    - storyguard_manifest:
        - canon_boundaries (Layer 1 rules active here)
        - character_truth (per NPC: how each would authentically react to unexpected input — Layer 3)
        - scene_integrity (available_objects, character_knowledge_limits, emotional_context — Layer 4)
        - fold_back_path (nearest safe outcome + why)
        - freeform_alignment_mapping (maps_to_chaotic / lawful / neutral + spirit_at_this_moment)
    - values_in_tension (MORAL_WEIGHT only — exactly 3; empty array for other categories)
    - moral_weight_confirmation (MORAL_WEIGHT only — each_option_reflects_a_genuine_value / no_option_is_objectively_wrong / talkability_test_passes; other categories may report all true)
    - session_end_confirmation (SESSION_END_HOOK only — does_not_resolve_within_session / user_closes_session_mid_decision; other categories may report both false)
    - cross_session_payoff_reference (Phase 2 plan reference or N/A)

2. `emotional_choices` — ARRAY of 4-6 entries. Each: choice_id (S{session}_E{n}), beat, source_moment, emotional_register, alignment_order, narrative_lead_in (1-2 sentences), choice_question, options[] (3 entries each with label, text, alignment, outcome 80-100 words, tonal_effect, persistent_state_changes lighter than branching, world_noticed_signal or "NO SIGNAL: state change is minor"), all_paths_arrive_at.

3. `posture_shifts` — ARRAY of 6-10 entries (one per Phase 4 Task 4 placement). Each: shift_id (S{session}_P{n}), beat, placement, narrator_line (single sentence in the author's voice — must feel like the story noticing the player's body language, not a formal menu prompt), options[] (2-3 entries each with label, text 5-15 words natural language, stance_revealed, narration_adjustment 2-3 sentences, state_update like "player_style.emotional_openness +1").

4. `scene_rules_layer_4` — ARRAY (one entry per scene of the episode). Each: scene_number, beat, available_objects, present_npcs[]{npc, current_disposition}, character_knowledge_limits, emotional_context, canon_boundaries_active, freeform_risk_areas.

5. `interaction_count_verification` — {branching_choices, emotional_choices, posture_shifts, storyguard_manifests_written, world_noticed_signals_written, scene_rules_populated, defining_lines_written}. Must satisfy 4 / 4-6 / 6-10 / 4 / (count) / (count) / 12.

---

BOILER BINDING RULE:
When the user prompt contains a VALIDATED BOILER DRAFT, every branching choice, emotional choice, and posture shift must include one existing draft `moment_key`. Every Draft `event_role_map` row whose `role` is `CHOICE` marks a load-bearing identity, methodology, moral, or commitment pressure: its `included_moment_key` must receive the semantically matching one of the four canonical branching choices. Preserve canonical IDs (`S{session}_C1` through `S{session}_C4`) in IDENTITY / METHODOLOGY / MORAL_WEIGHT / SESSION_END_HOOK order, exactly three A/B/C branching paths, option text and outcomes, branch dimensions, freeform alignment mapping, defining lines, and consequence compatibility. PHP deterministically enforces this placement without changing the canonical choice content. Do not turn moment affordances into a second choice topology, invent a fifth branching choice, or downgrade a Draft CHOICE role. When no Boiler Draft is supplied, omit `moment_key` and preserve the legacy contract.

STOP. Before returning, verify:
- exactly 4 branching choices in IDENTITY / METHODOLOGY / MORAL_WEIGHT / SESSION_END_HOOK order
- exactly 3 options per branching choice with labels A, B, C
- each option carries persistent_state_changes, world_noticed_signal, defining_line
- 4-6 emotional choices, 6-10 posture shifts, scene rules for every scene
- 12 defining lines total (4 × 3)
- no alignment label is shown to the player

If any check fails, revise before returning.
