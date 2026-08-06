# LoreSpinner — The Boiler
## Documentation v1.0

**Audience:** Everyone — product, marketing, writers, narrative design, engineering, ops  
**Status:** Implemented and canary-adapted (Speckled Band)  
**Version:** 1.0 — single `standard` cut, adaptation-time toggle, moment-based runtime  
**Last updated:** 2026-07-23

### Related docs

| Doc | Use when |
|---|---|
| This file | Understanding Boiler end-to-end |
| `BOILER_IMPLEMENTATION_PROCESS_LOG.md` | Deep engineering process / wiring detail |
| `BOILER_FULL_SCOPE_LOG.md` | Rollback anchors and commit ledger |
| `Boiler Run/BOILER_VALIDATION_RUNBOOK.md` | Deploy, migrate, canary verification steps |
| `RnD Ideas/Boiler/lorespinner_the_boiler_full_scope_implementation_plan.md` | Original product R&D plan |

### Canary package (worked example)

```text
Adaptation layer/Boiler Adaptation/Boiled Speckeld band /
```

Story: **Sherlock Holmes in The Speckled Band**  
Slug: `the-adventure-of-the-speckled-band`

---

# Part A — For everyone

## A1. What Boiler is

Boiler is LoreSpinner’s **playable-cut compiler**.

It sits between the adapted source ledger and live Chaos narration.

```text
Source / events          = the canon ledger
Session map              = episode boundaries
The Boiler               = the playable cut of that episode
Chaos runtime            = the performer of the current playable moment
```

Boiler is **not**:

- a second adaptation system
- a summarizer
- “just shorter word counts”
- a live gameplay mode toggle
- a replacement for A/B/C choice architecture

Boiler **is**:

- a pacing layer inside the one existing adaptation pipeline
- a way to turn source events into ordered **playable moments** (rooms)
- a compiler that decides what is played, compressed, echoed, or cut
- a runtime contract that injects only the active room into the narrator

### One-sentence product claim

> LoreSpinner does not narrate the script. It boils the script into the moments where the player most wants to act, compresses everything else into cinematic glue, and tracks what kind of protagonist the player becomes.

---

## A2. Why Boiler exists

### The problem

Legacy runtime was too event-centered:

```text
event range → narrate event → choose → next event
```

That protects canon, but it can feel like walking a script:

- too much waiting before agency
- choices that exist because the system needs a button, not because the moment demands action
- long source ranges that push the model to summarize or over-narrate
- weak sense of “I am inside a room with pressure,” replaced by “I am listening to more prose”
- harder future for hands-free / multiplayer because the runtime unit is too large and literary

### The fix

Create a new runtime unit:

> **Playable Moment** — a designed unit of pressure, agency, state change, and exit condition.

The player inhabits a room for several turns. The room ends when its dramatic state changes.

---

## A3. What players should feel

A good Boiler session should feel like a premium 20–60 minute episode:

```text
I entered the strongest parts quickly.
I got to act before I got bored.
The world reacted to my instinct.
My choices formed a version of the protagonist.
The story stayed coherent.
I want to see what my path causes next.
```

### Player-facing differences vs legacy

| | Legacy | Boiler |
|---|---|---|
| What the narrator “sees” | Full session script block | Compact moment map + **active room only** |
| Progress unit | Event / script flow | Ordered playable moments |
| Visible options | Legacy choice strings + universal Continue | **0–3 contextual suggestions** + freeform always |
| Continue | Always available | Only if the narrator offers a suggestion literally named `Continue` |
| Lingering | Easy to feel railroaded forward | Designed to explore a room until the dramatic exit fires |
| Canon branches | A/B/C under the hood | Same A/B/C under the hood — suggestions may map to them |

Freeform input remains first-class in both modes.

---

## A4. Elevator pitches by role

### Marketing / product

Boiler makes LoreSpinner play like a **cut film**, not an audiobook of the screenplay. Sessions land faster on the scenes that matter, let players explore under pressure, and still keep literary voice and branching consequence.

### Writers / narrative design

Boiler is an editorial cut of the source into playable rooms. You still own voice, choices, consequences, and close. Boiler decides which events breathe as rooms, which compress into montage/echo, and what each room’s dramatic exit is. It does not invent a new branching language.

### Engineers

Boiler is a conditional branch of the adaptation session chain (`BoilerDraftJob` → Phase 4–7 moment bindings → `BoilerFinalizeJob`), plus normalized cut/moment tables, a compact runtime prompt branch, active-moment injection, and atomic moment progression in `PlayableMomentResolver`.

---

## A5. Core vocabulary

| Term | Plain meaning |
|---|---|
| **Canon ledger** | Source events / adapted text that must remain accountable |
| **Boiling profile** | Story-wide editorial diagnosis: how hard to compact, per-session minutes, moment-count ranges, must-breathe / must-compress events |
| **Event role** | Transparent job for every assigned event: PLAY, PULSE, CHOICE, REACTION, MONTAGE, ECHO, CUT |
| **Playable moment / room** | One ordered dramatic unit the player can inhabit for multiple turns |
| **Scene card** | The canonical brief for a room: goal, pressure, affordances, extracts, exit, budgets, choice IDs |
| **Standard cut** | v1’s only cut type — one finalized playable cut per session |
| **Manifest** | Lightweight Finalize summary (keys + coverage). Full cards live on moments |
| **Visible suggestion** | Soft UI hint (0–3). May or may not map to a canonical choice/path |
| **Canonical choice** | Existing four branching choices with A/B/C paths — unchanged topology |
| **Exit condition** | Dramatic outcome that ends the room (not a command checklist) |
| **Moment progress** | Narrator judgment: is the active room’s exit met? |
| **Footnote / critique** | Hidden local note about player intent, injected next turn, never shown to the player |
| **Active injection** | Request-time block that inserts only the current room into the cached prompt |

