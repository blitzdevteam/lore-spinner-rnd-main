# Wasteland Boiler Pipeline Hardening — Process Log

**Implementation date:** 2026-08-04 → 2026-08-05
**Repository:** LoreSpinner R&D
**Implementation branch:** `main`
**Scope:** Boiler adaptation pipeline — Story Session Map constraints, Draft/Finalize validation, batch isolation, editorial severity, shared repair loop
**Trigger:** Wasteland (`story_id=30`) kept failing Boiler while Speckled Band completed cleanly. Cloud logs + `docs/Boiler/Debug/wasteland-boiler-opus-diagnostic.json` used as evidence.
**Commits:**

| Commit | Summary |
|---|---|
| `e582a10` | Block unsatisfiable session maps; document `gravity_after`; stop orphaned finalize from advancing editorial; `--from=draft` repair |
| `d46bda9` | `allowFailures` + cancelled guards; terminal semantic failures; shared validation loop; advisory editorial craft notes; manifest naming |

**Deployment state at handoff:** both commits pushed to `main` (Laravel Cloud auto-deploys).
**Verification state at handoff:** PHP lint clean; Pint clean on touched files; `tests/Unit/BoilerArtifactValidatorTest` + Boiler wiring contract tests pass except one pre-existing `GameplayChatCard.vue` string drift at `HEAD` (untouched by this work). Feature suite not run — local Postgres host `pgsql` unavailable.

---

## 1. Executive summary

Wasteland's Boiler failures looked like a pile of unrelated content bugs (`event 2225 assigned to more than one moment`, `target_moment_count outside range`, `invalid word or turn budgets`, editorial "provide Final Manifest…"). They were not. They were three mechanical pipeline defects that Speckled Band never hit because its Session Map stayed solvable:

1. **Unsatisfiable moment budgets** — Session 4 had 2 events and a recommended moment range of 4–6. Draft validation requires disjoint non-empty source sets, so every generation had to fail.
2. **Batch cancellation without job cooperation** — one failed session cancelled the whole multi-session batch and fired `finally` while siblings were still mid-chain; those siblings kept spending Opus calls into a dead batch.
3. **Orphaned Finalize success advancing Editorial** — a concurrent Finalize that could not claim the session returned normally, releasing chained Editorial against a null final cut; Editorial then reported missing artifacts as content defects.

A second pass then hardened the pipeline the same way earlier adaptation phases had been designed: one shared generate → validate → repair loop, terminal semantic failures, editorial craft notes that do not brick a structurally sound session, and honest naming for the final-cut manifest.

---

## 2. Evidence that pointed at the root (not the symptoms)

### 2.1 Alternating Draft errors on Session 4

Cloud log pattern (repeated for hours):

```text
ERROR Boiler event 2225 is assigned to more than one moment.
ERROR Boiler Draft target_moment_count is outside the approved Story Session Map range.
ERROR Boiler event 2226 does not bind back to moment S4_M02.
```

Diagnostic facts for Wasteland:

| Session | Events | SSM moment min–max | Draft outcome |
|---|---|---|---|
| 1 | 13 | 8–11 | Draft OK |
| 2 | 22 | 9–12 | Draft OK |
| 3 | 12 | 10–13 | Draft OK |
| 4 | **2** | **4–6** | Impossible |

`BoilerDraftValidator` requires:

- `target_moment_count` inside SSM min/max
- moment count === target
- every moment has ≥1 `source_event_ids`
- no event in more than one moment

With 2 events and min 4 moments, there is no legal draft. The model bounced between the only two escape hatches. Speckled Band never hit this cliff (S1: 21 events / min 11; S2: 11 events / min 9).

### 2.2 Batch rows cancelled with siblings still pending

From `wasteland-boiler-opus-diagnostic.json` `job_batches`:

| Batch | Total | Pending at cancel | Failed |
|---|---|---|---|
| `a26a3d29` | 36 | 17 | 5 |
| `a26a3a1b` | 36 | 36 | 0 |
| `a26a5acf` | 9 | 8 | 1 |

