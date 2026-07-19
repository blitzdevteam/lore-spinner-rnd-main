# Boiler Pacing Engine — Full Implementation Process Log

**Implementation date:** 2026-07-19  
**Repository:** LoreSpinner R&D  
**Implementation branch:** `main`  
**Baseline commit:** `42d022984bdf77e6080f0fdb63de95ab19db160b`  
**Rollback tag:** `pre-boiler`  
**Deployment state at handoff:** committed locally, not pushed by the implementing agent  
**Runtime verification state at handoff:** code inspection and IDE diagnostics completed; migrations, tests, builds, queues, Artisan commands, and Laravel Cloud commands were not executed by the implementing agent

---

## 1. Executive summary

The Boiler Pacing Engine was implemented as a codebase-native extension of LoreSpinner's existing adaptation pipeline and Chaos runtime.

Boiler is not a second adaptation system. It is a conditional pacing layer inside the one existing pipeline:

```text
Story-wide adaptation
→ session allocation and boiling guidance
→ Entry Point diagnosis
→ Boiler Draft
→ existing Phase 4-7 design with moment bindings
→ Boiler Finalize
→ expanded Editorial Verification
→ one validated runtime_narrator_prompt
→ playable-moment runtime
```

When Boiler is disabled:

- Boiler-specific jobs are absent from the session chain.
- Boiler-only schema fields are absent from existing phase schemas.
- the existing full-session runtime prompt branch is assembled;
- runtime remains session-based and uses legacy choices;
- mode-independent corrections still apply:
  - Runtime Assembly owns final session completion;
  - runtime prompts receive structural validation;
  - player turns receive server-side duplicate protection;
  - response prompts record `source_prompt_id`;
  - prompt choices pass through one normalizer.

When Boiler is enabled:

- the Story Session Map produces story-wide compaction guidance;
- every assigned event receives one transparent playability role;
- events are clustered into ordered playable moments;
- Phase 4-7 artifacts bind to those moments without replacing their current ownership;
- Finalize creates one canonical `standard` cut;
- the complete final scene card exists only in `playable_moments.scene_card`;
- the cached runtime prompt contains a compact moment map rather than the full session script;
- runtime injects only the active room, local source extracts, state, prior player insight, and a bounded preview of the next ordered room;
- the narration model judges semantic moment completion;
- PHP validates identity, structure, ordering, references, and atomic state transitions;
- the player receives zero to three contextual visible suggestions while canonical A/B/C branches remain unchanged underneath.

Implementation footprint at the time this report was generated:

```text
90 changed or added files
4,717 insertions
461 deletions
```

The deletions in that summary are principally refactoring of duplicated event-loading, session-chain, controller-turn, and legacy prompt assembly code. Canonical story, choice, consequence, and source data were not removed.

---

## 2. Product contract implemented

### 2.1 One adaptation-time toggle

The persistent mode is:

```text
story_adaptations.boiler_enabled
```

It defaults to `false`.

The value is selected at adaptation dispatch time and persisted by:

- `app/Filament/Creator/Resources/Stories/Pages/ViewStory.php`
- `app/Filament/Creator/Resources/Stories/Pages/CreateStory.php`
- `app/Console/Commands/RunAdaptationCommand.php`
- `app/Jobs/Adaptation/RunAdaptationPipelineJob.php`

CLI usage:

```bash
php artisan stories:run-adaptation {story-id-or-slug} --force --boiler
```

There is no live gameplay switch. Changing a story from legacy mode to Boiler mode requires full re-adaptation.

### 2.2 One runtime prompt

Each session still owns exactly one:

```text
session_adaptations.runtime_narrator_prompt
```

Boiler does not add a second prompt column.

The persisted adaptation mode determines which branch `RuntimeNarratorTemplateBuilder` assembles:

```text
boiler_enabled=false
→ legacy Section 12 full session source

boiler_enabled=true
→ compact Boiler moment map
→ active-moment runtime injection token
```

### 2.3 One canonical branch topology

Boiler does not change the existing choice architecture:

```text
Choice Design
→ four canonical branching choices
→ three A/B/C paths each
→ branch dimensions
→ defining lines
→ consequence mapping
```

Visible suggestions are a separate turn-presentation contract:

```text
Narration turn
→ zero to three visible suggestions
→ optional mapping to an existing canonical choice/path
→ freeform input always available
```

### 2.4 One standard cut

The initial implementation creates:

```text
cut_key=standard
```

It does not create quick, deep, or simultaneous variants.

### 2.5 Moment as a playable room

Each finalized moment is treated as a room:

- the player can inspect objects;
- the player can talk;
- the player can act physically;
- the player can explore for several turns;
- NPC, object, relationship, and world state can react;
- freeform input can create an equivalent or unexpected route;
- the moment exits when its dramatic state changes, not when a literal command checklist is satisfied.

---

## 3. Final pipeline wiring

### 3.1 Story-wide pipeline

The existing story-wide pipeline remains:

```text
IpTrimmingChapterJob × chapter count
→ IpTrimmingMergeJob
→ FormatDetectionJob
→ IpAuditJob
→ VoiceLockChapterJob × chapter count
→ VoiceLockMergeJob
→ StorySessionMapJob
```

`StorySessionMapJob` now also produces and validates `boiling_profile` when the persisted mode is enabled.

### 3.2 Canonical session chain

The final session order is:

```text
EntryPointDiagnosisJob
→ BoilerDraftJob                 only when Boiler is enabled
→ SessionArchitectureJob
→ ChoiceDesignJob
→ ConsequenceMappingJob
→ SessionCloseJob
→ BoilerFinalizeJob              only when Boiler is enabled
→ EditorialVerificationJob
→ RuntimeNarratorAssemblyJob
```

The sole chain definition is:

```text
app/Support/Adaptation/SessionAdaptationChain.php
```

The duplicate inline chain was removed from:

```text
app/Jobs/Adaptation/StorySessionMapJob.php
```

`StorySessionMapJob` now calls:

```php
SessionAdaptationChain::dispatchBatch(
    $this->story,
    $sessionNumbers,
    $reconciliationCallback,
);
```

The original Laravel batch behavior was preserved:

- sessions remain parallel chains inside one batch;
- jobs inside a session remain ordered;
- no `allowFailures()` was introduced;
- the existing `finally` callback still dispatches `AdaptationStatusReconciliationJob`.

### 3.3 Persist-and-reload ownership pattern

No large AI artifact is passed through queued constructors.

Each job receives stable identifiers, reloads prior artifacts, and writes only its owned output:

| Owner | Reads | Writes |
|---|---|---|
| Story Session Map | format, audit, trimming, voice, events | `story_session_map`, including `boiling_profile` |
| Entry Point | session source and story map | `entry_point_diagnosis` |
| Boiler Draft | Entry Point, guidance, session events, constraints | `boiler_draft` |
| Session Architecture | Draft and existing inputs | `session_architecture` |
| Choice Design | Draft, Architecture, existing choice inputs | `session_choice_design` |
| Consequence Mapping | Draft, Choice Design, state schema | `choice_consequence_map` |
| Session Close | Draft, choices, consequences, session events | `session_close_design` |
| Boiler Finalize | Draft and Phase 4-7 artifacts | normalized cut rows and `boiler_final_cut` manifest |
| Editorial | complete design and normalized Boiler rows | `editorial_verification` |
| Runtime Assembly | verified session artifacts | `runtime_narrator_prompt` and completed session status |

---

## 4. Database implementation

Six additive migrations were created.

### 4.1 Adaptation columns

File:

```text
database/migrations/2026_07_19_000001_add_boiler_adaptation_columns.php
```

`story_adaptations`:

```text
boiler_enabled boolean default false
```

`session_adaptations`:

```text
boiler_draft longText nullable
boiler_final_cut longText nullable
boiler_status string default pending
boiler_error text nullable
boiler_generated_at timestamp nullable
boiler_finalized_at timestamp nullable
```

The two artifact fields follow the existing phase-artifact convention: JSON serialized into long text and cast back to arrays by Eloquent.

### 4.2 Playable session cuts

File:

```text
database/migrations/2026_07_19_000002_create_playable_session_cuts_table.php
```

Columns:

```text
id
story_id
session_adaptation_id
session_number
cut_key
status
timestamps
```

Constraints:

- `story_id` cascades on story deletion;
- `session_adaptation_id` is unique and cascades on session-adaptation deletion;
- one session owns one normalized standard cut.

### 4.3 Playable moments

File:

```text
database/migrations/2026_07_19_000003_create_playable_moments_table.php
```

Columns:

```text
id
playable_session_cut_id
moment_key
moment_order
anchor_event_id nullable
source_event_ids JSON
scene_card JSON
timestamps
```

Constraints:

- moments cascade with their cut;
- anchor event uses `nullOnDelete`;
- `(playable_session_cut_id, moment_key)` is unique;
- `(playable_session_cut_id, moment_order)` is indexed.

The complete runtime card is stored only in `scene_card`.

### 4.4 Event playability roles

File:

```text
database/migrations/2026_07_19_000004_create_event_playability_roles_table.php
```

Columns:

```text
id
playable_session_cut_id
event_id
primary_role
included_moment_key nullable
why
reintroduction_note nullable
timestamps
```

Constraints:

- roles cascade with the cut;
- event deletion cascades its role;
- `(playable_session_cut_id, event_id)` is unique.

### 4.5 Game runtime pointers

File:

```text
database/migrations/2026_07_19_000005_add_boiler_runtime_columns_to_games_table.php
```

Columns:

```text
current_playable_session_cut_id nullable FK
current_playable_moment_id nullable FK
playable_moment_turn_count unsigned integer default 0
moment_footnote JSON nullable
```

Both pointers use `nullOnDelete`.

Deleting or replacing a cut therefore:

```text
deletes cut-owned moments and roles
→ clears stale game pointers
→ does not delete or block the game
```

No duplicate `current_event_id` was reintroduced. The active moment's `anchor_event_id` is the canon checkpoint.

### 4.6 Durable response idempotency

File:

```text
database/migrations/2026_07_19_000006_add_source_prompt_id_to_prompts_table.php
```

Column and constraint:

```text
source_prompt_id nullable ULID
unique(game_id, source_prompt_id)
```

Every post-player-turn response records which unanswered prompt produced it.

This database constraint permanently prevents a second response row for one source prompt, even after game state and footnotes have moved forward.

### 4.7 Models and casts

New models:

```text
app/Models/PlayableSessionCut.php
app/Models/PlayableMoment.php
app/Models/EventPlayabilityRole.php
```

Extended models:

```text
app/Models/StoryAdaptation.php
app/Models/SessionAdaptation.php
app/Models/Game.php
app/Models/Prompt.php
```

Added relationships include:

```text
SessionAdaptation → one PlayableSessionCut
PlayableSessionCut → many PlayableMoments
PlayableSessionCut → many EventPlayabilityRoles
PlayableMoment → optional anchor Event
Game → current PlayableSessionCut
Game → current PlayableMoment
Prompt → source Prompt
Prompt → response Prompt
```

---

## 5. Boiler lifecycle

Enum:

```text
app/Enums/BoilerStatus.php
```

Only six statuses exist:

```text
pending
drafting
drafted
finalizing
finalized
failed
```

Ownership:

```text
BoilerDraftJob:
pending → drafting → drafted

BoilerFinalizeJob:
drafted → finalizing → finalized

terminal job failure:
current state → failed
```

Retry behavior:

- transient exceptions store concise attempt context and rethrow;
- retryable attempts remain `drafting` or `finalizing`;
- `failed(Throwable)` changes Boiler and session status only after retries are exhausted;
- a terminal `failed` state is not silently reset by ordinary job entry;
- Runtime Assembly records its final failure and prevents false readiness.

---

## 6. Story-wide boiling guidance

### 6.1 Existing phase extended

Files:

```text
app/Ai/Agents/Adaptation/StorySessionMapAgent.php
app/Jobs/Adaptation/StorySessionMapJob.php
resources/views/ai/agents/adaptation/story-session-map/system-prompt.blade.php
resources/views/ai/agents/adaptation/story-session-map/prompt.blade.php
```

No extra story-wide AI call was introduced.

When Boiler is enabled, the existing Story Session Map call also produces:

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

When Boiler is disabled, the schema is assembled without `boiling_profile`; the persisted legacy Story Session Map contract remains unchanged.

### 6.2 Guidance prompt

The Story Session Map system prompt asks the existing agent to judge:

- source format and author pacing;
- action density;
- exposition density;
- connective material;
- emotional and relationship density;
- mystery dependencies;
- meaningful-choice density;
- repeated material;
- canon fragility;
- voice fragility;
- suspense, spectacle, intimacy, revelation, confrontation, payoff, and climax scenes requiring room.

It explicitly forbids a hardcoded genre, format, page-count, or event-count formula.

### 6.3 Structural validation

Before any session chain is dispatched, `StorySessionMapJob` verifies:

- positive total playtime;
- positive per-session playtime;
- one guidance entry for every allocation;
- no duplicate or unknown session numbers;
- positive moment-count minimum and maximum;
- minimum is not greater than maximum;
- every must-compress and must-breathe ID belongs to the matching event range;
- per-session target minutes reconcile to total minutes within one minute per session of rounding tolerance.

An invalid profile fails Story Session Map before downstream creative work starts.

---

## 7. Shared session event loading

Service:

```text
app/Services/Adaptation/SessionEventLoader.php
```

The loader replaced duplicated event-order logic in:

- Story Session Map;
- Entry Point Diagnosis;
- Session Close;
- Runtime Narrator Template Builder;
- Boiler context construction.

It returns stable session events with:

```text
database event ID
chapter ID
chapter position
chapter-local event position
story-global position
chapter title
event title
objectives
content
session number
before_entry_point marker
```