### Event roles (creative meaning)

| Role | Intent |
|---|---|
| **PLAY** | Full room — inspect, talk, act, explore |
| **PULSE** | Short high-pressure beat that still deserves embodiment |
| **CHOICE** | Load-bearing identity / branch posture moment |
| **REACTION** | World or NPC reacts to what just changed |
| **MONTAGE** | Compressed connective tissue; keep the feeling, lose the page count |
| **ECHO** | Earlier material reappears as texture or consequence, not a new room |
| **CUT** | Not staged as play; must still be accounted for, often with reintroduction notes |

---

# Part B — Product & creative contract

## B1. Product rules (non-negotiable in v1)

1. **One adaptation-time toggle** — `story_adaptations.boiler_enabled`  
   No live mid-game switch. Changing modes requires full re-adaptation.
2. **One runtime prompt per session** — still `session_adaptations.runtime_narrator_prompt`  
   Boiler changes what is assembled into that prompt, not the column count.
3. **One canonical branch topology** — four branching choices, A/B/C paths  
   Suggestions are presentation, not a new branch language.
4. **One standard cut** — no quick/deep/multi-cut variants yet.
5. **Moment as room** — players can inspect, talk, act, explore; exit is dramatic state change.
6. **Every assigned event remains visible** in the role map — including pre-entry-point material.
7. **Draft decides moment count and clustering** — later phases bind to Draft; they do not redesign the cut.
8. **Runtime never invents the cut live** — narrator performs the active room; PHP validates structure and progression.

---

## B2. What writers still own

Boiler does **not** steal ownership of:

- voice / Voice Lock constraints
- Entry Point diagnosis
- Session Architecture beat craft (once bound to moments)
- Choice Design (options, defining lines, StoryGuard manifests)
- Consequence Mapping (paths, state deltas, narrator hooks)
- Session Close (resolution, hook, final line, stickiness)

Boiler **adds** ownership of:

- story-wide boiling diagnosis
- event-by-event playability critique
- moment clustering and semantic exits
- compilation of canonical scene cards
- runtime moment-map + active-room injection contract

---

## B3. Semantic exits (creative standard)

**Wrong (checklist):**

> The player takes, hides, rejects, or abandons the shard.

**Right (dramatic outcome):**

> The room ends once the player meaningfully commits toward the shard, or changes the situation enough to make that commitment obsolete.

Affordances are possible routes. They are not an exhaustive command list. PHP never string-matches player prose to exit wording. The narrator judges completion; PHP validates that the judgment is structurally sane and advances by order.

---

## B4. Playable Promise arc

Every drafted session names three anchors (session-local keys):

```text
first_proof_moment
midpoint_intensification
final_cash_out
```

Rules:

- Short sessions may reuse one moment for multiple anchors
- `final_cash_out` must be the final ordered moment
- Session Close binds to that final moment

This is how Boiler keeps a session from being “a pile of rooms” and makes it feel like an episode with a shape.

---

## B5. Suggestions vs canonical choices

```text
Canonical design (adaptation-time)
→ four branching choices
→ three A/B/C paths each
→ consequences / alignment / defining lines

Live turn (runtime)
→ narrator may emit 0–3 visible_suggestions
→ each may optionally map to choice_id + path A/B/C
→ freeform always available
→ mapped clicks are validated server-side against stored suggestion + active moment
```

Players never need to understand “canonical.” They feel local, contextual options. Under the hood, mapped clicks still feed the existing branch resolver.

---

## B6. What v1 deliberately does not include

- Quick cut / deep cut / simultaneous cuts
- Multiple simultaneous runtime prompts
- Focused retrofit of old Phase 3–7 into Boiler without full re-adaptation
- Arbitrary moment graphs / child-room graphs / cross-session moment graphs
- Forced advancement by turn count alone
- Creator compaction slider
- Hands-free / multiplayer contracts
- Session receipts / advanced replay analytics
- Variable A/B/C option counts

If someone asks “can Boiler do X?” and X is on this list: **not in v1**.

---

# Part C — How adaptation works with Boiler on

## C1. High-level flow

```text
Creator enables Boiler and runs adaptation
        ↓
Story-wide phases run as usual
(IP trim → format → audit → voice → Story Session Map)
        ↓
Story Session Map also emits boiling_profile
        ↓
For each session (parallel chains, ordered jobs inside):
  Entry Point Diagnosis
  → Boiler Draft
  → Session Architecture   (+ moment bindings)
  → Choice Design          (+ moment bindings)
  → Consequence Mapping    (+ moment bindings)
  → Session Close          (+ final-moment binding)
  → Boiler Finalize        (canonical cards + DB cut)
  → Editorial Verification (+ boiler_audit)
  → Runtime Narrator Assembly (compact prompt)
        ↓
Reconciliation validates all sessions
→ story adaptation completed
→ games reset once for the new adaptation
```

When Boiler is **off**, Draft and Finalize are omitted, boiling_profile is omitted, Phase 4–7 keep legacy schemas, and Runtime Assembly builds the legacy full-session Section 12.