`SessionAdaptationChain` dispatched without `allowFailures()`. Laravel cancels the batch on first failure and stamps `finished_at`, which fires `AdaptationStatusReconciliationJob` while other sessions are still running. Phase-1 chapter jobs checked `$this->batch()?->cancelled()`; **session / Boiler jobs did not**, so cancelled batches kept burning model calls.

### 2.3 Finalize skip → Editorial "missing artifacts"

Log pairing:

```text
boiler.finalize_concurrent_skip  … DONE ~90ms
EditorialVerificationJob RUNNING
ERROR … Provide Final Manifest, Normalized Event Roles, and Canonical Final Playable Moments
```

`BoilerFinalizeJob` used to `return` on a failed claim. That is a *successful* job for the queue, so the chained Editorial job ran against `boiler_final_cut = null`. The audit then blamed content.

### 2.4 Hidden `gravity_after` invariant

`BoilerFinalCutValidator` required `gravity_after >= target_turns`, but neither the Finalize schema descriptions nor the system prompt stated it. Opening cards repeatedly failed with the opaque message `Final scene card S1_M01 has invalid word or turn budgets.`

### 2.5 Editorial craft notes as hard failures

Editorial REVISE notes such as "Deepen S3_M05 and S3_M09…" permanently marked sessions `FAILED` with the same force as missing bindings. There was no severity axis.

---

## 3. Fixes — commit `e582a10`

### 3.1 Unsatisfiable SSM guidance rejected at Phase 2

**File:** `app/Jobs/Adaptation/StorySessionMapJob.php`

After computing `$validEventIds` for a session's `event_range`, reject:

```text
recommended_moment_count_min > count(validEventIds)
```

Message includes session number, minimum, and event count.

**Prompt:** `resources/views/ai/agents/adaptation/story-session-map/system-prompt.blade.php`

- Moment counts bounded by material, not minutes
- Bridge sessions of 2–3 events get 2–3 moment ranges
- Prefer widening/folding thin sessions over promising unsatisfiable ranges
- STOP GATE checks `min <= event_count`

### 3.2 Document `gravity_after >= target_turns`

**Files:**

- `app/Ai/Agents/Adaptation/BoilerFinalizeAgent.php` — schema descriptions on `target_turns` / `gravity_after`
- `resources/views/ai/agents/adaptation/boiler-finalize/system-prompt.blade.php` — card requirements state gravity is the point *after* the target

### 3.3 Finalize claim outcomes stop the chain when no cut exists

**Files:**

- `app/Exceptions/Adaptation/BoilerFinalizeNotClaimedException.php` (new)
- `app/Jobs/Adaptation/BoilerFinalizeJob.php`

Claim results:

| Result | Behavior |
|---|---|
| `claimed` | Run Finalize |
| `already_finalized` | Return success (artifacts exist; chain may proceed) |
| `held_by_another_worker` / `not_finalizable` / `missing` | Throw `BoilerFinalizeNotClaimedException` (stops chain) |

`failed()` ignores `BoilerFinalizeNotClaimedException` so an orphan cannot overwrite the status of the worker that owns the session.

### 3.4 Editorial hard-guard before model spend

**File:** `app/Jobs/Adaptation/EditorialVerificationJob.php`

If Boiler is enabled and any of these are missing, fail before calling the agent:

- `boiler_status === FINALIZED`
- non-empty `boiler_final_cut`
- normalized `playableCut` present

### 3.5 Recovery from terminal Draft `FAILED`

**File:** `app/Console/Commands/RepairBoilerEditorialCommand.php`

New `--from=draft`:

- Clears session back to `PENDING` / `boiler_status=PENDING`
- Redispatches full `SessionAdaptationChain` for that session
- **Refuses** if SSM guidance is still unsatisfiable (prints min vs event count)

---

## 4. Fixes — commit `d46bda9`

### 4.1 Batch isolation (highest ops impact)

**File:** `app/Support/Adaptation/SessionAdaptationChain.php`

```php
Bus::batch($sessionChains)->allowFailures()->onQueue('adaptation');
```

Sessions are independent; one failure must not cancel siblings.

