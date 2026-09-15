# LoreSpinner — The Boiler
## Full-Scope R&D Implementation Plan

**Document purpose:** Engineering/product handoff for rebuilding LoreSpinner’s adaptation/runtime structure around a new layer called **The Boiler**.

**Core decision:** Boiler is not a prompt patch, not a summarizer, and not merely “shorter narration.” Boiler is the layer that turns source events into the **playable cut** of a session: the fast, pressure-driven, choice-aware version of the story that users actually play.

---

## 0. Executive Position

LoreSpinner should stop treating the source script/event ledger as the direct runtime experience.

The source is the **canon ledger**.  
The session map is the **episode boundary**.  
Boiler is the **playable cut compiler**.  
Runtime is the **performer of the current playable moment**.

The product leap is this:

> LoreSpinner does not narrate the script. It boils the script into the moments where the user most wants to act, compresses everything else into cinematic glue, and tracks what kind of protagonist the player becomes.

This is the core of the improved game feel.

---

## 1. Problem Statement

The current structure is too event-centered.

Current mental model:

```text
event range
→ narrator receives event context
→ narrator converts event into prose
→ user chooses
→ next event
```

This is safe for canon, but it risks creating a slow experience:

- The user waits through too much narration.
- The runtime feels like it is “walking the script.”
- Choices can appear because the system needs interaction, not because the moment demands action.
- Long source ranges create pressure to summarize or over-narrate.
- Hands-free and multiplayer modes become difficult because the runtime unit is too large and too literary.
- Replayability is hidden because the user does not clearly feel what kind of path they are building.

The fix is not only shorter word counts.

The fix is to create a new playable runtime unit:

> **Playable Moment** — a designed unit of pressure, agency, state change, and exit condition.

---

## 2. Product Goal

Boiler should make a 20–60 minute session feel like a premium playable episode.

The player should feel:

```text
I entered the strongest parts quickly.
I got to act before I got bored.
The world reacted to my instinct.
My choices formed a version of the protagonist.
The story stayed coherent.
I want to see what my path causes next.
```

Boiler must improve:

1. Pacing.
2. Fun.
3. Agency frequency.
4. Replayability.
5. Runtime prompt manageability.
6. Canon fidelity.
7. Author voice preservation.
8. Hands-free readiness.
9. Multiplayer readiness.
10. Session-to-session retention.

---

## 3. Core Philosophy

### 3.1 The script is not the experience

A screenplay may be 100–200 pages. A movie performs a cut.

LoreSpinner should do the same.

The runtime experience should not be:

```text
read every event in prettier prose
```

It should be:

```text
perform the playable cut of the session
```

### 3.2 Boiler is not a summary

A summary says what happened.

Boiler decides what should be **played**, what should be **compressed**, what should become an **echo**, what should become an **optional inspectable detail**, and what should be **cut**.

Boiler outputs a staging plan, not prose.

### 3.3 Runtime should not decide the cut live

The narrator should not be handed Events 1–20 and asked to decide live what matters.

That makes the model do too many jobs:

- preserve canon,
- compress source,
- choose playable moments,
- design choices,
- maintain branch logic,
- write in voice,
- manage pacing,
- avoid spoilers,
- decide when to stop.

That belongs mostly at build time.

### 3.4 Events remain canon

Events are still the source of truth.

Boiler does not rewrite the story into a new plot.

Boiler decides how canon becomes playable.

```text
Canon Layer      = what happens.
Adaptation Layer = dramatic session structure.
Boiler Layer     = playable cut and game feel.
Runtime Layer    = moment performance and player reaction.
```

### 3.5 The player changes HOW, not WHETHER, for spine beats

Boiler should protect required canonical outcomes while giving the player meaningful variance in:

- motive,
- method,
- relationship posture,
- risk tolerance,
- emotional interpretation,
- object handling,
- NPC trust,
- timing pressure,
- later echo surface.

The player may not prevent the spine from existing, but they should strongly shape how the spine feels and what it means.

---

## 4. Best Pipeline Positioning

The cautious plan places Boiler after SessionClose as Phase 7.5.

That is shippable, but it makes Boiler a post-processing compression layer.

For full-scope R&D, Boiler must shape the architecture earlier.

## 4.1 Recommended target pipeline

```text
IpTrimmingMerge
→ FormatDetection
→ IpAudit
→ VoiceLock
→ StorySessionMap
→ EntryPointDiagnosis / Phase 3
→ BoilerDraft / Phase 3.5
→ SessionArchitecture / Phase 4
→ ChoiceDesign / Phase 5
→ ConsequenceMapping / Phase 6
→ SessionClose / Phase 7
→ BoilerFinalize / Phase 7.5
→ EditorialVerification / Phase 8
→ RuntimeNarratorAssembly
→ Live ChaosEngineService runtime
```

## 4.2 Why this is the right position

BoilerDraft needs:

- session ranges from StorySessionMap,
- start event from EntryPointDiagnosis,
- story/format knowledge,
- voice constraints,
- branch opportunity hints.

SessionArchitecture and ChoiceDesign need BoilerDraft because they should design around playable moments, not raw event ranges.

BoilerFinalize then needs:

- architecture beats,
- choices,
- consequence mapping,
- session close/hook,
- final verification needs.

So Boiler should run in two passes:

```text
BoilerDraft    = selects and shapes the playable cut before downstream design.
BoilerFinalize = compiles the final runtime scene cards after choices/consequences/close exist.
```

This preserves the deeper vision.

---

## 5. Boiler Two-Pass Architecture

## 5.1 BoilerDraft — Phase 3.5

**Purpose:** Decide the playable structure of the session before SessionArchitecture and ChoiceDesign.

BoilerDraft answers:

```text
What is the playable promise of this session?
Which source events are PLAY, PULSE, MONTAGE, ECHO, OPTIONAL, or CUT?
What are the playable moments?
Where should player input happen?
Which moments deserve full agency?
Which moments should be compressed?
What duration cut are we building toward?
What needs to be remembered later?
```

BoilerDraft does **not** write final runtime narration.

It outputs the first version of:

- event role map,
- playable moment list,
- moment purpose,
- moment budgets,
- player affordances,
- required canon facts,
- source constraints,
- optional/cut material plan,
- moment-level branch pressure.

## 5.2 BoilerFinalize — Phase 7.5

**Purpose:** Bind the playable structure to final choices, consequences, close, and runtime cards.

BoilerFinalize answers:

```text
What exact card does runtime receive for each playable moment?
Which ChoiceDesign choices attach to which moments?
Which consequence hooks should surface here?
What is the scene exit condition?
What bridge moves the user to the next moment?
What does the session end receipt say?
What does hands-free need to hear?
```

BoilerFinalize outputs the final **Playable Session Cut** used by runtime.

---

## 6. Runtime Unit Shift

Current source unit:

```text
Event
```

New gameplay unit:

```text
Playable Moment
```

A playable moment may contain:

```text
half of one event
one event
multiple events
a montage across many events
an inserted choice between two canonical facts
a reaction echo from a past decision
```

Runtime should progress by playable moments, while keeping event IDs as canon anchors.

New truth model:

```text
game.current_playable_moment_id
→ playable_moment
→ anchor_event_id
→ source_event_refs
→ canon constraints
→ choice/consequence hooks
```

`current_event_id` remains important, but it becomes the canon anchor, not the primary game-feel unit.

---

## 7. Event Treatment Taxonomy

Every source event in a session must receive exactly one Boiler role.

Allowed roles:

## 7.1 PLAY

Full live staging. Use when the event has:

- protagonist embodiment,
- immediate pressure,
- active NPC/object tension,
- meaningful player action,
- potential for 2–4 turns,
- strong sensory or dramatic hook.

## 7.2 PULSE

A short playable beat. Use when the event is worth touching but not worth a full scene.

Typical runtime:

```text
60–160 words
one pressure image
one quick player action or choice
```

## 7.3 CHOICE

A durable branch decision. Use when the moment creates identity or downstream consequence.

This can overlap conceptually with PLAY/PULSE, but in the event role map it should indicate the event’s dominant purpose.

## 7.4 REACTION

The event appears as consequence surface, not as full play.

Examples:

- NPC tone changes.
- HUD updates.
- Object warms.
- Door seals.
- Ally withholds truth.
- The world reacts to a previous posture.

## 7.5 MONTAGE

Compression material. Use for:

- travel,
- repeated action,
- time jumps,
- procedural setup,
- atmosphere,
- transition,
- connective tissue.

## 7.6 ECHO

Material delayed into later memory, object, line, image, or NPC reaction.

Useful for preserving meaning without slowing the current moment.

## 7.7 OPTIONAL

Inspectable or expandable material. The user can ask about it, inspect it, or unlock it, but it is not required in the main cut.

## 7.8 CUT

Removed from runtime. Must not include major turning points unless a reintroduction route is provided.

---

## 8. Playability Scoring Model

BoilerDraft should score every event or event cluster on seven axes.

```text
Agency       — can the player meaningfully act here?
Pressure     — is there danger, desire, cost, temptation, or conflict?
Image        — is there a memorable sensory/object/cinematic hook?
Consequence  — can this affect state, NPCs, objects, or later echoes?
Identity     — does this reveal what kind of protagonist the player is becoming?
Onboarding   — does this teach the world through action instead of exposition?
Voice Hook   — does this carry author-specific surface texture?
```

Suggested scoring:

```text
0 = absent
1 = weak
2 = useful
3 = strong
```

Events with high Agency + Pressure + Identity become PLAY/CHOICE.

Events with high Image but low Agency become PULSE/MONTAGE.

Events with high Consequence but low immediacy become REACTION/ECHO.

Events with low total score become CUT or BACKGROUND.

---

## 9. Duration Cut Strategy

Boiler should support multiple session cuts, but R&D should center on one default first.

## 9.1 Quick Cut — 20 minutes

Purpose: commute, casual play, retention.

```text
3–5 playable moments
2–4 meaningful choices
short pulses
minimal optional exploration
hard hook at end
```

## 9.2 Standard Cut — 30 minutes

Purpose: default product experience.

```text
5–8 playable moments
4–6 meaningful inputs
1 identity-defining choice
1 relationship/object consequence
session receipt
strong next-session hook
```

## 9.3 Deep Cut — 60 minutes

Purpose: fans, long sessions, evening play.

```text
8–12 playable moments
more inspection
more freeform agency
more relationship texture
more optional lore
more branch echo
```

## 9.4 Recommended R&D priority

Build the **Standard 30-minute cut** first.

Then derive Quick 20 and Deep 60 from the same BoilerDraft.

---

## 10. Data Model

The full-scope version should use first-class runtime entities, but keep JSON phase artifacts for auditability.

## 10.1 Keep phase artifacts on `session_adaptations`

Add fields:

```php
$table->json('boiler_draft')->nullable();
$table->json('boiler_final_cut')->nullable();
$table->string('boiler_status')->default('pending');
$table->string('boiler_version')->nullable();
$table->text('boiler_error')->nullable();
$table->timestamp('boiler_generated_at')->nullable();
$table->timestamp('boiler_finalized_at')->nullable();
```

Statuses:

```text
pending
draft_generating
draft_completed
finalizing
completed
failed
skipped
```

## 10.2 Add `playable_session_cuts`

Runtime-facing compiled cut.