All Boiler validation, source coverage, and runtime event identity now share the same event-order contract.

---

## 8. Boiler Draft implementation

### 8.1 Components

```text
app/Ai/Agents/Adaptation/BoilerDraftAgent.php
app/Jobs/Adaptation/BoilerDraftJob.php
app/Ai/Adaptation/BoilerDraftContextBuilder.php
app/Ai/Adaptation/BoilerDraftValidator.php
resources/views/ai/agents/adaptation/boiler-draft/system-prompt.blade.php
resources/views/ai/agents/adaptation/boiler-draft/prompt.blade.php
```

### 8.2 Model policy

The Draft agent explicitly uses the same high-judgment tier as IP Trimming:

```php
#[Provider('anthropic')]
#[Model('claude-opus-4-8')]
#[Timeout(300)]
```

Job-level queue protection:

```text
tries: 3
timeout: 720 seconds
backoff: 60 seconds
queue: adaptation
```

### 8.3 Draft inputs

`BoilerDraftContextBuilder` supplies:

- story ID and title;
- session number;
- session allocation;
- story-wide boiling profile;
- matching per-session guidance;
- Entry Point diagnosis;
- format detection;
- bounded-agency evidence from IP Audit;
- StoryGuard canon;
- session branch opportunities;
- IP Trimming conversion notes;
- controlled Voice DNA and hard-ban slice;
- only events assigned to this session.

Source budgeting:

```text
session event source budget: 60,000 characters
per-event extract ceiling: 8,000 characters
```

Every event retains full identity and objectives. Source text may be truncated to stay within the session call budget; the agent is told whether truncation occurred.

### 8.4 Draft system prompt

The Draft system prompt defines Boiler as a critical playable-cut editor, not a summarizer and not a runtime prose writer.

It requires one role per event:

```text
PLAY
PULSE
CHOICE
REACTION
MONTAGE
ECHO
CUT
```

The critique considers:

- player agency;
- live pressure;
- memorable image;
- consequence potential;
- identity expression;
- emotional expression;
- personality expression;
- onboarding value;
- author-language value;
- branch relevance;
- canon dependency;
- repetition;
- exploration potential;
- whether the event has earned breathing room.

It requires every assigned event to remain visible in the role map, including material before the Entry Point.

It requires reintroduction notes for compressed or cut canon and for protected must-breathe material.

### 8.5 Moment clustering

Draft selects one exact moment count inside the Story Session Map's approved range.

Each moment draft contains:

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

The first moment must contain the Entry Point start event.

### 8.6 Playable Promise arc

Draft identifies:

```text
first_proof_moment
midpoint_intensification
final_cash_out
```

All keys are session-local. Short sessions may reuse one moment for multiple anchors. `final_cash_out` must be the final ordered moment.

### 8.7 Semantic exit conditions

The prompt distinguishes:

```text
incorrect:
The player takes, hides, rejects, or abandons the shard.

correct:
The room ends once the player meaningfully commits toward the shard
or changes the situation enough to make that commitment obsolete.
```

Exit conditions describe dramatic outcomes. Affordances are possible routes, not an exhaustive list.

PHP validates only:

- presence;
- string type;
- minimum and maximum length.

PHP never compares player prose to exit-condition wording.

### 8.8 Draft validation

`BoilerDraftValidator` verifies:

- version and session identity;
- matching Story Session Map guidance;
- exact target playtime identity;
- exact must-compress and must-breathe identity;
- target moment count inside the approved range;
- drafted moment count equals target count;
- every assigned event appears once in the role map;
- valid role names;
- a reason for every event;
- playable events bind to moments;
- moment keys are unique and ordered;
- source events belong to the session;
- one source event cannot belong to two moments;
- source events bind back to the same role-map moment key;
- anchor event belongs to the moment;
- runtime goal and live pressure are present;
- affordances and source extracts are present;
- exit condition is structurally bounded;
- must-breathe MONTAGE or CUT events have reintroduction notes;
- Playable Promise anchors resolve;
- final cash-out is last;
- first moment contains the Entry Point event.

### 8.9 Draft persistence

On success:

```text
session_adaptations.boiler_draft
session_adaptations.boiler_status=drafted
session_adaptations.boiler_generated_at
```

The job logs:

```text
boiler.draft_started
boiler.draft_completed
boiler.draft_attempt_failed
```

---

## 9. Phase 4-7 moment bindings

Boiler responsibilities were integrated into existing owners rather than duplicated.

### 9.1 Conditional phase schemas

Existing agent constructors now accept persisted Boiler mode.

When disabled:

- Boiler fields are absent from their JSON schemas;
- the existing strict structured-output schema remains intact.

When enabled:

- Boiler fields are required;
- job validators verify all references.

This follows the codebase's strict-schema pattern instead of relying on unsupported JSON Schema conditionals.

### 9.2 Session Architecture

Files:

```text
app/Ai/Agents/Adaptation/SessionArchitectureAgent.php
app/Jobs/Adaptation/SessionArchitectureJob.php
resources/views/ai/agents/adaptation/session-architecture/system-prompt.blade.php
resources/views/ai/agents/adaptation/session-architecture/prompt.blade.php
```

Additions:

```text
beat_map[].moment_key
boiler_budget_alignment.aligned
boiler_budget_alignment.conflict
```

Architecture retains ownership of:

- beat structure;
- narration budget;
- compression targets;
- expansion targets;
- dialogue treatment;
- interaction placement.

It must distribute those inside Draft's approved target and moment count. It cannot split, merge, add, or delete moments.

An unresolved budget conflict fails visibly.

### 9.3 Choice Design

Files:

```text
app/Ai/Agents/Adaptation/ChoiceDesignAgent.php
app/Jobs/Adaptation/ChoiceDesignJob.php
resources/views/ai/agents/adaptation/choice-design/system-prompt.blade.php
resources/views/ai/agents/adaptation/choice-design/prompt.blade.php
```

Additions:

```text
branching_choices[].moment_key
emotional_choices[].moment_key
posture_shifts[].moment_key
```

Preserved:

- four canonical branching choices;
- A/B/C labels;
- three options per canonical branching choice;
- option text;
- authored outcomes;
- branch dimensions;
- defining lines;
- freeform alignment mapping;
- StoryGuard manifests.

The job validates canonical topology and moment references only in Boiler mode, preserving disabled-mode behavior.

### 9.4 Consequence Mapping

Files:

```text
app/Ai/Agents/Adaptation/ConsequenceMappingAgent.php
app/Jobs/Adaptation/ConsequenceMappingJob.php
resources/views/ai/agents/adaptation/consequence-mapping/system-prompt.blade.php
resources/views/ai/agents/adaptation/consequence-mapping/prompt.blade.php
```

Additions for branching and emotional consequences:

```text
moment_key
echo_moment_key
moment_narrator_hook
```

The job verifies:

- immediate moment matches the authored choice moment;
- optional echo moment exists;
- narrator hook is present;
- current branch paths and state deltas remain unchanged.