---

## C2. How to enable Boiler

### Filament (Creator)

Enable Boiler when creating/dispatching adaptation on the story. The value is persisted on the adaptation row.

### CLI

```bash
php artisan stories:run-adaptation {story-id-or-slug} --force --boiler
```

Example canary:

```bash
php artisan stories:run-adaptation the-adventure-of-the-speckled-band --force --boiler
```

### Persistence

```text
story_adaptations.boiler_enabled = true | false   (default false)
```

There is no gameplay UI to flip this mid-run.

---

## C3. Story-wide boiling guidance

Produced by the existing Story Session Map agent when Boiler is enabled. No extra story-wide AI call.

Shape:

```json
{
  "boiling_profile": {
    "compaction_level": "light | balanced | strong",
    "target_total_playtime_minutes": 110,
    "diagnosis": "Evidence-based editorial diagnosis.",
    "per_session_guidance": [
      {
        "session_number": 1,
        "target_playtime_minutes": 22,
        "recommended_moment_count_min": 5,
        "recommended_moment_count_max": 7,
        "must_compress_event_ids": [],
        "must_breathe_event_ids": [],
        "rationale": "Session-specific pacing reasoning."
      }
    ]
  }
}
```

The agent judges source format, action vs exposition density, mystery dependencies, choice density, repetition, canon/voice fragility, and which scenes need room (suspense, intimacy, revelation, confrontation, climax). It is forbidden from using a hardcoded genre/page-count formula.

PHP fails Story Session Map before session chains dispatch if guidance is structurally invalid (missing sessions, bad ranges, unknown event IDs, playtime reconciliation failure, etc.).

---

## C4. Session chain (canonical)

Defined only in:

```text
app/Support/Adaptation/SessionAdaptationChain.php
```

```text
EntryPointDiagnosisJob
→ BoilerDraftJob                 [Boiler only]
→ SessionArchitectureJob
→ ChoiceDesignJob
→ ConsequenceMappingJob
→ SessionCloseJob
→ BoilerFinalizeJob              [Boiler only]
→ EditorialVerificationJob
→ RuntimeNarratorAssemblyJob
```

Ownership pattern: jobs receive IDs, reload prior artifacts, write only their owned output. Large AI payloads are not passed through queue constructors.

---

## C5. Boiler Draft (creative compiler — pass 1)

**Model:** Claude Opus (high-judgment tier)  
**Job:** `BoilerDraftJob`  
**Status path:** `pending → drafting → drafted` (or `failed`)

### Inputs

- session allocation + boiling guidance for this session
- Entry Point diagnosis
- format / audit / StoryGuard / voice slice
- only events assigned to this session (shared `SessionEventLoader`)
- source budget: 60k chars session / 8k per event extract ceiling

### Outputs (persisted on `session_adaptations.boiler_draft`)

- playable promise
- session budget decision (exact moment count inside Map range)
- playable promise arc
- event role map (one role + why per assigned event)
- moment drafts (ordered rooms)
- coverage check
- optional cut material notes / reintroduction needs

### Each moment draft includes

```text
moment_key
moment_order
source_event_ids
anchor_event_id
treatment
runtime_goal
player_desire
live_pressure
player_affordances
canon_must_keep
allowed_variance
forbidden_expansions
source_extracts
exit_condition_draft
```

### Hard Draft rules

- First moment must contain the Entry Point start event
- Every assigned event appears exactly once in the role map
- Source events cannot belong to two moments
- Target moment count must equal drafted count and sit inside Map min/max
- Must-breathe MONTAGE/CUT events need reintroduction notes
- Final cash-out is last

Validation is owned by `BoilerDraftValidator`. Invalid drafts fail visibly; they do not silently continue.

---

## C6. Phase 4–7 with moment bindings

Existing agents keep their jobs. When Boiler is on, their schemas require Boiler fields. When Boiler is off, those fields are absent (strict schemas, no JSON Schema conditionals).

### Session Architecture

Adds:

```text
beat_map[].moment_key
boiler_budget_alignment.aligned
boiler_budget_alignment.conflict
```

May not split/merge/add/delete moments. Unresolved budget conflict fails visibly.

### Choice Design

Adds `moment_key` on branching choices, emotional choices, posture shifts. Preserves four canonical branches, A/B/C labels, defining lines, freeform alignment maps.

### Consequence Mapping

Adds for branching/emotional consequences:

```text
moment_key
echo_moment_key
moment_narrator_hook
```

Immediate moment must match the authored choice moment.

### Session Close

Adds:

```text
closing_moment_key
closing_moment_relationship
next_bridge_guidance
```

Close must bind to the final ordered Draft moment; trigger event must belong to that moment.

---

## C7. Boiler Finalize (compiler — pass 2)

**Model:** Claude Opus  
**Job:** `BoilerFinalizeJob`  
**Status path:** `drafted → finalizing → finalized` (or `failed`)

Finalize **compiles**; it does not redesign.

It preserves Draft keys/order/source clusters/anchors/roles and Phase 4–7 choice/consequence/close authority.

### Canonical scene card fields

```text
moment_key, moment_order, title
source_event_ids, anchor_event_id
treatment, runtime_goal, player_desire, live_pressure
opening_image
player_affordances
canon_must_keep, allowed_variance, forbidden_expansions
source_extracts
choice_ids
word_budget.target_per_turn
turn_budget.target_turns / gravity_after
  (invariant: gravity_after >= target_turns; gravity is the soft push *after* the target)
exit_condition
next_moment_key (empty on final; not used for routing)
next_bridge
```

