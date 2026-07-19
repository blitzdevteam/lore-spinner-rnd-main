# Boiler Pacing Engine — Validation and Canary Runbook

Companion process report: `../BOILER_IMPLEMENTATION_PROCESS_LOG.md`\
Rollback reference: `../BOILER_FULL_SCOPE_LOG.md`

This runbook is written for the current Laravel Cloud deployment model:

- `main` is the connected deployment branch;
- pushing `main` triggers deployment;
- Laravel Cloud commands are run by Daniel from the Cloud command UI;
- adaptation jobs run on the `adaptation` queue;
- do not deploy while an adaptation run is active.

## Recommended order

Do **not** start with the Cloud canary.

Use this order:

```text
1. Local/CI validation
2. Confirm adaptation queue is idle
3. Take database snapshot
4. Push/deploy
5. Run migrations
6. Restart queue workers
7. Verify schema and commands
8. Run one Cloud canary adaptation
9. Inspect artifacts
10. Play the runtime canary
```

The agent can run local validation after Daniel explicitly authorizes project commands. Daniel must perform Laravel Cloud deployment, backup, migration, queue, and canary commands.

---

## Run record

Fill this before starting.

```text
Operator:
Date:
Environment:
Canary story:
Canary slug:
Pre-deploy commit:
Laravel Cloud deployment ID:
Database snapshot ID:
Queue confirmed idle: YES / NO
```

---

# Phase A — Local or CI validation

Run Phase A before pushing to Laravel Cloud.

## A0. Restore locked frontend dependencies

**Where:** local or CI

```bash
npm ci
```

**Expected:**

- Install completes from `package-lock.json`.
- No `package.json` or `package-lock.json` change.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## A1. Confirm Boiler commit range

**Where:** local repository

```bash
git log --oneline pre-boiler..HEAD
```

**Expected:**

- Boiler schema, pipeline, Draft, Phase bindings, Finalize, Assembly, runtime, frontend, diagnostics, tests, and documentation commits appear.
- `pre-boiler` resolves to `42d022984bdf77e6080f0fdb63de95ab19db160b`.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## A2. Confirm only intended source changes are committed

**Where:** local repository

```bash
git diff --check pre-boiler..HEAD -- . ':(exclude)docs/**'
```

**Expected:**

- No output.
- Exit status `0`.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## A3. Run Boiler-focused PHP tests

**Where:** local or CI testing environment

```bash
php artisan test --filter=Boiler
```

**Expected:**

- All Boiler unit tests pass.
- All Boiler feature tests pass.
- No database connection points at production.
- No failed assertions or exceptions.

The suite covers:

- six-state Boiler lifecycle;
- Opus model configuration;
- disabled-schema compatibility;
- Draft and Finalize validators;
- pipeline order;
- FK deletion behavior;
- prompt validation;
- choice normalization;
- duplicate-turn idempotency;
- resolver progression;
- resolver rollback;
- opening footnote contract;
- final pointer preservation;
- shared reset behavior.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## A4. Run full PHP regression suite

**Where:** local or CI testing environment

```bash
php artisan test
```

**Expected:**

- Existing analytics, authentication, model, Chaos state-merge, and Boiler tests pass.
- No legacy-story regression.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## A5. Run frontend lint

**Where:** local or CI

```bash
npx eslint .
```

**Expected:**

- No TypeScript or Vue lint errors.
- `PromptChoice` union is accepted.
- `uses_playable_moments` is accepted.
- `choice-selected` event signature is accepted in gameplay and R&D preview pages.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## A6. Build frontend

**Where:** local or CI

```bash
npm run build
```

**Expected:**

- Production frontend build completes.
- No missing imports.
- No Vue template type errors.
- No Wayfinder request payload errors.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## Phase A gate

Do not deploy until all Phase A checks pass.

```text
A0 Dependencies: PASS / FAIL
A1 Commit range: PASS / FAIL
A2 Diff check: PASS / FAIL
A3 Boiler tests: PASS / FAIL
A4 Full tests: PASS / FAIL
A5 Frontend lint: PASS / FAIL
A6 Frontend build: PASS / FAIL

PHASE A: PASS / FAIL
```

---

# Phase B — Laravel Cloud pre-deploy safety

## B0. Confirm the queue lease exceeds every adaptation timeout

**Where:** Laravel Cloud command UI, before dispatching any adaptation

```bash
php artisan tinker --execute='echo "database_retry_after=".config("queue.connections.database.retry_after").PHP_EOL;'
```

**Expected:**

```text
database_retry_after=1200
```

The longest adaptation job timeout is 900 seconds. Never run the canary with a 90-second lease: a still-running AI job would become available to another worker, allowing concurrent execution of the same UUID.

If Cloud still prints `90`, set this environment value, redeploy, and restart workers:

```text
DB_QUEUE_RETRY_AFTER=1200
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## B1. Confirm adaptation queue is idle

**Where:** Laravel Cloud command UI, before push/deploy

```bash
php artisan tinker --execute='echo "adaptation_jobs=" . DB::table("jobs")->where("queue","adaptation")->count() . PHP_EOL; echo "failed_jobs=" . DB::table("failed_jobs")->count() . PHP_EOL;'
```

**Expected:**

```text
adaptation_jobs=0
```

`failed_jobs` may contain historical failures. Record the number before deployment so new failures are distinguishable.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## B2. Take complete database snapshot

**Where:** Laravel Cloud database/backups UI

**Action:**

Create a complete database snapshot before migrations.

**Expected:**

- Snapshot completed successfully.
- Snapshot predates all Boiler migrations.
- Snapshot identifier is recorded.

**Paste snapshot record:**

```text
Snapshot ID:
Snapshot timestamp:
Database:
Status:
```

**Result:** PASS / FAIL

---

## B3. Record deployment target

**Where:** local repository

```bash
git rev-parse HEAD
```

**Expected:**

- SHA matches the exact commit intended for Cloud.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## Phase B gate

```text
B0 Queue lease: PASS / FAIL
B1 Queue idle: PASS / FAIL
B2 Database snapshot: PASS / FAIL
B3 Deployment SHA recorded: PASS / FAIL