Emotional choices are now explicitly passed into the consequence prompt.

### 9.5 Session Close

Files:

```text
app/Ai/Agents/Adaptation/SessionCloseAgent.php
app/Jobs/Adaptation/SessionCloseJob.php
resources/views/ai/agents/adaptation/session-close/system-prompt.blade.php
resources/views/ai/agents/adaptation/session-close/prompt.blade.php
```

Additions:

```text
closing_moment_key
closing_moment_relationship
next_bridge_guidance
```

The close must bind to the final ordered Draft moment. Its trigger event must belong to that moment.

Existing close assets remain authoritative:

- resolution prose;
- hook transition;
- A/B/C session-end choice;
- final line;
- stickiness audit.

The current V2 session-end choice selection was corrected to locate `SESSION_END_HOOK` / `_C4` and `is_session_end_hook`, rather than relying on a nonexistent `choice_number` field.

---

## 10. Boiler Finalize implementation

### 10.1 Components

```text
app/Ai/Agents/Adaptation/BoilerFinalizeAgent.php
app/Jobs/Adaptation/BoilerFinalizeJob.php
app/Ai/Adaptation/BoilerFinalizeContextBuilder.php
app/Ai/Adaptation/BoilerFinalCutValidator.php
app/Services/Adaptation/BoilerCutNormalizer.php
resources/views/ai/agents/adaptation/boiler-finalize/system-prompt.blade.php
resources/views/ai/agents/adaptation/boiler-finalize/prompt.blade.php
```

### 10.2 Model policy

Finalize uses:

```php
#[Provider('anthropic')]
#[Model('claude-opus-4-8')]
#[Timeout(300)]
```

Queue policy:

```text
tries: 3
timeout: 720 seconds
backoff: 60 seconds
queue: adaptation
```

### 10.3 Inputs

Finalize reloads:

- Boiler Draft;
- session boiling guidance;
- Session Architecture;
- Choice Design;
- Consequence Mapping;
- Session Close;
- StoryGuard canon;
- controlled voice constraints.

It does not receive the raw full source.

### 10.4 Finalize prompt

The Finalize system prompt states that Finalize compiles rather than redesigns.

It must preserve:

- Draft moment count;
- Draft keys and order;
- source-event clusters;
- anchor events;
- event-role coverage and rationale;
- canonical choice IDs;
- consequence bindings;
- existing close.

### 10.5 Canonical scene card

Every final card contains:

```text
moment_key
moment_order
title
source_event_ids
anchor_event_id
treatment
runtime_goal
player_desire
live_pressure
opening_image
player_affordances
canon_must_keep
allowed_variance
forbidden_expansions
source_extracts
choice_ids
word_budget.target_per_turn
turn_budget.target_turns
turn_budget.gravity_after
exit_condition
next_moment_key for non-final moments
next_bridge
```

For strict structured output, the agent emits an empty `next_moment_key` string on the final card. Normalization removes it from the canonical stored card.

### 10.6 Final validation

`BoilerFinalCutValidator` verifies:

- version, session, and `standard` cut key;
- final moment count equals Draft target;
- Architecture reports budget alignment;
- final keys and order equal Draft;
- source and anchor events equal Draft;
- all event IDs belong to the session;
- required card strings and arrays exist;
- affordances and source extracts exist;
- exit condition is structurally bounded;
- word and turn budgets are positive;
- gravity does not precede target turns;
- every choice ID exists and binds to that moment;
- every non-final `next_moment_key` equals the key at `moment_order + 1`;
- final `next_moment_key` is empty, null, or absent after normalization;
- Promise arc keys resolve;
- final cash-out and Session Close share the final moment;
- event-role identity, included moment, reason, and reintroduction note remain identical to Draft;
- final role coverage equals assigned event coverage.

Runtime never routes using `next_moment_key`. Relational `moment_order + 1` is the sole progression authority.

### 10.7 Transactional normalization

`BoilerCutNormalizer` performs one database transaction:

```text
lock session adaptation
→ delete prior normalized cut for that session
→ create one finalized standard cut
→ create ordered moments
→ create event roles
→ derive manifest-only boiler_final_cut
→ set boiler_status=finalized
→ set boiler_finalized_at
```

If the transaction fails, all normalized writes roll back.

### 10.8 Manifest-only final artifact

`session_adaptations.boiler_final_cut` contains references and summaries:

```json
{
  "boiler_version": "1.0",
  "session_number": 1,
  "playable_promise": "Session promise.",
  "moment_manifest": [
    {
      "moment_key": "S1_M01",
      "moment_order": 1
    }
  ],
  "coverage_summary": {
    "source_events": 12,
    "events_accounted_for": 12
  },
  "pacing_summary": {
    "moment_count": 6,
    "first_agency_moment": "S1_M01"
  }
}
```

Complete cards are not duplicated into this manifest.

Finalize logs:

```text
boiler.finalize_started
boiler.finalize_completed
boiler.finalize_attempt_failed
```

---

## 11. Boiler-aware Editorial Verification

Files:

```text
app/Ai/Agents/Adaptation/EditorialVerificationAgent.php
app/Jobs/Adaptation/EditorialVerificationJob.php
resources/views/ai/agents/adaptation/editorial-verification/system-prompt.blade.php
resources/views/ai/agents/adaptation/editorial-verification/prompt.blade.php
```

When Boiler is enabled, Editorial receives:

- Boiler Draft;
- final manifest;
- normalized event roles;
- canonical ordered scene cards;
- existing Phase 3-7 design;
- voice profile;
- StoryGuard canon;
- persistent-state schema;
- world-reactivity rules.

The persisted `boiler_audit` checks:

1. event coverage;
2. canon reintroduction;
3. moment alignment;
4. canonical choice bindings;
5. consequence and close bindings;
6. playable-room quality;
7. pacing and budget agreement;
8. Playable Promise arc;
9. semantic exit conditions;
10. immersion, consequence, and momentum balance.

Boiler production status is `PASS` only when all ten checks pass.

The existing 23-question editorial audit remains intact.

The RED auto-retry lookup was corrected to read:

```text
final_verdict.production_status
```

Editorial now:

```text
persists editorial_verification
→ leaves session_status=editorial-verification
```

It no longer marks the session playable.

---

## 12. Runtime Assembly and structural validation

### 12.1 Completion ownership

Files:

```text
app/Jobs/Adaptation/RuntimeNarratorAssemblyJob.php
app/Enums/Adaptation/SessionAdaptationStatusEnum.php
```

New status:

```text
runtime-assembly
```

Final lifecycle:

```text
Editorial succeeds
→ session remains editorial-verification

Runtime Assembly begins
→ session becomes runtime-assembly

build + validate + persist succeeds
→ session becomes completed

Runtime Assembly exhausts retries
→ session becomes failed
```

### 12.2 Shared prompt validator

File:

```text
app/Ai/Adaptation/RuntimeNarratorPromptValidator.php
```

Both Runtime Assembly and story reconciliation use the same validator.