### Database write (transactional)

`BoilerCutNormalizer`:

```text
lock session adaptation
→ delete prior cut for that session
→ create one finalized standard cut
→ create ordered playable_moments (full scene_card JSON)
→ create event_playability_roles
→ write manifest-only boiler_final_cut
→ set boiler_status=finalized
```

**Important:** complete cards live in `playable_moments.scene_card`.  
`boiler_final_cut` is references/summaries only (the Editorial prompt variable is `boilerManifest` for that reason).

Runtime progression authority is relational `moment_order + 1`, never `next_moment_key` lookup.

---

## C8. Editorial Verification

Existing 23-question editorial audit remains.

Boiler adds `boiler_audit` with ten checks:

1. Event coverage  
2. Canon reintroduction  
3. Moment alignment  
4. Canonical choice bindings  
5. Consequence and close bindings  
6. Playable-room quality *(advisory craft — does not hard-fail the session)*  
7. Pacing and budget agreement  
8. Playable Promise arc  
9. Semantic exit conditions  
10. Immersion / consequence / momentum balance *(advisory craft — does not hard-fail the session)*  

`boiler_audit.production_status` from the model may still say REVISE when advisory items fail. The job only hard-fails on **blocking** checks (1–5, 7–9). Advisory REVISE items are logged as `boiler.editorial_verification_advisory`.

One automatic Editorial re-prompt is allowed; the second pass receives the first pass's revision focus.

**Critical team distinction:**

```text
boiler_audit PASS
≠ overall editorial final_verdict PASS
```

Overall editorial can still be AMBER/RED for voice hard-bans, alignment labeling, state naming, world-noticed signals, etc. Those are adjacent pipeline issues. Boiler can be structurally healthy while editorial still wants revisions.

Editorial no longer marks the session playable. Runtime Assembly owns completion.

---

## C9. Runtime Assembly

Status flow:

```text
Editorial succeeds → session stays editorial-verification
Runtime Assembly begins → runtime-assembly
Prompt build + validate + persist succeeds → completed
Retries exhausted → failed
```

Boiler prompt branch includes:

```text
[BOILER_RUNTIME_V1]
SECTION 12: BOILER PLAYABLE SESSION MAP
→ Playable Promise
→ ordered moment key/title/treatment/goal/pressure/canon/budgets/choice IDs
→ [ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]
BOILER MOMENT_PROGRESS CONTRACT
BOILER PLAYER-CRITIQUE FOOTNOTE
BOILER VISIBLE_SUGGESTIONS CONTRACT
```

Legacy full `SECTION 12: FULL CURRENT EPISODE SCRIPT` must be absent in Boiler mode.

Shared validator: `RuntimeNarratorPromptValidator` (also used by reconciliation).

---

# Part D — How gameplay works with Boiler on

## D1. Begin session

`GameController::begin()` / `nextSession()`:

```text
load finalized standard cut
→ set current cut + first ordered moment
→ playable_moment_turn_count = 0
→ clear moment_footnote
→ load runtime context with active game
→ inject active room
→ opening narration
→ require exit_condition_met = false
→ require no footnote yet
→ validate opening suggestions
→ create first unanswered prompt
```

Opening does not increment turn count and does not advance.

## D2. Player turn

`PromptController::store()`:

1. Frontend sends `prompt_id` of the unanswered prompt (plus optional suggestion identity)
2. Server acquires cache lock `game-turn:{game}:{prompt}` (TTL 120s)
3. Claims source prompt (must be unanswered + latest)
4. Loads active moment context + prior matching footnote
5. Calls narrator
6. Normalizes suggestions
7. `PlayableMomentResolver` commits everything atomically

### Three duplicate-turn protections

1. Exact unanswered `prompt_id` from client  
2. Bounded cache lock  
3. Durable DB unique `(game_id, source_prompt_id)` on response prompts  

Crash before successful narration leaves the source unanswered so the turn can be safely retried after lock expiry.

## D3. What the narrator returns (Boiler fields)

```json
{
  "visible_suggestions": [
    {
      "text": "Hide the shard.",
      "canonical_choice_id": "S1_C1",
      "canonical_path_label": "B"
    },
    {
      "text": "Inspect the residue.",
      "canonical_choice_id": null,
      "canonical_path_label": null
    }
  ],
  "moment_progress": {
    "moment_key": "S1_M01",
    "exit_condition_met": false,
    "moment_footnote": "The player seeks proof, not delay; answer with revealing resistance, then return the decision pressure."
  }
}
```

When an active moment exists, runtime requires:

- `visible_suggestions` present (may be empty array)
- no simultaneous legacy `choices`
- 0–3 suggestions
- `moment_progress` present with matching moment key and boolean exit
- player turns require a non-empty footnote
- openings require false exit and null/omitted footnote

## D4. Active-moment injection contents

At request time, Chaos injects:

- full active `scene_card`
- soft turn count
- matching hidden treasure / critique insight
- bounded next-moment preview (key, title, goal, opening image, pressure, affordances, choice IDs, essential canon, bridge)

Preview is ignored while current exit remains false.

World-state filtering scopes to active moment source event IDs when a moment is present. Conversation history narrows using active moment turn count (legacy keeps the older twelve-message window).

## D5. Progression rules

```text
exit false → retain moment, increment turn count, replace footnote
exit true  → advance to moment_order + 1, reset turn count, carry footnote once if sequential
exit true on final moment → session complete
```