```php
Schema::create('playable_session_cuts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('story_id')->constrained()->cascadeOnDelete();
    $table->foreignId('session_adaptation_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('session_number');
    $table->string('cut_key')->default('standard_30'); // quick_20, standard_30, deep_60
    $table->string('boiler_version')->default('1.0');
    $table->string('status')->default('draft');
    $table->unsignedInteger('target_playtime_minutes')->default(30);
    $table->string('playable_promise')->nullable();
    $table->json('session_spine')->nullable();
    $table->json('runtime_pacing_contract')->nullable();
    $table->json('session_end_card_spec')->nullable();
    $table->json('verification_summary')->nullable();
    $table->timestamps();
});
```

## 10.3 Add `playable_moments`

```php
Schema::create('playable_moments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('playable_session_cut_id')->constrained()->cascadeOnDelete();
    $table->string('moment_id'); // S1_M01
    $table->unsignedInteger('moment_order');
    $table->string('treatment'); // PLAY, PULSE, MONTAGE, REACTION, ECHO, OPTIONAL, CUT
    $table->string('moment_type')->nullable(); // cold_open, pressure_choice, setpiece, bridge, receipt
    $table->json('source_event_ids');
    $table->foreignId('anchor_event_id')->nullable()->constrained('events')->nullOnDelete();
    $table->string('interaction_type')->nullable();
    $table->json('duration_budget')->nullable();
    $table->json('word_budget')->nullable();
    $table->text('runtime_goal')->nullable();
    $table->text('live_pressure')->nullable();
    $table->json('player_affordances')->nullable();
    $table->json('canon_must_keep')->nullable();
    $table->json('choice_links')->nullable();
    $table->json('consequence_hooks')->nullable();
    $table->json('object_anchors')->nullable();
    $table->json('npc_pressure')->nullable();
    $table->json('world_state_hooks')->nullable();
    $table->json('forbidden_expansions')->nullable();
    $table->text('exit_condition')->nullable();
    $table->text('next_bridge')->nullable();
    $table->json('scene_card')->nullable();
    $table->timestamps();

    $table->unique(['playable_session_cut_id', 'moment_id']);
});
```

## 10.4 Add `event_playability_roles`

This gives audit coverage for every source event.

```php
Schema::create('event_playability_roles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('playable_session_cut_id')->constrained()->cascadeOnDelete();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('event_position')->nullable();
    $table->string('role');
    $table->string('included_in_moment_id')->nullable();
    $table->text('why')->nullable();
    $table->json('canon_must_keep')->nullable();
    $table->text('cut_or_compression_note')->nullable();
    $table->text('reintroduction_route')->nullable();
    $table->timestamps();

    $table->unique(['playable_session_cut_id', 'event_id']);
});
```

## 10.5 Game runtime fields

Add to `games` or existing game state JSON.

Recommended explicit fields:

```php
$table->foreignId('current_playable_session_cut_id')->nullable()->constrained('playable_session_cuts')->nullOnDelete();
$table->string('current_playable_moment_id')->nullable();
$table->unsignedInteger('current_playable_moment_order')->nullable();
$table->unsignedInteger('playable_moment_turn_count')->default(0);
$table->json('completed_playable_moment_ids')->nullable();
$table->json('boiler_runtime_log')->nullable();
```

If migration risk is high, store these in `game_state` JSON first.

---

## 11. New Code Files

## 11.1 Agents

```text
app/Ai/Agents/Adaptation/BoilerDraftAgent.php
app/Ai/Agents/Adaptation/BoilerFinalizeAgent.php
```

## 11.2 Jobs

```text
app/Jobs/Adaptation/BoilerDraftJob.php
app/Jobs/Adaptation/BoilerFinalizeJob.php
```

## 11.3 Prompt views

```text
resources/views/ai/agents/adaptation/boiler-draft/system-prompt.blade.php
resources/views/ai/agents/adaptation/boiler-draft/prompt.blade.php

resources/views/ai/agents/adaptation/boiler-finalize/system-prompt.blade.php
resources/views/ai/agents/adaptation/boiler-finalize/prompt.blade.php
```

## 11.4 Schemas and validators

```text
app/Ai/Schemas/BoilerDraftSchema.php
app/Ai/Schemas/BoilerFinalCutSchema.php
app/Ai/Validators/BoilerDraftValidator.php
app/Ai/Validators/BoilerFinalCutValidator.php
```

## 11.5 Context builders

```text
app/Ai/Adaptation/BoilerDraftContextBuilder.php
app/Ai/Adaptation/BoilerFinalizeContextBuilder.php
```

## 11.6 Runtime services

```text
app/Services/Runtime/PlayableMomentResolver.php
app/Services/Runtime/PlayableMomentProgressService.php
app/Services/Runtime/BoilerRuntimeContextBuilder.php
app/Services/Runtime/SessionReceiptBuilder.php
```

## 11.7 Models

```text
app/Models/PlayableSessionCut.php
app/Models/PlayableMoment.php
app/Models/EventPlayabilityRole.php
```

---

## 12. BoilerDraft Input Contract

BoilerDraft receives enough to design the playable cut, not enough to drown in full source.

## 12.1 Story-level inputs

```text
story title
format detection
protagonist
world summary
dramatic question
genre signals
core canon constraints
major turning points
branch opportunities from StorySessionMap
voice surface slice
```

## 12.2 Session-level inputs

```text
session_number
event_range
chapters_covered
primary_dramatic_question
emotional_register
entry_point_diagnosis.start_event_id
entry_point_diagnosis.cold_open as direction only
entry_point_diagnosis.emotional_promise
entry_point_diagnosis.must_reintroduce
```

## 12.3 Source event inputs

For each event in range:

```json
{
  "event_id": 123,
  "event_position": 12,
  "session_number": 1,
  "objective": "What observably happens.",
  "compressed_text": "Short source excerpt or compressed event text.",
  "characters_present": [],
  "location": "",
  "important_objects": [],
  "dialogue_samples": [],
  "potential_choice_pressure": ""
}
```

Do not pass unlimited full source by default.

For high-score candidate events, the context builder may include a longer excerpt.

## 12.4 Voice inputs

Use a controlled slice:

```text
anchor_card
master_rule_1_hard_bans
2–4 voice_anchor excerpts most relevant to this session
character dialogue ceilings
screenplay_to_prose or novel_to_play rules
```

Boiler needs voice enough to identify “cool extracts” and avoid generic play cards, but it should not become a prose generator.

---

## 13. BoilerDraft Output Schema

Stored as `session_adaptations.boiler_draft`.

```json
{
  "boiler_version": "1.0",
  "session_number": 1,
  "recommended_default_cut": "standard_30",
  "playable_promise": "",
  "session_spine": [],
  "event_role_map": [],
  "playable_moment_draft": [],
  "duration_cut_variants": {},
  "cut_material": [],
  "optional_material": [],
  "must_reintroduce_later": [],
  "branch_pressure_map": [],
  "runtime_pacing_contract_draft": {},
  "design_notes_for_phase_4": {},
  "design_notes_for_phase_5": {},
  "verification_notes": {}
}
```

## 13.1 Draft playable moment schema

```json
{
  "moment_id": "S1_M01",
  "moment_order": 1,
  "draft_treatment": "PLAY",
  "source_event_ids": [101, 102],
  "anchor_event_id": 101,
  "moment_title": "The RENDAN Shard",
  "runtime_goal": "Force Nora's first posture toward Amora's clean grief machinery.",
  "player_desire": "Do I obey, refuse, or subvert?",
  "live_pressure": "Ilya places RENDAN between Nora and the exit.",
  "interaction_type_recommendation": "open_input",
  "expected_turns": "2-3",
  "word_budget_recommendation": {
    "screen_default": "100-180",
    "audio_default": "60-120",
    "setpiece_max": 280
  },
  "player_affordances": [
    "take the shard",
    "refuse the shard",
    "pocket it without agreeing",
    "ask what RENDAN means"
  ],
  "canon_must_keep": [],
  "allowed_variance": [],
  "branch_dimension_candidates": [],
  "object_anchors": [],
  "npc_pressure": [],
  "cool_extracts": [],
  "forbidden_expansions": [],
  "exit_condition_draft": "The player defines Nora's posture toward Ilya and RENDAN."
}
```

---

## 14. Downstream Phase Changes

## 14.1 SessionArchitecture / Phase 4

Current role:

```text
design session beat architecture from session/event context
```

New role:

```text
design beat architecture around BoilerDraft playable moments
```

Phase 4 should receive:

```text
boiler_draft.playable_moment_draft
boiler_draft.session_spine
boiler_draft.design_notes_for_phase_4
entry point
session promise
voice slice
```

Phase 4 output should add:

```json
{
  "moment_beat_architecture": [
    {
      "moment_id": "S1_M01",
      "beat_type": "cold_open_pressure",
      "emotional_function": "clinical control into first defiance",
      "required_state_change": "player posture toward Amora established",
      "moment_gravity_rule": "after 2 exploratory beats, force the shard decision"
    }
  ]
}
```

## 14.2 ChoiceDesign / Phase 5

ChoiceDesign should no longer design choices against raw events alone.

It should design choices against Boiler moments.

Inputs:

```text
boiler_draft moment list
moment_beat_architecture
branch dimensions
first_choice_spec
anchor_card
```

ChoiceDesign output should include:

```json
{
  "choice_id": "S1_C1",
  "moment_id": "S1_M01",
  "source_event_ids": [101, 102],
  "interaction_type": "open_input_with_three_suggested_vectors",
  "screen_choices": [],
  "spoken_choices": [],
  "freeform_aliases": [],
  "branch_dimension": "compliance_vs_defiance",
  "choice_visibility_rule": "show as three options on screen; allow natural freeform in input"
}
```

Important: keep existing choice IDs for compatibility, but add `moment_id` binding.

## 14.3 ConsequenceMapping / Phase 6

ConsequenceMapping should map choices to:

```text
moment-level immediate reaction
later moment echo
session receipt line
NPC state
object state
world flag
branch dimension
```

Add output fields:

```json
{
  "moment_consequence_hooks": [
    {
      "choice_id": "S1_C1",
      "moment_id": "S1_M01",
      "immediate_surface": "Ilya's tone cools or softens based on posture.",
      "echo_moment_ids": ["S1_M04", "S2_M02"],
      "receipt_language": "You refused the clean channel.",
      "world_state_updates": [],
      "npc_state_updates": []
    }
  ]
}
```

## 14.4 SessionClose / Phase 7

SessionClose should produce a **session receipt spec**, not only a prose close/hook.

Required fields:

```json
{
  "session_end_card_spec": {
    "path_label_rules": [],
    "world_remembers_slots": [],
    "npc_shift_slots": [],
    "object_shift_slots": [],
    "branch_summary_slots": [],
    "next_session_hook": "",
    "shareable_line_template": ""
  }
}
```

This is crucial for replay and retention.

---

## 15. BoilerFinalize Input Contract

BoilerFinalize receives:

```text
boiler_draft
session_architecture
choice_design
consequence_mapping
session_close
voice surface slice
current source event compact refs
```

It binds everything into runtime-ready cards.

---

## 16. Boiler Final Cut Output Schema

Stored as `session_adaptations.boiler_final_cut` and normalized into `playable_session_cuts` + `playable_moments`.