Common checks:

- prompt is non-empty;
- prompt does not exceed `RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS`;
- no unresolved Blade `{{ token }}` remains;
- each current runtime injection token appears exactly once.

Legacy mode:

- Boiler markers must be absent;
- existing runtime tokens must remain valid;
- no Boiler moment-map requirement is applied.

Boiler mode:

- `[BOILER_RUNTIME_V1]` appears exactly once;
- `[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]` appears exactly once;
- the legacy full-event Section 12 marker is absent;
- Boiler map, progress, suggestion, and critique instruction blocks are present;
- ordered moment map is non-empty;
- unknown uppercase control tokens fail against an explicit allowlist.

### 12.3 Runtime prompt builder

File:

```text
app/Ai/Adaptation/RuntimeNarratorTemplateBuilder.php
```

Changes:

- uses `SessionEventLoader`;
- loads finalized standard cut when Boiler mode is persisted;
- refuses Boiler assembly without moments;
- passes manifest and canonical cards to the Blade template;
- maintains the legacy branch when disabled;
- keeps all existing voice, StoryGuard, choice, consequence, world-state, and close sections.

### 12.4 Boiler Section 12

File:

```text
resources/views/ai/agents/chaos/runtime-narrator-template.blade.php
```

Legacy branch:

```text
SECTION 12: FULL CURRENT EPISODE SCRIPT
```

Boiler branch:

```text
[BOILER_RUNTIME_V1]

SECTION 12: BOILER PLAYABLE SESSION MAP
→ Playable Promise
→ ordered moment key/title/treatment
→ runtime goal
→ pressure
→ essential canon
→ word/turn pacing
→ choice IDs
→ active-moment injection token
```

The full session script is not rendered into the Boiler prompt.

The template defaults Boiler variables for compatibility with existing direct template-render probes.

### 12.5 Runtime narration instructions added

The Boiler branch adds:

```text
BOILER MOMENT_PROGRESS CONTRACT
BOILER PLAYER-CRITIQUE FOOTNOTE
BOILER VISIBLE_SUGGESTIONS CONTRACT
```

The contract tells the narrator:

- perform only the active room;
- resolve latest player input first;
- treat time and turn count as soft cues;
- judge semantic completion;
- continue meaningful exploration;
- introduce revelation, resistance, consequence, or pressure when play stagnates;
- bridge naturally into the next preview when the current room exits;
- produce zero to three contextual suggestions;
- keep canonical alignment hidden;
- emit one hidden local critique after player input.

---

## 13. Adaptation reconciliation

File:

```text
app/Jobs/Adaptation/AdaptationStatusReconciliationJob.php
```

For every completed session in both modes:

- prompt must be non-empty;
- prompt must pass `RuntimeNarratorPromptValidator`.

For Boiler-enabled sessions, reconciliation additionally requires:

```text
boiler_status=finalized
one normalized cut
cut status=finalized
cut key=standard
at least one ordered moment
```

Story status:

```text
all valid sessions → completed
all failed sessions → failed
mixed or missing result → partial-completion
```

Successful completion also invokes the shared story-game reset exactly once while transitioning the adaptation from non-completed to completed.

---

## 14. Runtime active-moment injection

File:

```text
app/Services/ChaosEngineService.php
```

`loadSessionContext()` accepts an optional game and resolves:

- current cut;
- current moment;
- relational next moment;
- matching footnote;
- current moment turn count.

The current moment must belong to:

```text
game.current_playable_session_cut_id
and
the current SessionAdaptation
```

A stale or cross-session pointer throws rather than silently using the wrong card.

### 14.1 Active card block

The request-time injection contains:

```text
full active scene_card
soft turn count
matching hidden treasure insight
bounded next-moment preview
```

### 14.2 Bounded next preview

The preview includes only:

```text
next moment key
title
runtime goal
opening image
live pressure
player affordances
choice IDs
essential canon
current moment next bridge
```

The prompt states that the preview is ignored while the current exit remains false.

### 14.3 Active state scoping

World-state filtering uses active moment source event IDs when a moment is present. Legacy mode retains session-level state scoping.

### 14.4 Accumulated room conversation

`PromptController` narrows Boiler conversation history using the active moment turn count. Legacy turns retain the existing twelve-message history window.

---

## 15. Narration response contract

File:

```text
app/Ai/Agents/Chaos/ChaosNarrationSchema.php
```

Legacy field:

```json
{
  "choices": ["zero to three legacy strings"]
}
```

Boiler fields:

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

`choices`, `visible_suggestions`, and `moment_progress` remain globally optional so legacy prompts can use the shared schema.

When an active moment exists, runtime validation requires:

- `visible_suggestions` exists, including an empty array;
- no legacy `choices` are emitted simultaneously;
- zero to three suggestions;
- `moment_progress` exists;
- moment key matches the active moment;
- exit value is boolean.

Opening narration additionally requires:

```text
exit_condition_met=false
moment_footnote=null or omitted by structured output
```

Player turns require a non-empty footnote.

---

## 16. Hidden player critique footnote

Storage:

```text
games.moment_footnote
```

Shape:

```json
{
  "moment_key": "S1_M01",
  "text": "The player seeks proof, not delay; answer with revealing resistance, then return the decision pressure.",
  "source_prompt_id": "01J..."
}
```

Properties:

- hidden from player resources;
- created only after actual player input;
- replaced every successful turn;
- never accumulated as a permanent diagnosis;
- associated with one moment;
- discarded from injection when the key is stale;
- cleared on session initialization and reset;
- carried to the next moment only during a validated sequential transition;
- truncated to the first thirty Unicode-aware words when over budget;
- logged when truncation occurs;
- never interpreted by PHP.

The narrator prompt asks for a probabilistic local critique that distinguishes:

- observed action;
- likely intent;
- emotional need;
- emerging narrative preference;
- protagonist shaping;
- contradiction or change;
- best next narrative response.

It forbids:

- psychological diagnosis;
- permanent labels;
- numerical scoring;
- generic commands such as only saying to increase pressure.

---

## 17. Visible suggestions and canonical branches

### 17.1 Shared normalizer

File:

```text
app/Services/Runtime/NarrationResultNormalizer.php
```

Used by:

- game opening;
- player turns;
- next-session opening.

It accepts:

```text
legacy string choices
or
Boiler suggestion objects
```

It enforces:

- maximum of three;
- non-empty bounded text;
- canonical choice ID and path appear together;
- path is A, B, or C;
- unmapped actions normalize to null canonical identity.

### 17.2 Resource compatibility

File:

```text
app/Http/Resources/PromptResource.php
```

The resource preserves legacy strings and normalizes suggestion objects into:

```text
text
canonical_choice_id
canonical_path_label
```

### 17.3 Stable click identity

Frontend submits:

```text
prompt
prompt_id
suggestion_index
canonical_choice_id
canonical_path_label
```

Backend validation:

- prompt ID is a ULID;
- index is zero through two;
- canonical fields are paired;
- path is A/B/C;
- prompt belongs to the game;
- prompt is still unanswered and latest;
- indexed stored item matches submitted text and identity;
- mapped choice is bound to the active moment;
- mapped path exists in authored Choice Design.

Request fields alone are never trusted.

### 17.4 Explicit mapped branch authority

For a validated mapped click:

- explicit stored identity is authoritative;
- narrator receives the validated choice/path context;
- if model inference conflicts, a warning is logged;
- resolver keeps the validated click;
- existing `resolveBranchCommit()` remains the final canonical branch validator.

Freeform and unmapped actions continue through model classification.

### 17.5 Continue behavior

Legacy:

```text
unconditional Continue remains available
```

Boiler:

```text
no unconditional Continue button
```

If continuation is appropriate, the narrator emits an unmapped suggestion whose text is `Continue`.

Only an indexed click on that stored item becomes the existing `__continue__` marker.

Typing `Continue` manually remains ordinary freeform input.

---

## 18. Duplicate-turn and atomic commit implementation

### 18.1 Three protection layers

1. Frontend sends the exact unanswered `prompt_id`.
2. `PromptController` acquires a bounded cache lock:

```text
game-turn:{game-id}:{prompt-id}
TTL: 120 seconds
```

3. Database permanently enforces:

```text
unique(game_id, source_prompt_id)
```

### 18.2 Claim behavior

Before the model call:

- acquire shared cache lock;
- load exact game-owned prompt;
- require `prompt IS NULL`;
- require it is still the latest prompt;
- reject duplicate or stale requests.

The database transaction is not held open during the model call.

### 18.3 Crash recovery

The prompt's player-answer field is not written before narration succeeds.

Therefore:

- a process crash leaves the source prompt unanswered;
- the bounded lock expires;
- the same turn can be retried;
- a concurrent request cannot be accepted while the lock owner remains active.

The lock is released in `finally` for:

- model/API failure;
- schema failure;
- moment-progress failure;
- suggestion validation failure;
- resolver failure;
- transaction failure.

### 18.4 Legacy commit

Legacy turns preserve current world/branch semantics but now commit inside one database transaction:

```text
lock game
lock source prompt
check durable response link
merge state
resolve branch
write source player action
update game
create response with source_prompt_id
commit
```

### 18.5 Boiler commit

Boiler post-player-turn writes are owned only by:

```text
app/Services/Runtime/PlayableMomentResolver.php
```

The controller does not separately write Boiler world, branch, moment, session, footnote, or response state.

---

## 19. PlayableMomentResolver

### 19.1 Initialization

`GameController::begin()` and `GameController::nextSession()` initialize before opening narration:

```text
load finalized standard cut
load first ordered moment
set current_playable_session_cut_id
set current_playable_moment_id
set playable_moment_turn_count=0
clear moment_footnote
set current_session_complete=false
```

### 19.2 Player-turn transaction

The resolver:

1. locks the game;
2. locks the source prompt;
3. returns the existing response if `source_prompt_id` was already processed;
4. validates source prompt remains unanswered;
5. locks current moment;
6. verifies reported moment key;
7. verifies boolean exit;
8. loads only `moment_order + 1` when exit is true;
9. validates suggestions against the resulting active moment;
10. applies explicit canonical click identity when present;
11. merges world state;
12. merges alignment;
13. appends symbolic memory;
14. invokes existing branch commit validation;
15. bounds and replaces the footnote;
16. updates source player action;
17. advances or retains the moment;
18. increments or resets turn count;
19. sets session complete only after final ordered moment exits;
20. creates one response prompt with `source_prompt_id`;
21. commits everything together.

### 19.3 Sequential progression

Runtime routing is:

```text
current relational moment_order + 1
```

It never queries by `scene_card.next_moment_key`.

### 19.4 Session completion

Boiler ignores model `session_complete` as technical authority.

The resolver sets completion only when:

```text
current moment exit is true
and
no moment_order + 1 exists
```

The final cut and moment pointers remain set until `nextSession()` or reset so the frontend remains in Boiler interaction mode on the completion screen.

### 19.5 Runtime logging

Committed turns log:

```text
game ID
session number
source prompt ID
moment key
anchor event
exit judgment
retained / advanced / completed result
next moment
bounded footnote
```

Branch-model disagreement and footnote truncation have dedicated warnings.

---

## 20. Game lifecycle wiring

### 20.1 Begin

`GameController::begin()`:

- loads session 1;
- initializes first cut/moment when Boiler is enabled;
- loads runtime context with active game state;
- renders active card;
- calls narrator with opening validation;
- requires false exit and no footnote;
- validates opening suggestions against current moment;
- does not increment turn count;
- does not advance;
- creates the first unanswered prompt.

### 20.2 Player turn

`PromptController::store()`:

- receives stable source identity;
- claims the source;
- validates clicked suggestion;
- loads active moment context;
- injects previous matching insight;
- calls narrator;
- normalizes suggestions;
- dispatches to Boiler resolver or legacy transaction;
- releases the lock on every path.

### 20.3 Next session

`GameController::nextSession()`:

- preserves prior session pointers until transition begins;
- initializes next cut/moment before opening narration;
- clears footnote and turn count;
- carries world state, memory, alignment, and branch identity;
- validates next opening;
- restores the previous Boiler state if initialization, context loading, prompt rendering, schema validation, or model execution fails.

### 20.4 Reset

Shared service:

```text
app/Services/Adaptation/StoryGameResetService.php
```

Both manual reset and successful re-adaptation reconciliation use it.

It:

- writes `GameReset` audit;
- deletes prompts;
- increments story cycle;
- resets to session 1;
- clears session completion;
- clears world state;
- clears symbolic memory;
- resets alignment;
- clears branch identity;
- clears climactic flag;
- clears cut pointer;
- clears moment pointer;
- resets moment turn count;
- clears footnote;
- clears `completed_at`.

---

## 21. Re-adaptation behavior

Focused retrofit is intentionally deferred.

To move a previously adapted story into Boiler mode:

```text
full normal re-adaptation with boiler_enabled=true
```

Current destructive semantics remain:

```text
forced re-adaptation
→ deletes current session adaptations
→ cut/moment cascade runs
→ game pointers null through nullOnDelete
→ replacement pipeline builds
→ all sessions validate and assemble
→ reconciliation resets games exactly once
→ adaptation becomes completed
```

Failed re-adaptation:

- does not run the successful game reset;
- does not mark adaptation complete;
- does not attempt mid-sequence migration.

---

## 22. Frontend implementation

Files:

```text
resources/js/types/index.d.ts
resources/js/components/GameplayChatCard.vue
resources/js/pages/User/Games/Show.vue
resources/js/pages/BgRnd.vue
app/Http/Resources/GameResource.php
app/Http/Resources/PromptResource.php
app/Http/Requests/User/Game/Prompt/StorePromptRequest.php
```

### 22.1 Runtime discriminator

`GameResource` exposes:

```json
{
  "uses_playable_moments": true
}
```