PHASE B: PASS / FAIL
```

Do not push while `adaptation_jobs` is greater than zero.

---

# Phase C — Deploy and migrate

## C1. Push and wait for Laravel Cloud deployment

**Where:** local repository and Laravel Cloud UI

Push only after explicit approval:

```bash
git push origin main
```

**Expected:**

- Laravel Cloud detects the push.
- Build succeeds.
- Deployment reaches healthy state.
- No adaptation job was interrupted.

**Paste result:**

```text
Deployment ID:
Deployment status:
Deployed SHA:
Build duration:
Relevant log output:
```

**Result:** PASS / FAIL

---

## C2. Run Boiler migrations

**Where:** Laravel Cloud command UI

```bash
php artisan migrate --force
```

**Expected:**

The following six migrations run successfully:

```text
2026_07_19_000001_add_boiler_adaptation_columns
2026_07_19_000002_create_playable_session_cuts_table
2026_07_19_000003_create_playable_moments_table
2026_07_19_000004_create_event_playability_roles_table
2026_07_19_000005_add_boiler_runtime_columns_to_games_table
2026_07_19_000006_add_source_prompt_id_to_prompts_table
```

No FK or duplicate-index error should appear.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## C3. Confirm migration status

**Where:** Laravel Cloud command UI

```bash
php artisan migrate:status
```

**Expected:**

- All six Boiler migrations show `Ran`.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## C4. Restart queue workers

**Where:** Laravel Cloud command UI

```bash
php artisan queue:restart
```

**Expected:**

- Command succeeds.
- Adaptation workers restart with the new Boiler job classes.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## C5. Verify Boiler Artisan commands exist

**Where:** Laravel Cloud command UI

```bash
php artisan list stories
```

**Expected output includes:**

```text
stories:run-adaptation
stories:boiler-status
stories:inspect-boiler
```

**Paste result:**

```text
Available commands for the "stories" namespace:
  stories:boiler-status       Show Boiler compilation status for a story
  stories:convert-scripts     Convert screenplay PDF scripts to .txt files for the extraction pipeline
  stories:generate-openings   Generate cinematic Lorespinner opening narrations for stories.
  stories:inspect-boiler      Inspect Boiler event roles and canonical moment cards
  stories:repair-ip-trimming  Re-run IP trimming for stories with missing chapter_segments and optionally refresh affected sessions
  stories:run-adaptation      Dispatch the adaptation pipeline for a story
  stories:wipe                Remove ALL stories and their related data (games, chapters, events, adaptations, media)
```

**Result:** PASS / FAIL

---

## C6. Verify new schema columns

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='foreach(["story_adaptations.boiler_enabled","session_adaptations.boiler_draft","session_adaptations.boiler_final_cut","session_adaptations.boiler_status","games.current_playable_session_cut_id","games.current_playable_moment_id","games.playable_moment_turn_count","games.moment_footnote","prompts.source_prompt_id"] as $c){ [$t,$n]=explode(".",$c); echo $c."=".(Schema::hasColumn($t,$n)?"present":"MISSING").PHP_EOL; } foreach(["playable_session_cuts","playable_moments","event_playability_roles"] as $t){ echo $t."=".(Schema::hasTable($t)?"present":"MISSING").PHP_EOL; }'
```

**Expected:**

- Every line ends with `present`.

**Paste result:**

```text
story_adaptations.boiler_enabled=present
session_adaptations.boiler_draft=present
session_adaptations.boiler_final_cut=present
session_adaptations.boiler_status=present
games.current_playable_session_cut_id=present
games.current_playable_moment_id=present
games.playable_moment_turn_count=present
games.moment_footnote=present
prompts.source_prompt_id=present
playable_session_cuts=present
playable_moments=present
event_playability_roles=present
```

**Result:** PASS / FAIL

---

## Phase C gate

```text
C1 Deploy healthy: PASS / FAIL
C2 Migrations: PASS / FAIL
C3 Migration status: PASS / FAIL
C4 Queue restart: PASS / FAIL
C5 Commands registered: PASS / FAIL
C6 Schema present: PASS / FAIL

PHASE C: PASS / FAIL
```

Do not dispatch the canary until Phase C passes.

---

# Phase D — Pre-canary legacy safety

Before enabling Boiler on a story, verify that an existing disabled story remains legacy-compatible.

## D0. Find and record the Sherlock slug

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='foreach(App\Models\Story::where("title","like","%Sherlock%")->orWhere("title","like","%Speckled%")->get(["id","title","slug"]) as $s){ echo $s->id." | ".$s->title." | ".$s->slug.PHP_EOL; }'
```

**Expected:**

- The intended Sherlock QA story appears.
- Copy its exact slug into the run record.
- Replace every `YOUR_SHERLOCK_SLUG` below with that value.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## D1. Check an existing disabled adaptation

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","the-adventure-of-the-speckled-band")->firstOrFail(); $a=$s->adaptation; echo "adaptation=".($a?"present":"missing").PHP_EOL; echo "boiler_enabled=".($a?->boiler_enabled?"true":"false").PHP_EOL; echo "status=".($a?->adaptation_status?->value??"missing").PHP_EOL; echo "sessions=".($a?->sessionAdaptations()->count()??0).PHP_EOL;'
```