**Cancelled guards** added at the top of:

- `EntryPointDiagnosisJob`
- `BoilerDraftJob`
- `SessionArchitectureJob`
- `ChoiceDesignJob`
- `ConsequenceMappingJob`
- `SessionCloseJob`
- `BoilerFinalizeJob`
- `EditorialVerificationJob`
- `RuntimeNarratorAssemblyJob`

```php
if ($this->batch()?->cancelled()) {
    return;
}
```

### 4.2 Shared generate → validate → repair loop

**New:**

- `app/Support/Adaptation/GeneratesValidatedArtifact.php`
- `app/Exceptions/Adaptation/ArtifactValidationException.php`

Owns:

- `MAX_VALIDATION_ATTEMPTS = 2`
- log events `boiler.{artifact}_validation_failed` / `_corrected`
- `ArtifactValidationException` when attempts exhaust
- `failTerminally()` — marks queue job failed without another attempt; rethrows outside queue context

Adopted by:

| Job | Artifact stem |
|---|---|
| `BoilerDraftJob` | `draft` |
| `BoilerFinalizeJob` | `finalize` |
| `SessionArchitectureJob` | `architecture` |
| `ChoiceDesignJob` | `choice_design` |
| `ConsequenceMappingJob` | `consequence_mapping` |

Semantic validation failures call `failTerminally()` so the queue does not replay a failure it can only reproduce. Transport errors still rethrow and keep `$tries`.

### 4.3 Uniform `$tries = 2` on session phases

Meaning: one retry for a network blip — not another full validation loop. Validation attempts stay inside the shared trait (max 2 generations per job attempt). Editorial stays `$tries = 1` (its own in-process retry already exists).

### 4.4 Editorial: repair context + blocking vs advisory

**Files:**

- `app/Jobs/Adaptation/EditorialVerificationJob.php`
- `resources/views/ai/agents/adaptation/editorial-verification/prompt.blade.php`

**Advisory (log only, do not fail session):**

- `playable_room_quality`
- `experience_balance`

**Blocking (still hard-fail):** coverage, bindings, alignment, budgets, arc, exit conditions, etc.

Auto-retry on RED / blocking REVISE now passes `priorRevisionFocus` (failed check names + revision instructions) into the second prompt. The prompt also states the four supplied artifacts are the complete set — do not ask for a "missing" Final Manifest that is already present.

### 4.5 Manifest naming honesty

Prompt variable renamed `boilerFinalCut` → `boilerManifest`. Column `boiler_final_cut` still holds the *manifest* summary; scene cards live in normalized `boilerMoments`. Matches how `RuntimeNarratorTemplateBuilder` already treated it as `boilerManifest`.

### 4.6 Stop double-loading session events on Draft

`BoilerDraftContextBuilder::build()` accepts an optional pre-loaded `Collection`. `BoilerDraftJob` loads events once and passes them into both context build and validation.

---

## 5. What this deliberately did *not* change

- Boiler spine / runtime progression (still semantic `exit_condition_met` only)
- Speckled Band canary artifacts
- Full SQL rewrite of `SessionEventLoader` (`ROW_NUMBER()` window for story_position) — left for a later pass; only the double-load was removed
- Renaming the DB column `boiler_final_cut` (prompt variable only)

---

## 6. Ops notes before re-running Wasteland

1. **Deploy** must include both `e582a10` and `d46bda9`.
2. **Stored Session Map for Wasteland S4 is still illegal** until regenerated. The new Phase-2 guard only runs when a map is generated. Either:
   - regenerate Story Session Map for Wasteland, or
   - hand-correct S4 `boiling_profile` to a 1–2 moment range (and few minutes)
3. Then recover S4:

```bash
php artisan stories:repair-boiler-editorial wasteland --session=4 --from=draft
```

If guidance is still unsatisfiable, the command refuses with the real numbers.

4. Sessions 1–3 that have valid drafts can be resumed from finalize/editorial as appropriate once workers are stable:

```bash
php artisan stories:repair-boiler-editorial wasteland --session=1 --from=finalize
```

5. Watch for new log events:

| Event | Meaning |
|---|---|
| `boiler.finalize_not_claimed` | Orphan Finalize stood down without releasing Editorial |
| `boiler.finalize_already_finalized` | Idempotent pass-through |
| `boiler.editorial_verification_advisory` | Craft notes recorded; session not failed |
| `boiler.editorial_verification_failed` | Blocking checks only |

---

## 7. File ledger

### New

```text
app/Exceptions/Adaptation/BoilerFinalizeNotClaimedException.php   (e582a10)
app/Exceptions/Adaptation/ArtifactValidationException.php         (d46bda9)
app/Support/Adaptation/GeneratesValidatedArtifact.php             (d46bda9)
docs/Boiler/Debug/WASTELAND-BOILER-PIPELINE-HARDENING-PROCESS-LOG.md  (this file)
```

### Modified (pipeline)

```text
app/Jobs/Adaptation/StorySessionMapJob.php
app/Jobs/Adaptation/BoilerDraftJob.php
app/Jobs/Adaptation/BoilerFinalizeJob.php
app/Jobs/Adaptation/SessionArchitectureJob.php
app/Jobs/Adaptation/ChoiceDesignJob.php
app/Jobs/Adaptation/ConsequenceMappingJob.php
app/Jobs/Adaptation/SessionCloseJob.php
app/Jobs/Adaptation/EntryPointDiagnosisJob.php
app/Jobs/Adaptation/EditorialVerificationJob.php
app/Jobs/Adaptation/RuntimeNarratorAssemblyJob.php
app/Support/Adaptation/SessionAdaptationChain.php
app/Ai/Adaptation/BoilerDraftContextBuilder.php
app/Ai/Agents/Adaptation/BoilerFinalizeAgent.php
app/Console/Commands/RepairBoilerEditorialCommand.php
resources/views/ai/agents/adaptation/story-session-map/system-prompt.blade.php
resources/views/ai/agents/adaptation/boiler-finalize/system-prompt.blade.php
resources/views/ai/agents/adaptation/editorial-verification/prompt.blade.php
tests/Unit/BoilerWiringContractTest.php
```

### Evidence / export (unchanged by code, used as input)

```text
docs/Boiler/Debug/wasteland-boiler-opus-diagnostic.json
docs/Boiler/Debug/WASTELAND-OPUS-DIAGNOSTIC-EXPORT.md
```

---

## 8. Root-cause vs symptom map (for future logs)

| Symptom in Cloud log | Actual cause | Fix |
|---|---|---|
| `event 2225 assigned to more than one moment` | Unsatisfiable S4 moment min | SSM validator + prompt |
| `target_moment_count outside range` | Same | Same |
| `Final scene card S*_M01 has invalid word or turn budgets` | Undocumented `gravity_after` rule | Schema + Finalize prompt |
| Editorial "Provide Final Manifest…" | Editorial run without final cut | Finalize claim throw + Editorial guard |
| `MaxAttemptsExceeded` / `failed_jobs_uuid_unique` | Deploy kills + orphaned retries | Ops noise; `allowFailures` + cancelled guards reduce compounding |
| Editorial REVISE on room richness → session FAILED | No severity split | Advisory checks |
| Sibling sessions dying when one fails | Batch cancel without allowFailures | `allowFailures()` + cancelled guards |

---

## 9. Related docs

| Doc | Relation |
|---|---|
| [WASTELAND-OPUS-DIAGNOSTIC-EXPORT.md](./WASTELAND-OPUS-DIAGNOSTIC-EXPORT.md) | How the diagnostic JSON was exported |
| [BOILER_AGENCY_RESTORATION_PROCESS_LOG.md](./BOILER_AGENCY_RESTORATION_PROCESS_LOG.md) | Prior runtime-agency pass (different layer) |
| [../BOILER_DOCUMENTATION_V1.md](../BOILER_DOCUMENTATION_V1.md) | Product / architecture source of truth |
| [../Boiler Run/BOILER_VALIDATION_RUNBOOK.md](../Boiler%20Run/BOILER_VALIDATION_RUNBOOK.md) | Deploy / canary verification |