Boiler ignores model `session_complete` as technical authority. Only final-room exit completes the session.

Pointers stay set on the completion screen so the frontend remains in Boiler interaction mode until next session / reset.

## D6. Frontend behavior

Discriminator:

```json
{ "uses_playable_moments": true }
```

Computed from non-null `current_playable_moment_id`.

| | Legacy card | Boiler card |
|---|---|---|
| Options | String choices | Exactly the stored 0–3 suggestions |
| Continue | Universal Continue | None unless suggestion text is `Continue` |
| Freeform | Yes | Yes |
| Submit payload | prompt text | prompt + `prompt_id` + optional `suggestion_index` + canonical identity |

No broad visual redesign shipped with v1 — interaction contract changed, not brand UI.

## D7. Reset / re-adaptation

Shared `StoryGameResetService` clears:

- prompts, world state, memory, alignment, branch identity
- cut/moment pointers, turn count, footnote
- session completion / completed_at
- increments story cycle

Forced Boiler re-adaptation deletes current session adaptations; cuts/moments cascade; game pointers null via `nullOnDelete`; pipeline rebuilds; reconciliation resets games once on successful completion.

Failed re-adaptation does not mark complete and does not run the successful reset path.

---

# Part E — Engineering reference

## E1. Key files

### Pipeline / jobs

```text
app/Support/Adaptation/SessionAdaptationChain.php
app/Jobs/Adaptation/StorySessionMapJob.php
app/Jobs/Adaptation/BoilerDraftJob.php
app/Jobs/Adaptation/BoilerFinalizeJob.php
app/Jobs/Adaptation/SessionArchitectureJob.php
app/Jobs/Adaptation/ChoiceDesignJob.php
app/Jobs/Adaptation/ConsequenceMappingJob.php
app/Jobs/Adaptation/SessionCloseJob.php
app/Jobs/Adaptation/EditorialVerificationJob.php
app/Jobs/Adaptation/RuntimeNarratorAssemblyJob.php
app/Jobs/Adaptation/AdaptationStatusReconciliationJob.php
```

### Agents / validators / builders

```text
app/Ai/Agents/Adaptation/BoilerDraftAgent.php
app/Ai/Agents/Adaptation/BoilerFinalizeAgent.php
app/Ai/Adaptation/BoilerDraftContextBuilder.php
app/Ai/Adaptation/BoilerDraftValidator.php
app/Ai/Adaptation/BoilerFinalizeContextBuilder.php
app/Ai/Adaptation/BoilerFinalCutValidator.php
app/Ai/Adaptation/RuntimeNarratorTemplateBuilder.php
app/Ai/Adaptation/RuntimeNarratorPromptValidator.php
app/Services/Adaptation/BoilerCutNormalizer.php
app/Services/Adaptation/SessionEventLoader.php
```

### Runtime

```text
app/Services/ChaosEngineService.php
app/Services/Runtime/PlayableMomentResolver.php
app/Services/Runtime/NarrationResultNormalizer.php
app/Ai/Agents/Chaos/ChaosNarrationSchema.php
app/Http/Controllers/... PromptController / GameController
app/Http/Resources/GameResource.php
app/Http/Resources/PromptResource.php
```

### Frontend

```text
resources/js/types/index.d.ts
resources/js/components/GameplayChatCard.vue
resources/js/pages/User/Games/Show.vue
```

### Prompts (Blade)

```text
resources/views/ai/agents/adaptation/story-session-map/*
resources/views/ai/agents/adaptation/boiler-draft/*
resources/views/ai/agents/adaptation/session-architecture/*
resources/views/ai/agents/adaptation/choice-design/*
resources/views/ai/agents/adaptation/consequence-mapping/*
resources/views/ai/agents/adaptation/session-close/*
resources/views/ai/agents/adaptation/boiler-finalize/*
resources/views/ai/agents/adaptation/editorial-verification/*
resources/views/ai/agents/chaos/runtime-narrator-template.blade.php
```

---

## E2. Database

### Adaptation columns

`story_adaptations`

```text
boiler_enabled boolean default false
```

`session_adaptations`

```text
boiler_draft longText nullable
boiler_final_cut longText nullable
boiler_status string default pending
boiler_error text nullable
boiler_generated_at timestamp nullable
boiler_finalized_at timestamp nullable
```

### New tables

**`playable_session_cuts`**

```text
id, story_id, session_adaptation_id (unique), session_number, cut_key, status, timestamps
```

**`playable_moments`**

```text
id, playable_session_cut_id, moment_key, moment_order,
anchor_event_id nullable, source_event_ids JSON, scene_card JSON, timestamps
```

Unique `(cut_id, moment_key)`; indexed `(cut_id, moment_order)`.

**`event_playability_roles`**

```text
id, playable_session_cut_id, event_id, primary_role,
included_moment_key nullable, why, reintroduction_note nullable, timestamps
```

Unique `(cut_id, event_id)`.

### Game runtime pointers

`games`

```text
current_playable_session_cut_id nullable
current_playable_moment_id nullable
playable_moment_turn_count unsigned int default 0
moment_footnote JSON nullable
```

Pointers use `nullOnDelete`. Deleting a cut clears stale game pointers without deleting the game.

### Prompt idempotency

`prompts.source_prompt_id` + unique `(game_id, source_prompt_id)`.

### Models