**Expected before canary re-adaptation:**

```text
adaptation=present
boiler_enabled=false
```

Existing prompt-backed games should still open in legacy mode until re-adaptation starts.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## D2. Check disabled runtime prompt has no Boiler markers

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail(); foreach($s->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $p=(string)$sa->runtime_narrator_prompt; echo "session=".$sa->session_number." boiler_marker=".substr_count($p,"[BOILER_RUNTIME_V1]")." active_token=".substr_count($p,"[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]")." legacy_section=".(str_contains($p,"SECTION 12: FULL CURRENT EPISODE SCRIPT")?"yes":"no").PHP_EOL; }'
```

**Expected for a currently legacy adaptation:**

```text
boiler_marker=0
active_token=0
legacy_section=yes
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## Phase D gate

```text
D1 Disabled adaptation intact: PASS / FAIL
D2 Legacy prompt intact: PASS / FAIL

PHASE D: PASS / FAIL
```

---

# Phase E — Dispatch one Boiler canary

Use one story only. Sherlock is the recommended first canary because it is a novelist baseline with clear clue, pressure, and investigation moments.

## E1. Record pre-canary game count

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail(); echo "story_id=".$s->id.PHP_EOL; echo "games=".$s->games()->count().PHP_EOL; echo "prompts=".App\Models\Prompt::whereIn("game_id",$s->games()->pluck("id"))->count().PHP_EOL;'
```

**Expected:**

- Counts print successfully.
- Record them because successful reconciliation resets existing games.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## E2. Dispatch full Boiler adaptation

**Where:** Laravel Cloud command UI

```bash
php artisan stories:run-adaptation the-adventure-of-the-speckled-band --force --boiler
```

**Expected:**

```text
Boiler pacing: enabled
Job dispatched to the [adaptation] queue.
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## E3. Confirm queue received work

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='echo "adaptation_jobs=".DB::table("jobs")->where("queue","adaptation")->count().PHP_EOL;'
```

**Expected:**

- The count may be greater than zero while workers process.
- It eventually returns to zero.

**Paste first result:**

```text

```

**Paste drained result:**

```text

```

**Result:** PASS / FAIL

---

## E4. Monitor adaptation and Boiler states

**Where:** Laravel Cloud command UI

Run repeatedly while the queue works:

```bash
php artisan stories:boiler-status YOUR_SHERLOCK_SLUG
```

**Expected progression:**

```text
Story adaptation:
pending
→ ip-trimming
→ format-detection
→ ip-audit
→ voice-lock
→ story-session-map
→ adapting-sessions
→ completed

Each Boiler session:
pending
→ drafting
→ drafted
→ finalizing
→ finalized

Each session:
pending
→ entry-point-diagnosis
→ session-architecture
→ choice-design
→ consequence-mapping
→ session-close
→ editorial-verification
→ runtime-assembly
→ completed
```

**Paste progress result 1:**

```text

```

**Paste progress result 2:**

```text

```

**Paste final result:**

```text

```

**Result:** PASS / FAIL

---

## E5. Check failed jobs

**Where:** Laravel Cloud command UI

```bash
php artisan queue:failed
```

**Expected:**

- No new Boiler Draft, Boiler Finalize, Editorial, or Runtime Assembly failure.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## Phase E gate

```text
E1 Baseline recorded: PASS / FAIL
E2 Dispatch succeeded: PASS / FAIL
E3 Queue drained: PASS / FAIL
E4 Adaptation completed: PASS / FAIL
E5 No new failed job: PASS / FAIL

PHASE E: PASS / FAIL
```

Stop here if the adaptation is not fully completed.

---

# Phase F — Validate story-wide boiling guidance

## F1. Inspect boiling profile structure

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail()->adaptation; $p=$a->story_session_map["boiling_profile"]??[]; echo "boiler_enabled=".($a->boiler_enabled?"true":"false").PHP_EOL; echo "compaction_level=".($p["compaction_level"]??"MISSING").PHP_EOL; echo "target_total_minutes=".($p["target_total_playtime_minutes"]??"MISSING").PHP_EOL; echo "allocation_count=".count($a->story_session_map["session_allocation"]??[]).PHP_EOL; echo "guidance_count=".count($p["per_session_guidance"]??[]).PHP_EOL; foreach($p["per_session_guidance"]??[] as $g){ echo "session=".$g["session_number"]." minutes=".$g["target_playtime_minutes"]." moment_range=".$g["recommended_moment_count_min"]."-".$g["recommended_moment_count_max"]." compress=".count($g["must_compress_event_ids"]??[])." breathe=".count($g["must_breathe_event_ids"]??[]).PHP_EOL; }'
```

**Expected:**

- `boiler_enabled=true`
- compaction is `light`, `balanced`, or `strong`
- total minutes is positive
- allocation count equals guidance count
- every session has positive target minutes
- every moment range is positive and minimum is not greater than maximum

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## F2. Verify total-minute reconciliation

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail()->adaptation; $p=$a->story_session_map["boiling_profile"]; $sum=array_sum(array_column($p["per_session_guidance"],"target_playtime_minutes")); $total=$p["target_total_playtime_minutes"]; $sessions=count($p["per_session_guidance"]); echo "total=".$total.PHP_EOL; echo "session_sum=".$sum.PHP_EOL; echo "difference=".abs($sum-$total).PHP_EOL; echo "allowed=".$sessions.PHP_EOL; echo (abs($sum-$total)<=$sessions?"PASS":"FAIL").PHP_EOL;'
```

**Expected final line:**

```text
PASS
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