```json
{
  "boiler_version": "1.0",
  "session_number": 1,
  "cut_key": "standard_30",
  "target_playtime_minutes": 30,
  "source_format": "SCREENPLAY",
  "playable_promise": "",
  "session_spine": [],
  "event_role_map": [],
  "playable_moments": [],
  "bridge_cards": [],
  "cut_material": [],
  "optional_material": [],
  "session_end_card_spec": {},
  "runtime_pacing_contract": {},
  "hands_free_contract": {},
  "verification_notes": {}
}
```

## 16.1 Final playable moment card

```json
{
  "moment_id": "S1_M01",
  "moment_order": 1,
  "moment_type": "cold_open_play",
  "treatment": "PLAY",
  "source_event_ids": [101, 102],
  "anchor_event_id": 101,
  "title": "The RENDAN Shard",
  "runtime_goal": "Make the player define Nora's first posture toward Amora and clean grief.",
  "opening_image": "White light. Chrome ribs. A patient reaching for someone the loop erased.",
  "live_pressure": "Ilya places the RENDAN shard between Nora and the exit.",
  "where_we_are": "Amora Dive Lab, immediately after a cleared grief loop.",
  "what_player_knows": [],
  "what_is_visible_now": [],
  "what_is_pressuring_player": [],
  "player_affordances": [],
  "interaction_type": "open_input",
  "choice_links": [],
  "canon_must_keep": [],
  "allowed_variance": [],
  "required_dialogue": [],
  "cool_extracts": [],
  "voice_surface_notes": [],
  "npc_pressure": [],
  "object_anchors": [],
  "world_state_hooks": [],
  "branch_dimension_hooks": [],
  "consequence_hooks": [],
  "forbidden_expansions": [],
  "duration_budget": {
    "target_turns": 3,
    "max_turns_before_gravity": 3,
    "max_turns_total": 5
  },
  "word_budget": {
    "first_input_max_words": 160,
    "default_response_words": "80-160",
    "setpiece_response_words": "180-280",
    "hands_free_default_words": "55-110"
  },
  "exit_condition": {
    "type": "player_posture_defined",
    "description": "The player has defined Nora's posture toward Ilya and RENDAN.",
    "valid_exit_signals": [
      "choice_selected",
      "branch_aligned_freeform_resolved",
      "explicit_refusal_or_compliance_action"
    ]
  },
  "next_bridge": {
    "bridge_type": "hard_cut",
    "max_words": 70,
    "must_include": [],
    "must_not_include": []
  }
}
```

---

## 17. Interaction Types

Boiler should break the tyranny of always showing three choices.

Supported interaction types:

```text
open_input
open_input_with_suggestions
two_option_crisis
three_option_branch
inspection_prompt
dialogue_prompt
body_action_prompt
fast_forward_prompt
linger_or_leave
continue_prompt
session_end_choice
hands_free_spoken_choice
```

## 17.1 `open_input`

Use when expression is the fun.

Example:

```text
Ilya waits. What do you say?
```

## 17.2 `open_input_with_suggestions`

Default for many LoreSpinner moments.

Screen may show:

```text
You can refuse, ask what RENDAN means, or take the shard without agreeing.
```

This allows the player to type naturally without forcing A/B/C.

## 17.3 `two_option_crisis`

Use when the story has collapsed to a true binary.

Example:

```text
Seal the channel.
Open it.
```

## 17.4 `three_option_branch`

Use when three values are genuinely in tension.

Example:

```text
Defy.
Probe.
Comply tactically.
```

## 17.5 `inspection_prompt`

Use when touching/looking is fun.

Example:

```text
The pod. The monitor. The shard. Ilya's hand.
What do you inspect first?
```

## 17.6 `dialogue_prompt`

Use when speech is agency.

Example:

```text
What do you say to Jace?
```

## 17.7 `fast_forward_prompt`

Use when the user can move past low-agency material.

Example:

```text
Leave now, or linger one beat longer.
```

---

## 18. Runtime Pacing Contract

Each final cut must include:

```json
{
  "first_input_max_words": 160,
  "default_response_words": "80-180",
  "pulse_response_words": "60-140",
  "major_reveal_words": "200-320",
  "climax_words": "250-450",
  "max_exploratory_beats_before_gravity": 3,
  "max_moment_turns_without_state_change": 4,
  "choice_density_target": "one meaningful player input every 1-3 minutes",
  "do_not_walk_through_cut_events": true,
  "montage_allowed": true,
  "default_to_pressure_not_explanation": true
}
```

The rule is:

> Expand only when the player has earned, chosen, or requested expansion.

Default should be fast.

---

## 19. Hands-Free Readiness

Boiler should make hands-free easier by outputting audio-aware fields now.

Each playable moment should include:

```json
{
  "hands_free": {
    "audio_word_budget": "55-110",
    "spoken_scene_setup": "",
    "spoken_choice_text": [],
    "interrupt_safe_points": [],
    "requires_confirmation": false,
    "confirmation_phrase": "",
    "repeatable_state_summary": "",
    "listen_after": "choice_prompt"
  }
}
```

Hands-free choices must be shorter than screen choices.

Example:

Screen:

```text
Tell Wesley to stop treating it like a prop and say what he is not saying.
```

Hands-free:

```text
Pressure Wesley.
```

The key is not to build full voice mode now, but to make Boiler produce the structure that voice mode will need.

---

## 20. Multiplayer Readiness

Boiler should also prepare future communal mode.

Each moment can include:

```json
{
  "multiplayer": {
    "vote_safe": true,
    "roleplay_safe": true,
    "audience_prompt": "",
    "consensus_window": false,
    "private_role_hooks": []
  }
}
```

Do not build multiplayer now.

But do not design Boiler in a way that prevents it.

---

## 21. Runtime Integration