```text
PlayableSessionCut, PlayableMoment, EventPlayabilityRole
(+ extensions on StoryAdaptation, SessionAdaptation, Game, Prompt)
```

### Migrations (additive, 2026-07-19)

```text
000001_add_boiler_adaptation_columns
000002_create_playable_session_cuts_table
000003_create_playable_moments_table
000004_create_event_playability_roles_table
000005_add_boiler_runtime_columns_to_games_table
000006_add_source_prompt_id_to_prompts_table
```

---

## E3. Boiler status enum

```text
pending → drafting → drafted → finalizing → finalized
any stage can terminate → failed
```

File: `app/Enums/BoilerStatus.php`

Retry behavior: transient failures keep `drafting`/`finalizing` and rethrow; terminal `failed()` after retries exhausted. Ordinary job entry does not silently reset a terminal failed state.

---

## E4. Persist-and-reload ownership

| Owner | Reads | Writes |
|---|---|---|
| Story Session Map | format, audit, trim, voice, events | `story_session_map` incl. `boiling_profile` |
| Entry Point | session source + story map | `entry_point_diagnosis` |
| Boiler Draft | Entry Point + guidance + events | `boiler_draft` |
| Session Architecture | Draft + existing inputs | `session_architecture` |
| Choice Design | Draft + Architecture | `session_choice_design` |
| Consequence Mapping | Draft + Choice Design | `choice_consequence_map` |
| Session Close | Draft + choices + consequences | `session_close_design` |
| Boiler Finalize | Draft + Phase 4–7 | cut rows + `boiler_final_cut` |
| Editorial | full design + normalized Boiler rows | `editorial_verification` |
| Runtime Assembly | verified artifacts | `runtime_narrator_prompt` + completed status |

---

## E5. Logging (useful for ops)

Adaptation:

```text
boiler.draft_started / draft_completed / draft_attempt_failed
boiler.finalize_started / finalize_completed / finalize_attempt_failed
```

(Plus corrected/validation failure variants in the validation runbook.)

Runtime:

```text
boiler.moment_turn_committed
boiler.explicit_branch_model_mismatch
boiler.moment_footnote_truncated
```

---

## E6. Artisan commands

```bash
# Enable Boiler adaptation
php artisan stories:run-adaptation {story} --force --boiler

# Status overview
php artisan stories:boiler-status {story} --session={n}

# Inspect roles + scene cards
php artisan stories:inspect-boiler {story} --session={n}

# Export adaptation JSON/CSV
php artisan adaptation:export {story}
```

Creator Filament infolist also shows Boiler mode, per-session Boiler status, Draft, and final manifest.

---

## E7. Tests (authored for Boiler)

```text
tests/Unit/BoilerRuntimeContractTest.php
tests/Unit/BoilerArtifactValidatorTest.php
tests/Unit/BoilerWiringContractTest.php
tests/Feature/Boiler/PlayableMomentResolverTest.php
tests/Feature/Boiler/BoilerForeignKeyLifecycleTest.php
tests/Feature/Boiler/StoryGameResetServiceTest.php
```

```bash
php artisan test --filter=Boiler
```

Coverage includes lifecycle, schema conditionality, validators, chain order, FK/nullOnDelete, progression authority, idempotency, reset semantics, frontend discriminator.

---

## E8. Rollback anchors

```text
tag: pre-boiler
SHA: 42d022984bdf77e6080f0fdb63de95ab19db160b
```

Authoritative rollback procedure: `docs/Boiler/BOILER_FULL_SCOPE_LOG.md`  
Principle: `git revert`, never hard-reset shared `main`; roll migrations in reverse FK order; re-adapt affected stories so cached prompts match code.

---

# Part F — Worked example: Speckled Band canary

## F1. Package contents

```text
Adaptation layer/Boiler Adaptation/Boiled Speckeld band /
  full-boiler-output.json
  session-1-boiler-draft.json
  session-1-boiler-final-manifest.json
  session-1-scene-cards.json
  session-1-runtime-prompt.txt
  session-2-boiler-draft.json
  session-2-boiler-final-manifest.json
  session-2-scene-cards.json
  session-2-runtime-prompt.txt
```

| File | Meaning |
|---|---|
| `full-boiler-output.json` | Full export: story-wide + both sessions + editorial + normalized cuts |
| `*-boiler-draft.json` | Draft roles, budgets, moment drafts, promise arc |
| `*-boiler-final-manifest.json` | Finalize summary (keys, coverage, promise) |
| `*-scene-cards.json` | Canonical room cards (best creative read) |
| `*-runtime-prompt.txt` | Cached narrator prompt for that session |

## F2. Headline results

| | Session 1 | Session 2 |
|---|---|---|
| Target playtime | 25 min | 23 min |
| Map moment range | 11–15 | 9–12 |
| Moments finalized | 12 | 10 |
| Events covered | 21/21 | 11/11 |
| Cut | `standard` / finalized | `standard` / finalized |
| `boiler_audit` | PASS (10/10) | PASS (10/10) |
| Overall editorial | AMBER | RED |
| Runtime prompt | ~109.5k chars, Boiler markers OK | ~105.2k chars, Boiler markers OK |

Story-wide:

```text
compaction_level: balanced
target_total_playtime_minutes: 48
```

Diagnosis in plain terms: short clue-dense Doyle; preserve forensic objects; let the nocturnal vigil breathe; Session 1 brisk through testimony then linger on room inspection; Session 2 fewer events but slower felt time across wait → strike → reveal.