# Phase G — Validate per-session artifacts

## G1. Verify Draft, Finalize, normalized rows, and prompt

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail(); $a=$story->adaptation; foreach($a->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $cut=$sa->playableCut()->withCount(["moments","eventRoles"])->first(); $assigned=$story->events()->where("session_number",$sa->session_number)->count(); echo "session=".$sa->session_number." session_status=".$sa->session_status->value." boiler_status=".$sa->boiler_status->value." draft=".(!empty($sa->boiler_draft)?"present":"MISSING")." manifest=".(!empty($sa->boiler_final_cut)?"present":"MISSING")." cut=".($cut?->status??"MISSING")." moments=".($cut?->moments_count??0)." roles=".($cut?->event_roles_count??0)." assigned_events=".$assigned." prompt_chars=".strlen((string)$sa->runtime_narrator_prompt).PHP_EOL; }'
```

**Expected for every session:**

```text
session_status=completed
boiler_status=finalized
draft=present
manifest=present
cut=finalized
moments>0
roles=assigned_events
prompt_chars>0
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## G2. Verify Draft budget agreement

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail()->adaptation; $guidance=collect($a->story_session_map["boiling_profile"]["per_session_guidance"])->keyBy("session_number"); foreach($a->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $g=$guidance[$sa->session_number]; $d=$sa->boiler_draft["session_budget_decision"]; $actual=count($sa->boiler_draft["moment_drafts"]??[]); echo "session=".$sa->session_number." map_minutes=".$g["target_playtime_minutes"]." draft_minutes=".$d["target_playtime_minutes"]." range=".$g["recommended_moment_count_min"]."-".$g["recommended_moment_count_max"]." target=".$d["target_moment_count"]." drafted=".$actual." ".(($g["target_playtime_minutes"]===$d["target_playtime_minutes"] && $d["target_moment_count"]===$actual)?"PASS":"FAIL").PHP_EOL; }'
```

**Expected:**

- Every row ends with `PASS`.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## G3. Verify one event role per assigned event

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail(); foreach($story->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $cut=$sa->playableCut; $assigned=$story->events()->where("session_number",$sa->session_number)->pluck("events.id")->map(fn($id)=>(int)$id)->sort()->values()->all(); $roles=$cut->eventRoles()->pluck("event_id")->map(fn($id)=>(int)$id)->sort()->values()->all(); echo "session=".$sa->session_number." assigned=".count($assigned)." roles=".count($roles)." unique=".count(array_unique($roles))." coverage=".($assigned===$roles?"PASS":"FAIL").PHP_EOL; }'
```

**Expected:**

- `assigned`, `roles`, and `unique` counts match.
- `coverage=PASS`.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## G4. Verify sequential moment order

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail()->adaptation; foreach($a->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $moments=$sa->playableCut->moments()->orderBy("moment_order")->get(); $ok=true; foreach($moments as $i=>$m){ $expected=$i+1; $next=$moments[$i+1]->moment_key??null; $cardNext=$m->scene_card["next_moment_key"]??null; if($m->moment_order!==$expected || $cardNext!==$next){$ok=false;} echo "session=".$sa->session_number." order=".$m->moment_order." key=".$m->moment_key." anchor=".($m->anchor_event_id??"null")." next=".($cardNext??"final").PHP_EOL; } echo "session=".$sa->session_number." sequential=".($ok?"PASS":"FAIL").PHP_EOL; }'
```

**Expected:**

- Orders begin at `1` and remain contiguous.
- Every non-final card points to the next printed key.
- Final card prints `next=final`.
- Every session prints `sequential=PASS`.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## G5. Verify phase bindings

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail()->adaptation; foreach($a->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $keys=$sa->playableCut->moments()->pluck("moment_key")->all(); $beatKeys=collect($sa->session_architecture["beat_map"]??[])->pluck("moment_key")->filter()->all(); $choiceKeys=collect(array_merge($sa->session_choice_design["branching_choices"]??[],$sa->session_choice_design["emotional_choices"]??[],$sa->session_choice_design["posture_shifts"]??[]))->pluck("moment_key")->filter()->all(); $close=$sa->session_close_design["closing_moment_key"]??null; $invalid=array_values(array_diff(array_merge($beatKeys,$choiceKeys,[$close]),$keys)); echo "session=".$sa->session_number." moments=".count($keys)." beat_bindings=".count($beatKeys)." choice_bindings=".count($choiceKeys)." close=".$close." invalid=".json_encode($invalid)." ".($invalid===[]?"PASS":"FAIL").PHP_EOL; }'
```

**Expected:**