## 21.1 Runtime mode selection

Runtime has two modes:

```text
Event Mode   — legacy fallback.
Boiler Mode  — active playable moment runtime.
```

Boiler Mode is used when:

```text
story/adaptation boiler_enabled = true
session_adaptation.boiler_status = completed
playable_session_cut exists
current_playable_moment_id is resolvable
```

## 21.2 Runtime receives current moment, not full event range

In Boiler Mode, runtime context should include:

```text
current playable moment card
compact session spine
runtime pacing contract
current branch state
relevant recent player choices
relevant NPC/object/world state
choice/consequence hooks for current moment
voice anchor/card/self-check
```

Runtime should not receive the full session event range as active material.

Full source can remain retrievable or included in tiny quoted/canonical excerpts only when needed.

## 21.3 Runtime narrator job changes

Old job:

```text
Render CURRENT_EVENT as an interactive scene.
```

New Boiler Mode job:

```text
Perform CURRENT_PLAYABLE_MOMENT as a playable scene.
Resolve the player's latest action first.
Stay inside the moment card.
Preserve canon constraints.
Use the word budget.
Stop at pressure or exit condition.
Emit progression metadata.
```

## 21.4 Runtime prompt section

Add to `runtime-narrator-template.blade.php`:

```text
SECTION X: BOILER PLAYABLE CUT MODE

If CURRENT_PLAYABLE_MOMENT is present, it is your active structure.
Do not walk through raw source events.
Do not narrate cut events.
Do not summarize the full event range.
Perform only the current playable moment.

CURRENT PLAYABLE MOMENT:
{{current_playable_moment_card}}

SESSION SPINE:
{{compact_session_spine}}

RUNTIME PACING CONTRACT:
{{runtime_pacing_contract}}

EXIT CONDITION:
{{exit_condition}}

Rules:
- Resolve player input first.
- Advance at most one meaningful beat.
- Stay inside the moment.
- Ask for input early.
- Use scene pressure, not explanation.
- Do not reveal future moments.
- When exit condition is met, stop cleanly and mark scene_progress.exit_condition_met = true.
```

## 21.5 Structured metadata

Runtime should emit hidden/internal metadata if the current response format allows it.

Recommended internal shape:

```json
{
  "scene_progress": {
    "moment_id": "S1_M01",
    "exit_condition_met": true,
    "exit_reason": "Player chose Nora's posture toward RENDAN.",
    "resolved_choice_id": "S1_C1",
    "resolved_branch_dimension": "compliance_vs_defiance",
    "next_moment_hint": "S1_M02"
  }
}
```

If hidden metadata is not currently supported, V1 can advance on recognized choice resolution and backend heuristics.

But full-scope R&D should add structured metadata support.

---

## 22. PlayableMomentResolver

Create:

```text
app/Services/Runtime/PlayableMomentResolver.php
```

Responsibilities:

```text
resolve current playable session cut
resolve current playable moment
initialize first moment on session start
advance to next moment when exit condition is met
update current_event_id to next anchor_event_id
increment moment turn count
log completed moments
fallback safely when Boiler is absent
hard fail when Boiler is enabled but invalid
```

Session start logic:

```php
$cut = PlayableSessionCut::forGameSession($game)->active()->first();
$firstMoment = $cut->moments()->orderBy('moment_order')->first();

$game->current_playable_session_cut_id = $cut->id;
$game->current_playable_moment_id = $firstMoment->moment_id;
$game->current_playable_moment_order = $firstMoment->moment_order;
$game->playable_moment_turn_count = 0;
$game->current_event_id = $firstMoment->anchor_event_id;
$game->save();
```

Advance logic:

```php
if ($progress->exit_condition_met) {
    $nextMoment = $resolver->nextMoment($game);

    $game->completed_playable_moment_ids = [...$completed, $currentMoment->moment_id];
    $game->current_playable_moment_id = $nextMoment->moment_id;
    $game->current_playable_moment_order = $nextMoment->moment_order;
    $game->playable_moment_turn_count = 0;
    $game->current_event_id = $nextMoment->anchor_event_id;
    $game->save();
}
```

Important: update `current_playable_moment_id` and `current_event_id` atomically.

---

## 23. Session End Receipt

Every Boiler session should end with a story-native receipt.

Purpose:

```text
show what the player changed
surface identity formation
make replay visible
create return motivation
```

Example:

```text
Session complete.

The world remembers:
- You refused Ilya's clean channel.
- You kept RENDAN out of Amora's hand.
- You let the lens stay warm.

You are becoming:
Unfiltered.

Ilya trust: damaged.
Mirror access: quieter, not gone.
Eli attachment: preserved.

Next:
The city starts answering through glass.
```

Do not use fake global statistics in V1.

Later, once real aggregate data exists, add:

```text
62% of players took the shard.
You left it behind.
```

---

## 24. EditorialVerification Updates

EditorialVerification must verify BoilerDraft and BoilerFinalCut.

Add checks:

## 24.1 Coverage

Fail if:

```text
any event in session range has no role
any event has multiple roles
any major turning point is CUT without reintroduction
entry point event is not included in first playable moment
```

## 24.2 Canon

Fail if Boiler:

```text
contradicts source
changes required outcome
reveals future material too early
breaks object continuity
breaks NPC knowledge boundaries
changes protagonist identity beyond allowed variance
```

## 24.3 Playability

Fail if:

```text
first input comes too late
PLAY moment has no live pressure
PLAY moment has no player affordance
moment lacks exit condition
too many PLAY moments run without branch/reaction
scene feels like summary instead of playable pressure
```

## 24.4 Pacing

Warn/fail if:

```text
default word budget exceeds 220
first_input_max_words exceeds 220
max_turns_before_gravity missing
moment count does not match target duration
montage/bridge cards missing between large source jumps
```