It is computed from non-null `current_playable_moment_id`.

### 22.2 Choice compatibility

TypeScript accepts:

```text
string
or
VisibleSuggestionInterface
```

The card normalizes both into renderable text while retaining the original stored payload for submission.

### 22.3 Interaction behavior

Legacy card:

- renders string choices;
- keeps universal Continue;
- keeps freeform.

Boiler card:

- renders exactly zero to three stored suggestions;
- does not add universal Continue;
- sends suggestion index and stable identity;
- keeps freeform input at all times.

No broad gameplay visual redesign was introduced.

---

## 23. Diagnostics and creator visibility

### 23.1 Boiler status command

File:

```text
app/Console/Commands/BoilerStatusCommand.php
```

Command:

```bash
php artisan stories:boiler-status {story} --session={number}
```

Output includes:

- persisted mode;
- adaptation status;
- session status;
- Boiler status;
- cut status;
- moment count;
- prompt character count;
- Boiler error.

### 23.2 Boiler inspection command

File:

```text
app/Console/Commands/InspectBoilerCommand.php
```

Command:

```bash
php artisan stories:inspect-boiler {story} --session={number}
```

Output includes:

- each event role;
- reason;
- included moment;
- reintroduction;
- moment order;
- source events;
- anchor;
- choice IDs;
- goal;
- desire;
- pressure;
- extracts;
- exit condition;
- turn budget;
- next moment;
- prompt size.

### 23.3 Adaptation exports

`ExportAdaptationCommand` and creator JSON export now include:

- `boiler_enabled`;
- Boiler status;
- Boiler error;
- Draft artifact;
- final manifest.

### 23.4 Filament creator view

Creator infolist now shows:

- Boiler mode enabled/disabled;
- per-session Boiler status;
- Boiler Draft;
- final Boiler manifest.

---

## 24. Tests added

No tests were executed by the implementing agent.

Files:

```text
tests/Unit/BoilerRuntimeContractTest.php
tests/Unit/BoilerArtifactValidatorTest.php
tests/Unit/BoilerWiringContractTest.php
tests/Feature/Boiler/PlayableMomentResolverTest.php
tests/Feature/Boiler/BoilerForeignKeyLifecycleTest.php
tests/Feature/Boiler/StoryGameResetServiceTest.php
```

Coverage written for:

- six-state lifecycle;
- explicit Opus model attributes;
- Boiler fields absent from disabled phase schemas;
- legacy and Boiler prompt validation;
- old full-event marker rejection;
- legacy choices and object suggestion normalization;
- zero-to-three suggestion limit;
- Draft contract;
- protected must-breathe handling;
- Finalize sequential next-key validation;
- canonical chain order;
- duplicate inline chain removal;
- FK/nullOnDelete migration contract;
- assembly completion ownership;
- relational progression authority;
- frontend discriminator;
- reset only after successful reconciliation;
- durable one-response-per-source behavior;
- no double moment increment;
- sequential advancement;
- final pointer preservation;
- resolver transaction rollback;
- opening false-exit/null-footnote contract;
- cut cascade and game-pointer nulling;
- shared legacy/Boiler reset semantics.

Daniel-run commands:

```bash
php artisan test --filter=Boiler
php artisan test
npm run lint
npm run build
```

Run these only against the intended testing environment/database.

---

## 25. Commit process

Nine primary stages were implemented:

| Stage | Commit | Scope |
|---|---|---|
| 1 | `cf6cffc` | Schema and models |
| 2 | `f2a9981` | Toggle and canonical pipeline wiring |
| 3 | `d88ea4a` | Boiling guidance, shared events, Draft |
| 4 | `6a4b68c` | Phase 4-7 bindings |
| 5 | `9647f13` | Finalize and normalization |
| 6 | `a6ab2e1` | Editorial and Runtime Assembly |
| 7 | `4f2b72c` | Runtime progression and idempotency |
| 8 | `3d04a12` | Suggestions and frontend |
| 9 | `a6637eb` | Diagnostics, tests, rollback runbook |

Static audit and documentation commits:

| Commit | Scope |
|---|---|
| `9d34477` | Initial rollback ledger finalization |
| `712b5bb` | Runtime contract tightening |
| `db738ff` | Static audit ledger update |
| `4e15a5b` | One-to-one event/moment ownership |
| `c872600` | Lifecycle/opening/reset tests |
| `453f839` | Strict disabled-mode schema preservation |
| `60d3ccb` | Validator/template compatibility |
| `31b93ea` | Disabled schema test |
| `a010a21` | Prompt choice normalization and compact map |
| `2e13d7a` | Complete audit/rollback sequence |

The rollback reference remains:

```text
pre-boiler
→ 42d022984bdf77e6080f0fdb63de95ab19db160b
```

---

## 26. Prompt inventory

### 26.1 Story-wide boiling guidance

```text
resources/views/ai/agents/adaptation/story-session-map/system-prompt.blade.php
resources/views/ai/agents/adaptation/story-session-map/prompt.blade.php
```

Purpose:

- judge story/session compaction;
- identify must-breathe and must-compress events;
- establish playtime and moment-count authority.

### 26.2 Boiler Draft

```text
resources/views/ai/agents/adaptation/boiler-draft/system-prompt.blade.php
resources/views/ai/agents/adaptation/boiler-draft/prompt.blade.php
```

Purpose:

- event-by-event critique;
- one role per event;
- sequential room clustering;
- source extract selection;
- affordances and canon bounds;
- semantic exit drafting;
- exact moment-count decision.

### 26.3 Session Architecture binding

```text
resources/views/ai/agents/adaptation/session-architecture/system-prompt.blade.php
resources/views/ai/agents/adaptation/session-architecture/prompt.blade.php
```

Purpose:

- bind existing beats to moment keys;
- align existing content budget;
- expose unresolved budget conflict.

### 26.4 Choice Design binding

```text
resources/views/ai/agents/adaptation/choice-design/system-prompt.blade.php
resources/views/ai/agents/adaptation/choice-design/prompt.blade.php
```

Purpose:

- bind canonical choices, emotional choices, and posture shifts;
- explicitly preserve A/B/C topology.

### 26.5 Consequence binding

```text
resources/views/ai/agents/adaptation/consequence-mapping/system-prompt.blade.php
resources/views/ai/agents/adaptation/consequence-mapping/prompt.blade.php
```

Purpose:

- attach immediate consequence surface;
- attach optional same-session echo;
- write moment-aware narrator hook;
- preserve current consequence paths.

### 26.6 Session Close binding

```text
resources/views/ai/agents/adaptation/session-close/system-prompt.blade.php
resources/views/ai/agents/adaptation/session-close/prompt.blade.php
```

Purpose:

- bind existing close to final moment;
- describe trigger relationship;
- provide Finalize bridge guidance.

### 26.7 Boiler Finalize

```text
resources/views/ai/agents/adaptation/boiler-finalize/system-prompt.blade.php
resources/views/ai/agents/adaptation/boiler-finalize/prompt.blade.php
```