- Every session ends with `PASS`.
- `invalid=[]`.
- `close` equals the final ordered moment key.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## G6. Verify Boiler Editorial audit

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail()->adaptation; foreach($a->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $audit=$sa->editorial_verification["boiler_audit"]??[]; echo "session=".$sa->session_number." boiler_audit=".($audit["production_status"]??"MISSING")." revisions=".count($audit["revision_instructions"]??[])." final_verdict=".($sa->editorial_verification["final_verdict"]["production_status"]??"MISSING").PHP_EOL; }'
```

**Expected:**

```text
boiler_audit=PASS
revisions=0
```

Record any legacy `final_verdict` status separately.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## G7. Inspect complete session 1 cut

**Where:** Laravel Cloud command UI

```bash
php artisan stories:inspect-boiler YOUR_SHERLOCK_SLUG --session=1
```

**Expected:**

- Every event has one role and reason.
- PLAY/PULSE/CHOICE events point to a moment.
- CUT/MONTAGE protected events have reintroduction notes.
- Moments are ordered.
- Every moment has source and anchor events.
- Goals, desires, pressure, extracts, exit conditions, and turn budgets are readable.
- Final moment has no next key.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

# Phase H — Validate runtime prompt

## H1. Verify Boiler markers and legacy-section removal

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail()->adaptation; foreach($a->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $p=(string)$sa->runtime_narrator_prompt; echo "session=".$sa->session_number." chars=".strlen($p)." boiler_marker=".substr_count($p,"[BOILER_RUNTIME_V1]")." active_token=".substr_count($p,"[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]")." opening_token=".substr_count($p,"[OPENING_SCENE_INJECTION_POINT]")." world_token=".substr_count($p,"[WORLD_STATE_TIERED_INJECTION_POINT]")." consequence_token=".substr_count($p,"[CONSEQUENCE_ECHO_INJECTION_POINT]")." close_token=".substr_count($p,"[SESSION_CLOSE_INJECTION_POINT]")." legacy_section=".(str_contains($p,"SECTION 12: FULL CURRENT EPISODE SCRIPT")?"YES":"no").PHP_EOL; }'
```

**Expected for every session:**

```text
chars>0
boiler_marker=1
active_token=1
opening_token=1
world_token=1
consequence_token=1
close_token=1
legacy_section=no
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## H2. Verify Boiler instruction blocks

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$sa=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail()->adaptation->sessionAdaptations()->orderBy("session_number")->first(); $p=(string)$sa->runtime_narrator_prompt; foreach(["SECTION 12: BOILER PLAYABLE SESSION MAP","ORDERED MOMENTS:","BOILER MOMENT_PROGRESS CONTRACT","BOILER PLAYER-CRITIQUE FOOTNOTE","BOILER VISIBLE_SUGGESTIONS CONTRACT"] as $m){ echo $m."=".(str_contains($p,$m)?"present":"MISSING").PHP_EOL; }'
```

**Expected:**

- Every line ends with `present`.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

# Phase I — Validate successful re-adaptation reset

## I1. Check prior games after successful reconciliation

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","YOUR_SHERLOCK_SLUG")->firstOrFail(); foreach($s->games()->orderBy("id")->get() as $g){ echo "game=".$g->id." session=".$g->current_session_number." cycle=".$g->current_story_cycle_number." prompts=".$g->prompts()->count()." complete=".($g->current_session_complete?"true":"false")." cut=".($g->current_playable_session_cut_id??"null")." moment=".($g->current_playable_moment_id??"null")." footnote=".($g->moment_footnote===null?"null":"present")." completed_at=".($g->completed_at??"null").PHP_EOL; }'
```

**Expected for every existing game after successful re-adaptation:**

```text
session=1
prompts=0
complete=false
cut=null
moment=null
footnote=null
completed_at=null
```

The story cycle number should have incremented once.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

# Phase J — Runtime UI canary

Use a test user and a fresh/reset Sherlock game.

## J1. Opening narration

**Action:**

Open Sherlock and start the game.

**Expected UI:**

- Opening narration appears.
- Zero to three suggestions are visible.
- Freeform input is available.
- No unconditional Boiler Continue pill appears.
- Suggestions read like contextual actions rather than three forced alignment labels.

**Paste observations:**

```text
Opening response:

Visible suggestions:

Continue pill present: YES / NO
Freeform available: YES / NO
Voice observations:
Pacing observations:
```

**Result:** PASS / FAIL

---

## J2. Opening database state

**Where:** Laravel Cloud command UI

Replace `{game-ulid}` with the test game ULID:

```bash
php artisan tinker --execute='$g=App\Models\Game::findOrFail("{game-ulid}"); $m=$g->currentPlayableMoment; echo "uses_playable_moments=".($g->current_playable_moment_id?"true":"false").PHP_EOL; echo "session=".$g->current_session_number.PHP_EOL; echo "cut=".$g->current_playable_session_cut_id.PHP_EOL; echo "moment_id=".$g->current_playable_moment_id.PHP_EOL; echo "moment_key=".($m?->moment_key??"MISSING").PHP_EOL; echo "turn_count=".$g->playable_moment_turn_count.PHP_EOL; echo "footnote=".($g->moment_footnote===null?"null":"UNEXPECTED").PHP_EOL; echo "prompt_count=".$g->prompts()->count().PHP_EOL;'
```

**Expected:**

```text
uses_playable_moments=true
session=1
cut=<non-null>
moment_id=<non-null>
moment_key=S1_M01
turn_count=0
footnote=null
prompt_count=1
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## J3. Freeform exploration turn

**Action:**

Type a freeform action that explores the room without immediately resolving its pressure.

Example:

```text
Before answering Holmes, I study the mud on Helen's sleeve and ask where the carriage stopped.
```

**Expected:**

- Narration recognizes the exact action.
- A clue, resistance, relationship shift, consequence, or pressure moves the room forward.
- Narration does not repeat the opening.
- Suggestions update contextually.
- Moment may remain active if meaningful material remains.