## 24.5 Replayability

Warn/fail if:

```text
branch choices do not affect later surfaces
session receipt has no path identity
NPCs/objects/world do not remember player posture
all choices feel tactical but not identity-forming
```

## 24.6 Voice surface

Warn if:

```text
cool_extracts missing
object anchors missing
voice_surface_notes generic
author-specific diction absent from moment cards
Boiler tries to write prose instead of staging cards
```

---

## 25. Prompt Engineering Notes

## 25.1 BoilerDraft system prompt core

```text
You are The Boiler Compiler for LoreSpinner.

Your job is not to summarize the source.
Your job is to compile this session into a fast, playable cut.

The source events are canon.
Do not contradict canon.
Do not invent new major plot.
Do not preserve every event equally.

Classify every event as PLAY, PULSE, CHOICE, REACTION, MONTAGE, ECHO, OPTIONAL, or CUT.

Prioritize:
- protagonist embodiment
- immediate pressure
- object interaction
- NPC friction
- replayable identity
- consequence visibility
- fast player input
- author voice surface

Output only valid JSON.
```

## 25.2 BoilerFinalize system prompt core

```text
You are The Boiler Finalizer for LoreSpinner.

You receive the BoilerDraft, SessionArchitecture, ChoiceDesign, ConsequenceMapping, and SessionClose.

Your job is to compile runtime-ready playable moment cards.

Do not rewrite the story.
Do not add new plot.
Do not output final prose.
Bind playable moments to choices, consequences, exit conditions, bridges, session receipt specs, and pacing budgets.

Output only valid JSON.
```

---

## 26. Format-Specific Boiler Behavior

## 26.1 Screenplay Boiler

Screenplay Boiler is a **shot-to-play compiler**.

Extract:

```text
image anchors
hard cuts
object beats
dialogue confrontations
physical action
visible pressure
scene entrances/exits
```

Avoid:

```text
novelizing everything
explaining emotional states
preserving scene headings as output
over-expanding action lines
```

Screenplay action is staging material, not prose to copy.

## 26.2 Novel Boiler

Novel Boiler is an **interiority-to-play compiler**.

Extract:

```text
desire
fear
secret
contradiction
relationship pressure
object fixation
decision pressure
sensory anchor
```

Convert internal pages into external playable pressure.

Example:

```text
Three pages of doubt
→ one object in hand
→ one person waiting
→ one trust choice
```

## 26.3 Shared schema

Both formats produce the same Boiler output schema.

Only extraction strategy differs.

---

## 27. UI / UX Implications

Boiler unlocks better UI patterns.

## 27.1 Moment-first screen

Instead of showing a long scroll of prose, the UI can show:

```text
current scene pulse
active object / NPC / threat
choice or open prompt
path feedback
```

## 27.2 Optional expansion

User can tap:

```text
inspect
ask
linger
recap
more detail
```

This lets the default path stay fast while curious users can go deeper.

## 27.3 Session map

After a session, show:

```text
played moments
locked echoes
objects changed
NPC shifts
path label
next hook
```

This makes replay visible.

---

## 28. Commands for Daniel

Add:

```bash
php artisan lorespinner:generate-boiler-draft {story_id} {--session=} {--force}
php artisan lorespinner:finalize-boiler {story_id} {--session=} {--cut=standard_30} {--force}
php artisan lorespinner:validate-boiler {story_id} {--session=}
php artisan lorespinner:inspect-boiler {story_id} {--session=}
php artisan lorespinner:playtest-boiler-prompt {story_id} {--session=} {--moment=}
```

Example:

```bash
php artisan migrate

php artisan lorespinner:generate-boiler-draft 25 --session=1 --force
php artisan lorespinner:finalize-boiler 25 --session=1 --cut=standard_30 --force
php artisan lorespinner:validate-boiler 25 --session=1
php artisan lorespinner:inspect-boiler 25 --session=1
```

---

## 29. Implementation Sprints

## Sprint 0 — Spec Lock

Deliver:

```text
Boiler roles
BoilerDraft schema
BoilerFinalCut schema
runtime moment card schema
feature flag strategy
QA checklist
```

Decision:

```text
Boiler is default for R&D canaries.
Boiler is feature-flagged for production.
```

## Sprint 1 — Storage + Models

Build:

```text
migrations for session_adaptations boiler fields
playable_session_cuts table
playable_moments table
event_playability_roles table
models and relationships
validators
```

Acceptance:

```text
existing stories unaffected
invalid Boiler JSON rejected
all event role coverage enforceable
```

## Sprint 2 — BoilerDraft

Build:

```text
BoilerDraftAgent
BoilerDraftJob
BoilerDraftContextBuilder
boiler-draft prompts
schema validation
```

Acceptance:

```text
every source event gets a role
first playable moment binds to start_event_id
moment draft has affordances, pressure, canon constraints, and exit condition draft
```

## Sprint 3 — Rewire Phase 4 / SessionArchitecture

Build:

```text
pass BoilerDraft into SessionArchitecture
add moment_beat_architecture output
ensure Phase 4 designs around moment IDs
```

Acceptance:

```text
Phase 4 output references moment IDs
no architecture beats point only to vague event ranges
```

## Sprint 4 — Rewire Phase 5 / ChoiceDesign

Build:

```text
pass BoilerDraft + moment architecture into ChoiceDesign
bind choices to moment_id
add spoken_choice_text / freeform_aliases fields where useful
```

Acceptance:

```text
choices are designed for playable moments
first choice is attached to first relevant moment
branch dimensions remain compatible
```

## Sprint 5 — Rewire Phase 6 / ConsequenceMapping

Build:

```text
moment_consequence_hooks
session receipt hooks
object/NPC/world state update hooks
```