## F3. Session 1 spine

Promise: enter a real forensic case at Watson’s side — testimony, menace, sealed murder-room inspection before night.

| Key | Title (final) | Notes |
|---|---|---|
| S1_M01 | Cold-open bedroom threshold | Entry / C1 |
| S1_M02 | Helen in the sitting-room | First proof |
| S1_M03 | Stoke Moran / Roylott testimony | Choice-shaped probes |
| S1_M04 | Julia’s death night | Must-breathe |
| S1_M05 | Bruises / same-day commitment | |
| S1_M06 | The Bent Poker | Midpoint intensification |
| S1_M07 | Motive + arming | C2 |
| S1_M08 | Travel → Stoke Moran | Compressed travel |
| S1_M09 | The Barred Shutters | Forensic exterior |
| S1_M10 | The Dummy Bell-Rope | Central clue room |
| S1_M11 | Roylott’s Chamber | Object inventory |
| S1_M12 | The Signal in the Dark | Cash-out / close / C3+C4 |

Promise arc: `S1_M02` → `S1_M06` → `S1_M12`

Role mix (Draft): PLAY 10 · PULSE 5 · CHOICE 4 · MONTAGE 2

## F4. Session 2 spine

Promise: live night operation — dark grounds, silent vigil, Holmes’s strike, speckled-band reveal.

| Key | Title (final) | Notes |
|---|---|---|
| S2_M01 | The Signal in the Middle Window | Entry / first proof / C1 |
| S2_M02 | The Grounds and the Baboon | |
| S2_M03 | Staging the Trap | C2 |
| S2_M04 | The Dreadful Vigil | Must-breathe silence |
| S2_M05 | The Gleam at the Ventilator | Midpoint |
| S2_M06 | The Strike and the Cry | Climax pulse |
| S2_M07 | Into Roylott’s Room | C3 |
| S2_M08 | The Speckled Band | Reveal |
| S2_M09 | The Swamp Adder Contained | |
| S2_M10 | The Debrief and the Verdict | Cash-out / close / C4 |

Promise arc: `S2_M01` → `S2_M05` → `S2_M10`

## F5. How to read the canary (any role)

**Writers / narrative**

1. Read both manifests for promise + moment count  
2. Read scene cards room-by-room for goal / pressure / affordances / exit / extracts  
3. Open drafts only when a room feels wrong — check role `why` and treatment  
4. Check editorial `boiler_audit` details, then separately note overall AMBER/RED voice/state notes  

**Product / marketing**

1. Read Part A of this doc  
2. Skim Session 1 + 2 spines above  
3. Play if a live Boiler game is available — judge “room feel,” suggestion quality, earned exits  

**Engineers**

1. Confirm markers in runtime prompts (`[BOILER_RUNTIME_V1]`, active injection token, no legacy full Section 12)  
2. Confirm normalized cut in `full-boiler-output.json` (`cut_key=standard`, moments, roles)  
3. Trace one choice ID from Choice Design → scene card `choice_ids` → consequence moment binding  

---

# Part G — Comparison: Boiler off vs on

## G1. Adaptation

| Concern | Off | On |
|---|---|---|
| Story Session Map | Allocations only | Allocations + `boiling_profile` |
| Session chain | No Draft/Finalize | Draft + Finalize inserted |
| Phase 4–7 schemas | Legacy fields only | + moment bindings required |
| Editorial | Classic audit | Classic + `boiler_audit` |
| Runtime prompt | Full episode script Section 12 | Compact moment map + injection token |
| Completion owner | Runtime Assembly | Runtime Assembly (same; Editorial does not complete) |

## G2. Gameplay

| Concern | Off | On |
|---|---|---|
| Game pointers | No playable moment IDs | Current cut + moment + turn count + footnote |
| Narration schema | Legacy `choices` | `visible_suggestions` + `moment_progress` |
| UI Continue | Universal | Not universal |
| History window | Legacy 12-message window | Narrowed by moment turn count |
| Session complete | Existing semantics | Final ordered room exit only |

---

# Part H — Team FAQ

### Is Boiler a new product mode players pick?

No. It is an adaptation-time compilation mode. Players experience the resulting game feel. They do not toggle “Boiler.”

### Does Boiler replace Chaos?

No. Chaos still narrates. Boiler decides the cut Chaos performs.

### Does Boiler replace Choice Design?

No. Four canonical A/B/C branches remain. Boiler binds them to rooms and softens presentation via suggestions.

### Will enabling Boiler make every story shorter?

Not necessarily. It redistributes time: more breathing room on must-breathe scenes, more compression on connective tissue. Total targets come from boiling guidance.

### Can we retrofit an old adaptation without re-running everything?

Not in v1. Full re-adaptation with `--boiler` is required.

### Why did Speckled Band get AMBER/RED if Boiler passed?

Because overall editorial still scored voice, alignment labeling, state keys, etc. Boiler’s ten-check audit passed independently. Treat those as separate queues of work.

### Who decides when a room ends — PHP or the model?

The model judges semantic exit. PHP validates structure/identity and advances only to `moment_order + 1` when exit is true. PHP never parses exit text against player prose.

### Where do complete scene cards live?

In `playable_moments.scene_card`. Not duplicated into `boiler_final_cut`.

### What should marketing promise today?

A faster path into the scenes that matter; rooms you can explore under pressure; contextual suggestions; same literary branching soul. Do not promise quick/deep cuts, multiplayer, or mid-run mode switching yet.