**Paste observations:**

```text
Player action:

Narrator response:

Recognition:
Consequence/revelation:
Propulsion:
Repeated material:
Moment appeared to retain or advance:
```

**Result:** PASS / FAIL

---

## J4. Freeform database state and footnote

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$g=App\Models\Game::findOrFail("{game-ulid}"); $f=$g->moment_footnote??[]; echo "moment_key=".$g->currentPlayableMoment?->moment_key.PHP_EOL; echo "turn_count=".$g->playable_moment_turn_count.PHP_EOL; echo "footnote_key=".($f["moment_key"]??"MISSING").PHP_EOL; echo "footnote_words=".str_word_count($f["text"]??"").PHP_EOL; echo "source_prompt_id=".($f["source_prompt_id"]??"MISSING").PHP_EOL; echo "response_links=".$g->prompts()->whereNotNull("source_prompt_id")->count().PHP_EOL;'
```

**Expected:**

- footnote key matches active moment;
- footnote words are `1-30`;
- source prompt ID is present;
- exactly one new response link was created for the submitted turn.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## J5. Mapped suggestion turn

**Action:**

Click a visible suggestion that represents a canonical branch.

**Expected:**

- UI sends stable suggestion identity.
- Narrator resolves the selected path.
- Game stores matching `defining_choice_id` and A/B/C label.
- World response reflects the path.
- No fuzzy text matching is required.

**Paste observations:**

```text
Clicked suggestion:
Expected choice ID/path:
Narrator response:
World-noticed signal:
```

**Result:** PASS / FAIL

---

## J6. Verify canonical branch persistence

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$g=App\Models\Game::findOrFail("{game-ulid}"); echo "choice_id=".($g->defining_choice_id??"null").PHP_EOL; echo "path_label=".($g->defining_path_label??"null").PHP_EOL; echo "defining_line=".($g->defining_choice_line??"null").PHP_EOL;'
```

**Expected:**

- `choice_id` matches the clicked suggestion.
- `path_label` is A, B, or C.
- defining line matches authored Phase 5 option.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## J7. Verify no duplicate response links

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$g=App\Models\Game::findOrFail("{game-ulid}"); $dupes=$g->prompts()->whereNotNull("source_prompt_id")->select("source_prompt_id",DB::raw("count(*) as total"))->groupBy("source_prompt_id")->havingRaw("count(*) > 1")->get(); echo "duplicate_sources=".$dupes->count().PHP_EOL; foreach($dupes as $d){ echo $d->source_prompt_id."=".$d->total.PHP_EOL; }'
```

**Expected:**

```text
duplicate_sources=0
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## J8. Manual concurrency check

**Action:**

Using two browser tabs on the same test game:

1. Load the same unanswered card in both tabs.
2. Submit an answer from both tabs as close together as possible.

**Expected:**

- One request is accepted.
- The second reports that the turn is already processing or no longer awaiting an answer.
- One narrator response row is created.
- Moment turn count increments once.
- Moment advances at most once.

**Paste observations:**

```text
Tab 1 result:
Tab 2 result:
Response rows added:
Turn-count change:
Moment before:
Moment after:
```

**Result:** PASS / FAIL

---

## J9. Natural moment advancement

**Action:**

Continue acting until the current room's dramatic pressure is genuinely resolved, transformed, bypassed, or made obsolete.

**Expected:**

- The same response closes the prior room and establishes the next room.
- Moment pointer advances by exactly one relational order.
- Turn count resets to zero.
- Suggestions belong to the resulting moment.
- No separate transition model call occurs.

**Paste observations:**

```text
Moment before:
Resolving player action:
Transition response:
Moment after:
Turn count after:
Suggestions fit new moment: YES / NO
```

**Result:** PASS / FAIL

---

## J10. Verify advancement state

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$g=App\Models\Game::findOrFail("{game-ulid}"); $m=$g->currentPlayableMoment; echo "moment_order=".$m?->moment_order.PHP_EOL; echo "moment_key=".$m?->moment_key.PHP_EOL; echo "anchor_event_id=".($m?->anchor_event_id??"null").PHP_EOL; echo "turn_count=".$g->playable_moment_turn_count.PHP_EOL; echo "footnote_key=".($g->moment_footnote["moment_key"]??"null").PHP_EOL; echo "session_complete=".($g->current_session_complete?"true":"false").PHP_EOL;'
```

**Expected after non-final advancement:**

- moment order increased by exactly one;
- turn count is `0`;
- footnote key matches new moment;
- session complete is `false`.

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## J11. Final-moment completion

**Action:**

Play through the final ordered moment and fulfill its dramatic exit.

**Expected:**

- Session becomes complete only after final moment exit.
- Final cut and moment pointers remain non-null.
- Boiler UI does not fall back to universal legacy Continue.
- Next-session button appears.

**Paste observations:**

```text
Final moment key:
Final player action:
Final narrator response:
Session complete shown: YES / NO
Legacy Continue shown: YES / NO
Next-session button shown: YES / NO
```

**Result:** PASS / FAIL

---

## J12. Verify final pointer preservation

**Where:** Laravel Cloud command UI

```bash
php artisan tinker --execute='$g=App\Models\Game::findOrFail("{game-ulid}"); echo "session_complete=".($g->current_session_complete?"true":"false").PHP_EOL; echo "cut=".($g->current_playable_session_cut_id??"null").PHP_EOL; echo "moment=".($g->current_playable_moment_id??"null").PHP_EOL; echo "moment_key=".($g->currentPlayableMoment?->moment_key??"null").PHP_EOL;'
```

**Expected:**

```text
session_complete=true
cut=<non-null>
moment=<non-null>
moment_key=<final session moment>
```

**Paste result:**

```text