Acceptance:

```text
each branch choice has immediate surface and delayed echo plan
consequence hooks reference moment IDs
```

## Sprint 6 — Rewire Phase 7 / SessionClose

Build:

```text
session_end_card_spec
path label rules
world remembers slots
next-session hook binding
```

Acceptance:

```text
session close can generate a player-specific receipt
receipt uses logged choices, not fake claims
```

## Sprint 7 — BoilerFinalize

Build:

```text
BoilerFinalizeAgent
BoilerFinalizeJob
BoilerFinalizeContextBuilder
final cut validator
normalization into runtime tables
```

Acceptance:

```text
final playable moments are runtime-ready
choice/consequence/session close are bound
pacing contracts included
hands-free fields present
```

## Sprint 8 — EditorialVerification

Build:

```text
Boiler verification checks
coverage/canon/playability/pacing/replayability/voice surface validation
```

Acceptance:

```text
bad Boiler cuts fail before runtime
major turning points cannot be silently cut
first agency delay is flagged
```

## Sprint 9 — Runtime Boiler Mode

Build:

```text
PlayableMomentResolver
BoilerRuntimeContextBuilder
runtime prompt Boiler section
moment state tracking
scene progress metadata parsing
```

Acceptance:

```text
game starts at first Boiler moment
runtime receives current moment card
runtime does not walk raw events
moment turn count increments
moment advances on exit condition
current_event_id follows anchor_event_id
legacy mode still works
```

## Sprint 10 — Session Receipt Runtime

Build:

```text
SessionReceiptBuilder
runtime/session-end trigger
receipt rendering
path labels
object/NPC/world remembers surfaces
```

Acceptance:

```text
session end shows what player changed
receipt language is story-native
next hook is clear
```

## Sprint 11 — Hands-Free Readiness Pass

Build fields and prompts for:

```text
audio word budgets
spoken choice text
interrupt safe points
repeatable state summaries
confirmation phrases
```

Acceptance:

```text
standard cut can be rendered as audio without rewriting the whole system
```

---

## 30. QA Plan

## 30.1 Canary order

Use:

```text
Anima Machina Session 1
Anima Machina Session 2
Anima Machina Session 5
Oz Session 1
Sherlock Session 1
```

Why Anima first:

```text
strong source voice
strong object anchors
clear branch dimensions
high risk of exposition overload
sci-fi world requires onboarding through action
```

## 30.2 Metrics

Track:

```text
words before first input
average words per narrator response
player inputs per 20 minutes
moments completed per session
branch hooks surfaced
NPC/object/world reactions
prompt size before/after Boiler
canon violations
voice drift incidents
user desire to continue
user desire to replay
```

## 30.3 Manual QA questions

```text
Did I act quickly?
Did the world react?
Did the scene move?
Did my choices form a version of the protagonist?
Did I understand enough without lore dumps?
Did it feel authored?
Did it feel playable?
Would I replay?
Would I continue?
```

## 30.4 Failure signs

Boiler failed if:

```text
it reads like a summary
it preserves too many events as PLAY
it cuts emotional setup and loses stakes
choices feel random or decorative
runtime still walks the old event range
session receipt feels generic
voice weakens because source texture was removed
```

---

## 31. Risk Register

## Risk 1 — Boiler becomes a summary engine

Mitigation:

```text
require live_pressure
require player_affordances
require exit_condition
require object_anchors
require consequence hooks
reject summary-only moment cards
```

## Risk 2 — Boiler cuts too much

Mitigation:

```text
event role coverage
audit major turning points
must_reintroduce_later routes
EditorialVerification fail gates
```

## Risk 3 — Downstream phases ignore BoilerDraft

Mitigation:

```text
make moment_id required in Phase 4/5/6 outputs
fail if choices are not bound to moments
fail if consequence hooks lack moment IDs
```

## Risk 4 — Runtime ignores BoilerFinalCut

Mitigation:

```text
Boiler mode prompt declares current moment as active structure
remove full event range from active runtime context
PlayableMomentResolver tracks current moment
structured scene progress metadata
```

## Risk 5 — Voice weakens

Mitigation:

```text
keep V2.3 voice anchor/card/self-check
include cool_extracts and voice_surface_notes
include selected source texture, not full source bulk
QA voice drift separately
```

## Risk 6 — Engineering complexity

Mitigation:

```text
build through canaries first
feature flag Boiler mode
keep legacy event mode
normalize final cuts only after draft validation
support JSON artifact inspection
```

---

## 32. Acceptance Criteria for Full R&D Prototype

Boiler is working when:

```text
1. A session plays through playable moments, not raw event walking.
2. First player input happens quickly.
3. Runtime responses are shorter by default but can expand when needed.
4. Choices are attached to identity pressure, not only plot navigation.
5. Freeform input maps cleanly to moment affordances.
6. NPC/object/world reactions surface player posture.
7. Session receipt makes replay visible.
8. Canon remains intact.
9. Voice remains author-specific.
10. Runtime prompt is smaller and cleaner.
11. Hands-free fields can be used without redesign.
12. QA players want to continue to the next session.
```

The ultimate subjective test:

> Does the player feel like they are driving the story forward inside the author’s world?

If yes, Boiler is doing its job.

---

## 33. Final Architecture Statement

Boiler is the missing game-feel layer.

```text
Canon Layer
chapters → events
source truth

Adaptation Layer
sessions → arcs → branch dimensions
story design

Boiler Layer
playable moments → cuts → pressure → pacing → affordances
game feel

Runtime Layer
current moment performance → player action → consequence surface
live play
```

Without Boiler, runtime has to turn script into play live.

With Boiler, runtime performs a designed playable cut.

That is the product leap.

Not shorter narration.

Playable storytelling.