---

# Part I — Operational quickstart

## I1. Dispatch canary

```bash
php artisan stories:run-adaptation the-adventure-of-the-speckled-band --force --boiler
php artisan stories:boiler-status the-adventure-of-the-speckled-band
php artisan stories:inspect-boiler the-adventure-of-the-speckled-band --session=1
php artisan adaptation:export the-adventure-of-the-speckled-band
```

## I2. Healthy completion signals

```text
boiler_enabled=true
adaptation_status=completed
each session_status=completed
each boiler_status=finalized
one standard cut per session
moment count matches Draft target
event roles cover all assigned events
runtime prompt contains [BOILER_RUNTIME_V1] exactly once
runtime prompt lacks FULL CURRENT EPISODE SCRIPT section
boiler_audit production_status=PASS
```

## I3. Deploy reminders

- Do not deploy while an adaptation run is active  
- Snapshot DB before Boiler migrations on Cloud  
- `php artisan migrate --force` then `php artisan queue:restart`  
- Full procedure: `docs/Boiler/Boiler Run/BOILER_VALIDATION_RUNBOOK.md`

---

# Part J — End-to-end diagrams

## J1. Enabled-mode walkthrough

```text
Creator enables Boiler
→ RunAdaptationPipelineJob persists boiler_enabled=true
→ story-wide phases
→ StorySessionMapAgent emits allocations + boiling_profile
→ StorySessionMapJob validates guidance
→ session chains dispatch

Per session:
EntryPoint → Draft → Architecture → Choice → Consequence → Close
→ Finalize compiles cards → Normalizer writes cut/moments/roles
→ Editorial (+ boiler_audit) → Runtime Assembly writes compact prompt

All sessions reconcile → game reset once → adaptation completed

Gameplay:
begin initializes first cut/moment
→ opening (false exit, no footnote)
→ player freeform or suggestion click
→ narrator returns prose + suggestions + moment_progress + footnote
→ resolver retains or advances atomically
→ final room exits → session complete
→ nextSession initializes next cut/moment
```

## J2. Disabled-mode walkthrough

```text
boiler_enabled=false
→ no boiling_profile
→ no Draft/Finalize
→ Phase 4–7 legacy schemas
→ Editorial classic schema
→ Runtime Assembly builds legacy Section 12
→ no playable-moment pointers
→ legacy choices + Continue UI
→ source_prompt_id still recorded on responses
```

---

# Part K — Glossary (complete)

| Term | Definition |
|---|---|
| Active moment | The playable moment currently pointed to by the game |
| Affordance | A possible player route inside a room (inspect/talk/act/explore cue) |
| Anchor event | Primary source event a moment is staged around |
| Boiler | Playable-cut compiler + runtime moment contract |
| Boiling profile | Story-wide pacing diagnosis and per-session guidance |
| Canon must-keep | Facts/images that must survive in the room |
| Compact map | Boiler Section 12 — ordered moment summaries, not full script |
| Cut | A finalized playable compilation of a session (v1: `standard` only) |
| Draft | First Boiler AI pass: roles + moment clustering |
| Echo | Compressed recurrence of earlier material |
| Exit condition | Dramatic outcome ending a room |
| Finalize | Second Boiler AI pass: compile canonical scene cards |
| Footnote | Hidden player-critique note for next-turn injection |
| Gravity after | Soft pacing cue: after N turns, increase pressure if still in room |
| Manifest | Lightweight Finalize summary artifact |
| Moment key | Stable ID like `S1_M04` |
| Moment order | Integer sequence; sole progression authority |
| Montage | Compressed connective staging |
| Must-breathe | Events guidance says must not be over-compressed |
| Must-compress | Events guidance says should not own full rooms |
| Normalized cut | DB rows for cut + moments + roles after Finalize |
| Playable promise | Session-level experiential promise statement |
| Playable Promise arc | First proof / midpoint / cash-out anchors |
| Pulse | Short embodied pressure beat |
| Scene card | Full runtime brief for a moment |
| Semantic exit | Model-judged dramatic completion |
| Source extracts | Bounded author-texture snippets attached to a room |
| Standard cut | Only cut_key in v1 |
| Suggestion | Visible 0–3 contextual option object |
| Turn budget | Soft target turns for a room before gravity |
| Visible suggestions contract | Runtime rules for emitting/normalizing suggestions |
| Word budget | Soft target words per narration turn in a room |

---

# Part L — Document ownership

| Need | Go here |
|---|---|
| Understand Boiler | **This document (v1.0)** |
| Implementation wiring minutiae | `BOILER_IMPLEMENTATION_PROCESS_LOG.md` |
| Rollback / commit ledger | `BOILER_FULL_SCOPE_LOG.md` |
| Cloud validation checklist | `Boiler Run/BOILER_VALIDATION_RUNBOOK.md` |
| Original R&D intent | `RnD Ideas/Boiler/lorespinner_the_boiler_full_scope_implementation_plan.md` |
| Speckled Band artifacts | `Adaptation layer/Boiler Adaptation/Boiled Speckeld band /` |

---

## Version history

| Version | Date | Notes |
|---|---|---|
| 1.0 | 2026-07-23 | First team-wide Boiler documentation. Covers product contract, creative model, adaptation pipeline, runtime, engineering reference, Speckled Band canary, FAQ, and ops quickstart. |

---

*Boiler v1: one toggle, one cut, one prompt, one room at a time — compiled at adaptation time, performed at runtime.*