Purpose:

- compile canonical cards;
- preserve Draft and Phase 4-7 authority;
- write one standard cut;
- preserve sequential routing evidence.

### 26.8 Editorial audit

```text
resources/views/ai/agents/adaptation/editorial-verification/system-prompt.blade.php
resources/views/ai/agents/adaptation/editorial-verification/prompt.blade.php
```

Purpose:

- inspect normalized cards and event roles;
- verify budget, canon, choice, consequence, close, semantic exit, and experiential quality.

### 26.9 Runtime narrator

```text
resources/views/ai/agents/chaos/runtime-narrator-template.blade.php
```

Purpose:

- preserve legacy prompt branch;
- add compact Boiler map;
- inject active room;
- define semantic progression;
- request hidden player critique;
- separate visible suggestions from canonical branches;
- enforce responsive continuation rather than repetition.

---

## 27. End-to-end enabled-mode walkthrough

```text
Creator enables Boiler and dispatches adaptation
→ RunAdaptationPipelineJob persists boiler_enabled=true
→ story-wide phases run normally
→ StorySessionMapAgent emits allocations + boiling_profile
→ StorySessionMapJob validates guidance
→ session rows are created
→ canonical session chains dispatch in parallel

Per session:
EntryPointDiagnosisJob chooses real start
→ BoilerDraftAgent critiques every assigned event
→ BoilerDraftValidator proves coverage and count
→ SessionArchitecture binds beats and aligns budget
→ ChoiceDesign binds canonical choices
→ ConsequenceMapping binds immediate/echo surfaces
→ SessionClose binds final close
→ BoilerFinalizeAgent compiles complete cards
→ BoilerFinalCutValidator verifies references/order
→ BoilerCutNormalizer writes cut/moments/roles atomically
→ Editorial reads normalized cards and passes Boiler audit
→ RuntimeNarratorTemplateBuilder creates compact prompt
→ RuntimeNarratorPromptValidator verifies markers/tokens
→ RuntimeNarratorAssemblyJob persists prompt and completes session

All sessions:
AdaptationStatusReconciliationJob validates complete runtime result
→ shared game reset executes once
→ story adaptation becomes completed

Gameplay:
begin initializes first cut/moment
→ opening narrator receives active room
→ opening response has false exit and no footnote
→ player sees zero to three suggestions or uses freeform
→ exact source prompt is claimed
→ narrator responds with state, suggestions, progress, critique
→ resolver atomically retains or advances
→ world memory and branch identity persist
→ final ordered room exits
→ session completes
→ nextSession initializes next cut/moment
```

---

## 28. Disabled-mode walkthrough

```text
Creator dispatches adaptation with Boiler off
→ boiler_enabled=false persists
→ Story Session Map omits boiling_profile
→ session chain omits Draft and Finalize
→ Phase 4-7 use original schemas
→ Editorial uses original schema
→ Runtime Assembly builds legacy Section 12
→ shared validator checks legacy runtime requirements
→ Assembly owns completed status
→ reconciliation validates prompt
→ runtime has no playable-moment pointer
→ frontend preserves legacy choices and Continue behavior
→ post-player response records source_prompt_id
```

---

## 29. Deferred features

The implementation intentionally does not include:

- quick cut;
- deep cut;
- multiple simultaneous cuts;
- multiple simultaneous runtime prompts;
- staged adaptation publication;
- atomic old/new adaptation swap;
- focused retrofit from old Phase 3-7 artifacts;
- arbitrary moment graph routing;
- child-room graphs;
- cross-session moment graphs;
- session receipts;
- hands-free contracts;
- multiplayer contracts;
- advanced replay analytics;
- variable canonical A/B/C option count;
- mid-session cut switching;
- automatic mid-session migration;
- forced advancement based only on turn count;
- creator compaction slider.

The schema does not reserve speculative fields for these features.

---

## 30. Deployment and verification handoff

Before deployment:

1. Confirm no adaptation job is active.
2. Take a complete Laravel Cloud database snapshot.
3. Record the snapshot identifier in `BOILER_FULL_SCOPE_LOG.md`.
4. Review `git log --oneline pre-boiler..HEAD`.
5. Push only with explicit approval.

Laravel Cloud commands after deployment:

```bash
php artisan migrate --force
php artisan queue:restart
```

Testing commands:

```bash
php artisan test --filter=Boiler
php artisan test
npm run lint
npm run build
```

Canary:

```bash
php artisan stories:run-adaptation the-adventure-of-the-speckled-band --force --boiler
php artisan stories:boiler-status the-adventure-of-the-speckled-band
php artisan stories:inspect-boiler the-adventure-of-the-speckled-band --session=1
php artisan adaptation:export the-adventure-of-the-speckled-band
```

The canary should verify:

- story-wide boiling guidance;
- every-event coverage;
- moment-count agreement;
- author texture;
- semantic exits;
- zero-to-three suggestions;
- freeform exploration;
- world memory;
- consequence visibility;
- session close;
- final pointer preservation;
- next-session initialization;
- duplicate-turn rejection.

---

## 31. Rollback

The authoritative rollback commands and complete commit order are maintained in:

```text
docs/experiments/BOILER_FULL_SCOPE_LOG.md
```

Rollback principles:

- stop new Boiler dispatch;
- wait for or stop active Boiler jobs safely;
- use `git revert`, never hard reset shared `main`;
- roll migrations back in reverse FK order;
- re-run affected story adaptation so the cached prompt matches reverted code;
- reset test games;
- verify legacy start, choices, freeform, consequence, next-session, completion, and reset.

The source-of-truth rollback anchor is:

```text
tag: pre-boiler
SHA: 42d022984bdf77e6080f0fdb63de95ab19db160b
```

---

## 32. Final implementation state

Implemented:

- persisted adaptation-time Boiler mode;
- one canonical session-chain definition;
- story-wide compaction diagnosis;
- shared event loading;
- Opus Draft;
- event critique and role map;
- exact moment clustering;
- Phase 4-7 bindings;
- Opus Finalize;
- canonical scene cards;
- normalized cut and role tables;
- Boiler Editorial audit;
- strict prompt assembly;
- assembly-owned completion;
- Boiler-aware reconciliation;
- active room runtime;
- semantic model-judged exits;
- hidden bounded player critique;
- zero-to-three contextual suggestions;
- stable canonical click identity;
- server-side turn claim;
- durable prompt idempotency;
- atomic moment/state/response transaction;
- sequential relational progression;
- shared reset service;
- frontend mode discrimination;
- inspection/status commands;
- creator visibility;
- exports;
- rollback documentation;
- unit and feature regression tests.

Not executed by the implementing agent:

- database migrations;
- test suite;
- frontend build;
- lint command;
- Artisan commands;
- adaptation queue;
- canary adaptation;
- Laravel Cloud deployment;
- Git push.

The implementation is complete at source level and awaits Daniel-run runtime verification and Laravel Cloud deployment steps.