```

**Result:** PASS / FAIL

---

## J13. Start next session

**Action:**

Click the next-session button.

**Expected:**

- Next session initializes before narration.
- New cut and first moment pointers are set.
- Turn count is zero.
- Footnote is null.
- Carried world, memory, alignment, and branch identity remain coherent.
- Opening does not advance before player input.

**Paste observations:**

```text
Previous session:
New session:
New first moment:
Opening response:
Suggestions:
Carried consequence observed:
```

**Result:** PASS / FAIL

---

## J14. Reset test game

**Action:**

Use the gameplay reset control.

**Expected:**

- prompts deleted;
- session reset to 1;
- story cycle increments;
- completion false;
- cut pointer null;
- moment pointer null;
- turn count zero;
- footnote null;
- world, memory, branch, and completion state cleared.

**Paste observations:**

```text
Reset succeeded:
Prompt count:
Session:
Cycle:
Cut:
Moment:
Turn count:
Footnote:
```

**Result:** PASS / FAIL

---

# Phase K — Log review

## K1. Search adaptation logs

**Where:** Laravel Cloud log viewer

Search for:

```text
boiler.draft_started
boiler.draft_validation_failed
boiler.draft_corrected
boiler.draft_completed
boiler.draft_attempt_failed
boiler.finalize_started
boiler.finalize_validation_failed
boiler.finalize_corrected
boiler.finalize_completed
boiler.finalize_attempt_failed
runtime_narrator_assembly.assembled
runtime_narrator_assembly.failed
```

**Expected:**

- Every session has Draft started/completed.
- A validation failure is followed by `boiler.draft_corrected` in the same job attempt or by a terminal Draft failure; it must not silently continue.
- Finalize choice IDs, moment references, next keys, and event roles are canonically overlaid from upstream artifacts before validation.
- Any remaining Finalize validation failure is followed by `boiler.finalize_corrected` in the same job attempt or by a terminal Finalize failure.
- Every session has Finalize started/completed.
- Every session has Assembly success.
- No terminal failure remains.

**Paste relevant log lines:**

```text

```

**Result:** PASS / FAIL

---

## K2. Search runtime logs

**Where:** Laravel Cloud log viewer

Search for:

```text
boiler.moment_turn_committed
boiler.explicit_branch_model_mismatch
boiler.moment_footnote_truncated
game.turn_failed
```

**Expected:**

- committed events identify game/session/moment/source prompt;
- no unexplained stale or duplicate commit;
- branch mismatch warnings are absent or investigated;
- footnote truncation is occasional, not constant;
- no repeated game-turn failure.

**Paste relevant log lines:**

```text

```

**Result:** PASS / FAIL

---

# Phase L — Final sign-off

## Adaptation sign-off

```text
Boiler toggle persisted: PASS / FAIL
Boiling profile valid: PASS / FAIL
All sessions guided: PASS / FAIL
Every event covered once: PASS / FAIL
Moment count agreed: PASS / FAIL
Phase 4-7 bindings valid: PASS / FAIL
Final cuts normalized: PASS / FAIL
Editorial Boiler audits pass: PASS / FAIL
Runtime prompts structurally valid: PASS / FAIL
Story adaptation completed: PASS / FAIL
```

## Runtime sign-off

```text
Opening initializes first moment: PASS / FAIL
Opening has null footnote: PASS / FAIL
Zero-to-three suggestions render: PASS / FAIL
No unconditional Boiler Continue: PASS / FAIL
Freeform exploration works: PASS / FAIL
World notices player action: PASS / FAIL
Canonical mapped click persists: PASS / FAIL
Duplicate turn blocked: PASS / FAIL
Footnote bounded and replaced: PASS / FAIL
Moment retains naturally: PASS / FAIL
Moment advances sequentially: PASS / FAIL
Final moment completes session: PASS / FAIL
Final pointer preserved: PASS / FAIL
Next session initializes correctly: PASS / FAIL
Reset clears all state: PASS / FAIL
```

## Experience sign-off

```text
First agency arrives early: PASS / FAIL
Pacing is faster than legacy: PASS / FAIL
Rooms are explorable: PASS / FAIL
Narration avoids repetition: PASS / FAIL
Source voice remains specific: PASS / FAIL
Choices feel meaningful: PASS / FAIL
Freeform actions feel heard: PASS / FAIL
Consequences surface visibly: PASS / FAIL
Session ending feels earned: PASS / FAIL
Desire to continue improved: PASS / FAIL
Replayability improved: PASS / FAIL
```

## Final decision

```text
CANARY RESULT: PASS / FAIL / ITERATE

Decision:

Blocking issues:

Non-blocking observations:

Next action:
```

---

# Targeted Editorial repair

After deploying a fix for a persisted Boiler audit revision, rerun only the affected authoritative phase and its dependents:

```bash
# Architecture/pacing revision: reruns Phase 4 through Runtime Assembly.
php artisan stories:repair-boiler-editorial STORY_ID --session=2 --from=architecture

# Phase 5→6 consequence binding revision: canonicalizes Phase 6, then reruns Editorial and Assembly.
php artisan stories:repair-boiler-editorial STORY_ID --session=1 --from=consequence
```

The consequence repair reuses the existing Phase 6 prose when strict validation passes after canonicalization; otherwise it regenerates Phase 6 with corrective feedback. The command clears only stale downstream artifacts for that session, preserves the validated Draft, and finishes with adaptation reconciliation. Run it only while the `adaptation` queue is idle.

---

# Failure guide

| Symptom | Likely area | First action |
|---|---|---|
| Migration FK failure | Migration order or existing schema | Stop; do not dispatch canary; capture full migration output |
| Boiler commands missing | Deploy/autoload/command discovery | Confirm deployed SHA and queue restart |
| `boiler_enabled=false` after `--boiler` | Entry-point or job persistence | Inspect `RunAdaptationPipelineJob` payload and adaptation row |
| Missing `boiling_profile` | Conditional Story Session Map schema/prompt | Check StorySessionMap job logs and stored mode |
| Duplicate `failed_jobs` UUID or repeated overlapping starts | Queue lease shorter than job runtime | Pause workers; confirm `database_retry_after=1200`; clear only the affected pending queue work |
| Draft event assigned to multiple moments | Invalid model partition | Confirm corrective pass receives the validation error; inspect `boiler.draft_validation_failed` then `boiler.draft_corrected` |
| Draft promise arc references a missing moment | Arc field did not copy an exact generated key | Confirm corrective pass replaces shorthand/title with an exact `S{session}_M{nn}` key |
| Final scene card references an invalid or differently bound choice | Finalize copied a choice from the wrong moment | Deploy the Finalize canonicalizer; it derives exact `choice_ids` from Choice Design before validation |
| Finalize fails after canonical reference overlay | Invalid authored card content or budget | Inspect `boiler.finalize_validation_failed`; the bounded corrective pass must repair or fail terminally |
| Boiler audit says BREATH lands outside 10–13 minutes | Architecture accepted an invalid time range | Re-run from Session Architecture after deploying the deterministic 10:00–13:00 validator/corrective pass; inspect `boiler.architecture_validation_failed` |
| Boiler audit reports Phase 5/6 choice dimension or binding mismatch | Consequence Mapping reinterpreted Choice Design authority | Re-run Consequence Mapping after deploying canonicalization; inspect `boiler.consequence_mapping_validation_failed` and confirm exact IDs, dimensions, moments, A/B/C alignments, and defining lines |
| Story Session Map fails before sessions | Invalid profile structure | Inspect agent output and validation exception |
| Draft stuck `drafting` | Queue/API/structured output | Check failed jobs and `boiler_error` |
| Draft reports out-of-range count | Budget authority conflict | Inspect guidance range and Draft target; do not override manually |
| Phase 4 reports conflict | Architecture cannot honor Draft count | Review `boiler_budget_alignment.conflict` |
| Finalize stuck `finalizing` | Invalid binding/card/role normalization | Check `boiler_error` and finalizer logs |
| Event roles fewer than assigned events | Draft/Finalize coverage failure | Inspect role map and session event IDs |
| Final moment has next key | Finalizer contract failure | Inspect final scene card and validator output |
| Boiler audit `REVISE` | Experiential or structural weakness | Follow audit revision instructions; do not force completion |
| Session stuck `editorial-verification` | Assembly did not finish | Check Runtime Assembly failed job and prompt validator |
| Prompt missing marker | Boiler template branch not used | Verify persisted mode and finalized cut |
| Prompt still contains full script | Legacy Section 12 rendered | Stop canary; inspect builder mode branch |
| Opening fails on schema | Missing suggestions/progress/null footnote | Inspect structured narrator response |
| No suggestions and no input | Frontend regression | Verify freeform input remains enabled |
| Universal Continue appears | `uses_playable_moments` false or UI bug | Inspect game moment pointer and GameResource payload |
| Duplicate narrator responses | Claim/idempotency failure | Check cache driver and `source_prompt_id` rows |
| Moment skips two rooms | Resolver order bug | Capture current/next moment orders and source prompt ID |
| Stale footnote injected | Moment-key mismatch handling | Inspect `moment_footnote` and active moment key |
| Session completes early | Resolver final-moment authority failure | Inspect moment order, exit result, and pointer |
| Next session fails and old state disappears | Transition rollback failure | Capture game state before/after and error log |
| Re-adaptation resets games on failure | Reconciliation reset guard failure | Stop rollout and restore snapshot if necessary |

---

# Emergency stop and rollback

If the canary fails before production use:

1. Do not dispatch more Boiler adaptations.
2. Confirm adaptation queue state.
3. Preserve failed artifacts and logs for diagnosis.
4. Use the exact code and database rollback sequence in:

```text
docs/Boiler/BOILER_FULL_SCOPE_LOG.md
```

Do not hard-reset shared `main`.

Rollback uses:

```text
git revert commits in reverse dependency order
→ rollback six migrations in reverse FK order
→ restart queue workers
→ re-adapt affected stories in legacy mode
→ reset test games
→ verify legacy runtime
```

---

# Recommended operator split

## Agent

After explicit authorization, the agent can run:

```text
local Boiler tests
full local test suite
frontend lint
frontend build
static source diagnostics
failure diagnosis from pasted Cloud output
```

## Daniel

Daniel runs:

```text
Laravel Cloud database snapshot
push/deployment approval
Cloud migrations
Cloud queue restart
Cloud adaptation dispatch
Cloud status/inspection commands
Cloud UI canary
Cloud log review
production rollout decision
```

## Final recommendation

Run local validation first. Then run the Sherlock Cloud canary.

Do not use the Cloud canary as the first syntax, test, migration, or frontend-build validation.
