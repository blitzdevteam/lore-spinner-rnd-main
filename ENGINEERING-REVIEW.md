# LoreSpinner — Engineering Review

> **Date:** June 18, 2026  
> **Scope:** Full codebase audit — bugs, security, pipeline reliability, DX, and feature opportunities  
> **Branch:** R&D (`lore-spinner-rnd`)

This document is a living engineering backlog derived from reading the codebase, docs, R&D specs, CI config, and critical runtime paths. It is meant to guide prioritization — not replace issue tracking.

---

## Executive Summary

LoreSpinner has a **strong core architecture**: a two-stage AI pipeline (extract → adapt → cache narrator prompt) feeding a unified **Chaos V2 production runtime** (`ChaosEngineService`). The adaptation layer (V2.2) is sophisticated and well-structured.

Operational maturity has not kept pace with the architecture:

| Area | Status |
|------|--------|
| Production runtime (Chaos V2) | ✅ Implemented |
| Adaptation pipeline (V2.2) | ✅ Implemented, ⚠️ fragile under concurrency |
| Security / authorization | 🔴 Critical gaps (IDOR) |
| Test coverage | 🔴 ~12 test files; gameplay & pipeline untested |
| Documentation | 🟡 Stale in several key places |
| R&D features (images, artifacts, TTS guard) | 📋 Spec-only |
| Story onboarding | 🟡 Manual, multi-step, error-prone |

**Top 5 actions if you do nothing else:**

1. Add game ownership checks to all gameplay controllers (IDOR fix).
2. Scope Writer Lab routes to story/chapter/draft ownership.
3. Fix `canceled()` → `cancelled()` typo in `EventObjectiveAndAttributeExtractor`.
4. Unify playable-story config (PHP + TS + Vue are out of sync today).
5. Add adaptation pipeline locking + partial-batch validation in merge jobs.

---

## Architecture Snapshot

```
Browser (Vue 3 + Inertia v2)
    ↓
Laravel 12 Controllers → Actions → Models → PostgreSQL
    ├── Extraction jobs     (Chapter → Event → Objectives → System Prompt)
    ├── Adaptation jobs     (IP Trim → Voice Lock → Session Map → per-session chain)
    ├── ChaosEngineService  (production narration — session-based, not event-by-event)
    ├── Filament panels     (/creator, /manager)
    └── Writer Lab / Voice Lab / Chaos Mode lab (parallel surfaces)
         ↓
    PgSQL · Redis (cache) · MinIO · OpenAI/Anthropic/ElevenLabs
```

**Key insight:** Production moved from Story Guard (`NarrationAgent` + `current_event_id`) to **Chaos V2** (session-based, cached `runtime_narrator_prompt`). Writer Lab playground still uses the old engine — previews do not match live gameplay.

Authoritative references:
- `docs/microservice-map.md` — current system map
- `chaos-mode/docs/chaos-default-mode.md` — production migration record
- `Adaptation layer/PROCESS_LOG.md` — deferred adaptation work

---

## P0 — Critical Bugs

### 1. Game IDOR — any authenticated user can play anyone's game

`GameController::show`, `begin`, `reset`, and `nextSession` load a `Game` by ULID with **no check** that `$game->user_id === auth()->id()`.

```52:66:app/Http/Controllers/User/GameController.php
    public function show(Game $game): Response
    {
        $game->load([
            'story' => fn ($q) => $q->with([
                'adaptation' => fn ($a) => $a->withCount('sessionAdaptations'),
            ]),
            // ...
        ]);

        return inertia('User/Games/Show', [
            'game' => $game->toResource(),
        ]);
    }
```

Same pattern in `PromptController::store` — user is injected but never compared to `$game->user_id`.

**Fix:** Add a `GamePolicy` or middleware that aborts 403 when the game doesn't belong to the current user. Apply to `TextToSpeechController` too (currently checks prompt→game but not game→user).

---

### 2. Writer Lab — no resource scoping

Any `auth:writer` user can:
- List **all stories** (`Story::query()->get()` in `WriterLabController::index`)
- Mutate drafts on routes like `writer-lab/{story}/chapters/{chapter}/drafts/{draft}` without verifying `$chapter->story_id === $story->id` or `$draft->chapter_id === $chapter->id`

There is **no `app/Policies/` directory** and no `authorize()` calls in Writer Lab controllers.

**Fix:** Introduce policies (or scoped route model binding) before Writer Lab goes beyond internal R&D use.

---

### 3. Batch cancellation API typo

`EventObjectiveAndAttributeExtractor` uses `canceled()` while every other batch job uses `cancelled()`:

```42:44:app/Jobs/Event/EventObjectiveAndAttributeExtractor.php
        if ($this->batch()?->canceled()) {
            return;
        }
```

Laravel's `Illuminate\Bus\Batch` exposes `cancelled()`. If `canceled()` does not exist, this throws when the job runs inside an active batch instead of exiting early.

**Fix:** One-line change to `cancelled()`. Add a unit test.

---

### 4. Editorial verification marks RED sessions as COMPLETED

`EditorialVerificationJob` auto-retries once on `production_status === 'RED'`, then **always** sets `session_status = COMPLETED` regardless of the retry outcome:

```57:65:app/Jobs/Adaptation/EditorialVerificationJob.php
            if (($verification['production_status'] ?? '') === 'RED') {
                $verification = $this->runVerification($adaptation, $session, $completeDesign);
                $verification['auto_retry_attempted'] = true;
            }

            $session->update([
                'editorial_verification' => $verification,
                'session_status' => SessionAdaptationStatusEnum::COMPLETED,
            ]);
```

Deliverable 6 spec implies RED should block completion or trigger re-run of prior phases. Today, a RED session can still get a runtime narrator prompt assembled and appear "ready."

**Fix:** If still RED after retry → `FAILED` or a new `NEEDS_REVIEW` status; gate `RuntimeNarratorAssemblyJob` on GREEN/YELLOW only.

---

## P1 — Security & Exposure

### Unauthenticated debug endpoint leaks internal data

`GET /expansion-status` (no auth) returns creator emails, cover paths, filesystem symlink details, and story statuses:

```53:127:routes/web.php
Route::get('expansion-status', function () {
    // ... exposes creators with email, story cover paths, symlink target, etc.
});
```

**Fix:** Gate behind `APP_DEBUG`, manager auth, or remove from production routes.

---

### Chaos Mode — intentional public LLM surface

`/chaos-mode/*` has no auth. Anyone with a session ULID can advance any chaos session. This is documented as experimental but is a **cost and abuse vector** in production deployments.

**Mitigation options:** Rate limiting, API key, IP throttling, or env flag to disable outside staging.

---

### Feedback screenshot path traversal

`FeedbackController::screenshot` blocks `..` but does not restrict paths to the `feedback-screenshots/` prefix. Any authenticated user could potentially read other files on the public disk if paths are known.

**Fix:** Validate path prefix + use signed URLs or storage IDs instead of raw paths.

---

### Mass assignment surface

Most models use `$guarded = ['id', 'created_at', 'updated_at']` only. Safe today because controllers use validated input, but fragile if someone adds `Model::create($request->all())`.

**Fix:** Switch to explicit `$fillable` on high-risk models (`Game`, `Prompt`, `Story`, `Event`).

---

### Runtime `env()` calls

`TranscribeController` and image jobs read `env('OPENAI_API_KEY')`, `env('IMAGE_PROVIDER')` directly. Breaks `config:cache` in production and can return null unexpectedly.

**Fix:** Move to `config/services.php` and read via `config()`.

---

### XSS surface from LLM output

Frontend uses `v-html` on narration in `GameplayChatCard.vue` and `ChaosMode.vue`. If server-side sanitization is ever skipped or bypassed, this is an XSS vector.

**Fix:** Audit `ChaosEngineService` output handling; consider DOMPurify on the client for defense in depth.

---

## P1 — Pipeline & Job Reliability

### No concurrency guard on adaptation re-runs

`RunAdaptationPipelineJob` has no `ShouldBeUnique`, no `Cache::lock`, and `tries = 1`. Concurrent `--force` runs can:
- Delete `sessionAdaptations` mid-flight
- Null `session_number` on events
- Dispatch overlapping batches

Only `RunExpansionSeederJob` implements `ShouldBeUnique`.

**Fix:** Unique lock per `story_id` for the full adaptation orchestrator.

---

### Batch `finally` dispatches merge jobs even on failure

`IpAuditJob`, `RunAdaptationPipelineJob`, and `StorySessionMapJob` use `Bus::batch(...)->finally()` to chain the next step. Laravel's `finally` runs on batch **completion**, not success — failed chapter jobs can still trigger merge/reconciliation.

**Fix:** Use `then()` for success-only chaining, or check `$batch->failedJobs` inside `finally`.

---

### Partial batch merge — incomplete voice/IP fragments

`VoiceLockMergeJob` and `IpTrimmingMergeJob` fail on empty fragments but **do not verify** `count($fragments) === $chapters->count()`. A partial batch can merge an incomplete voice profile into production data.

**Fix:** Assert fragment count matches chapter count before merge; fail loudly with `FAILED` status.

---

### Inconsistent job error handling

Some adaptation jobs catch → update status → rethrow (`SessionArchitectureJob`). Others have no catch at all (`VoiceLockChapterJob`, `IpTrimmingChapterJob`, merge jobs). **Zero jobs** implement a `failed()` method for permanent failure alerting.

**Fix:** Standardize on a trait or base job class: catch, update `session_status`/`adaptation_status`, log, rethrow; implement `failed()` for reconciliation.

---

### Queue infrastructure mismatch

- Default queue: **`database`** (`config/queue.php`), not Redis
- README deployment says `queue:work redis`
- 8 named queues exist; docs show only 3 workers on `adaptation`
- Ingestion/image queues can stall silently if not workered separately

**Fix:** Align docs, `.env.example`, and Cloud worker config. Consider Redis for production throughput.

---

## P2 — Config & UX Inconsistencies

### Playable story list is triplicated and out of sync

| Source | Count | Notes |
|--------|-------|-------|
| `GameController::LAUNCH_SLUGS` | 10 | Includes `the-tell-tale-heart` |
| `resources/js/data/playableStorySlugs.ts` | 9 | **Missing** `the-tell-tale-heart` |
| `FeaturedWorldsGames.vue` | hardcoded | Uses alias slugs like `alice-in-wonderland` |

Backend also requires session 1 `runtime_narrator_prompt` (`StoryController`); frontend `isStoryPlayable()` does **not**.

**Fix:** Single source of truth — DB column `is_playable` or config file consumed by both PHP (via Inertia shared props) and Vue. Derive from `adaptation_status === COMPLETED` + runtime prompt present instead of hardcoded slugs long-term.

---

### Alice slug aliasing confusion

DB slug: `alices-adventures-in-wonderland`. Frontend aliases: `alice-in-wonderland`. Mapping exists in `storyCardHoverMeta.ts` and `moodStories.ts` but is easy to break when adding stories.

**Fix:** Normalize slugs at the API boundary; stop maintaining parallel alias maps in JS.

---

### Writer Lab ≠ production engine

`PlaygroundController` uses `NarrationAgent` (Story Guard era). Live player uses `ChaosEngineService`. Editorial preview **will diverge** from player experience.

**Fix:** Route Writer Lab playground through `ChaosEngineService` with the same session context loader.

---

### Stale developer commands

`SimulateGameStartCommand` still references `NarrationAgent` flow, not `ChaosEngineService`. Misleading for debugging.

**Fix:** Update or deprecate with pointer to `GameTraceCommand`.

---

## Test & CI Gaps

### Current coverage (12 test files)

| Covered | Not covered |
|---------|-------------|
| Analytics (4 feature + 2 support) | Adaptation pipeline (26 jobs) |
| User auth | `GameController`, `PromptController` |
| `ChaosModeStateMergeTest` (reflection on removed controller internals) | Writer Lab |
| | Chaos Mode API |
| | Voice Lab |
| | Image/TTS jobs |
| | Authorization / IDOR |
| | Policies (none exist) |

CI runs `./vendor/bin/pest` on push/PR but **core gameplay and adaptation are untested**.

### Recommended test priorities

1. **Game ownership** — 403 when accessing another user's game ULID
2. **Prompt turn flow** — mock `ChaosEngineService`, assert world_state merge + prompt persistence
3. **EditorialVerificationJob** — RED after retry must not reach COMPLETED
4. **VoiceLockMergeJob** — reject partial fragment sets
5. **RunAdaptationPipelineJob** — concurrent dispatch blocked by unique lock
6. **Adaptation validation runner in CI** — `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php` exists but is manual-only today

---

## Documentation Debt

| Document | Problem |
|----------|---------|
| `docs/DOCUMENTATION.md` | Gameplay loop still describes `NarrationAgent` + `current_event_id` |
| `Adaptation layer/runtime-logic.md` | Entirely Story Guard — superseded |
| `docs/writer-lab/01-design.md` | Says "no implementation yet"; Writer Lab is built |
| `chaos-mode/docs/adding-a-chaos-story.md` | References removed `voice_partial` |
| `README.md` | Points to `DOCUMENTATION.md` at root; file moved to `docs/` |
| Admin paths in docs | Says `/admin`; actual panels are `/creator` and `/manager` |

**Fix:** Add a `docs/ARCHITECTURE.md` "current state" page; mark stale docs with deprecation banners; update README link.

---

## Performance & Scalability

### Token cost per turn

- Cached narrator prompts up to **192,000 chars** per session (`RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS`)
- `PromptController` sends last **12 turns** of history on every turn
- No precomputed `runtime_payload` (deferred in PROCESS_LOG)

**Ideas:** Precompute static portions of the prompt; trim history intelligently; cache rendered system prompt per session version.

---

### Adaptation cost

- Parallel batches: 2×N chapter jobs for IP trim + voice lock
- Per-session sequential chains for multi-session stories
- Full pipeline re-run only — no per-session retry from Filament

---

### TTS latency & hallucination

`TextToSpeechController` sends full `strip_tags($prompt->response)` as one ElevenLabs call. Documented in `RnD Ideas/voice-hallucination-guard.md` — observed fabricated speech on long passages.

**Fix:** Paragraph-chunked generation with `Http::pool()` (pattern already exists in `BakeVoiceLabIntroCommand`).

---

### Seeding blocks CLI

`AddSingleStorySeeder` forces `queue.default = sync` — 5–20 minute blocking run on Cloud for full extraction.

**Fix:** Async extraction with progress polling; `stories:onboard {slug}` command with status dashboard.

---

### Image jobs on ephemeral filesystem

`StoryCoverGeneratorJob` throws if file missing after save — known issue on Cloud ephemeral disks.

**Fix:** Write directly to S3/MinIO; verify via storage driver, not local path.

---

## Deferred Work (from Adaptation PROCESS_LOG)

These are acknowledged gaps, not surprises:

- **Adaptation versioning** — `version` + `is_active` on `story_adaptations` for rollback/A/B
- **Precomputed runtime payload** — faster prompt assembly without Blade render each turn
- **Per-session adaptation retry** — Filament UI for retry-from-failed-session
- **Beat type derivation** — `current_beat_type` on games is a cache placeholder
- **Emergent branch promotion** — `branch_resolution_log` captured, no editorial promotion UI
- **Programmatic branch resolution** — rules in prompts only, no server-side enforcement

---

## Feature Ideas — R&D Backlog

Ideas below are grounded in existing specs (`RnD Ideas/`) or clear codebase gaps. Ordered by impact × feasibility.

### Tier 1 — High impact, builds on existing code

| Idea | Source | Notes |
|------|--------|-------|
| **Unified story onboarding command** | `docs/ADDING_A_STORY.md` gap | `stories:onboard {slug}` → extract → adapt → images → readiness check |
| **Paragraph-chunked TTS** | `RnD Ideas/voice-hallucination-guard.md` | Fix hallucination + reduce latency for long narration |
| **Single playable-story source of truth** | LAUNCH_SLUGS drift | DB flag or shared config via Inertia |
| **Writer Lab on ChaosEngineService** | Engine split | Editorial preview matches production |
| **Adaptation observability in Filament** | PROCESS_LOG | Status dashboard, retry-from-session, failure reasons |
| **Per-story wipe** | `WipeStoriesCommand` is all-or-nothing | `stories:wipe {slug}` for safe iteration |

### Tier 2 — Creative Director image pipeline

From `RnD Ideas/Image-pipeline.md` — **zero implementation in `app/` today**.

Proposed structure:
```
Story → Visual DNA → Draft Prompt → Creative Director → Generate 2–4 candidates → Image Critic → Revision loop → Approved asset
```

Upgrade path: wrap existing `StoryCoverGeneratorJob` / `ChapterCoverGeneratorJob` with critic loop instead of single-pass Gemini/OpenAI prompt.

Related: `RnD Ideas/multi-provider-image-gallery.md` — remove `singleFile()` constraint, let creators pick active variant per provider.

### Tier 3 — Reactive story artifacts (in-scene visuals)

From `RnD Ideas/In-Scene-Image_Gen.md` and `In-Scen_Image-Gen-mobile.md`.

Principle: **narration/state = truth; artifact image = visual echo of truth**. Separate visual layer listens to `world_state` / narrator output — does not burden the narrator with image logic.

Examples: pocket watch, DRINK ME bottle, bloodstained phone — diegetic UI traces, not scene illustrations every turn.

### Tier 4 — Runtime & narrative depth

| Idea | Notes |
|------|-------|
| Beat type derivation at runtime | Wire `session_architecture` beat map into `ChaosEngineService` context |
| Branch promotion workflow | Filament UI for emergent branches from `branch_resolution_log` |
| Session-close injection | Noted in adaptation debug guides; verify in `PromptController` |
| Social Echo Layer | Deliverable 9 spec exists in `Adaptation layer/` — not in runtime |
| Voice Lab → production path | `app/VoiceLab/` is isolated R&D; could become alternate input modality |

### Tier 5 — UX & discovery

| Idea | Notes |
|------|-------|
| Auto-playability from adaptation status | Replace hardcoded slugs when pipeline is stable |
| Lava / ocean background R&D | `RnD Ideas/Lava/` — visual polish for story cards/hero |
| Chaos Mode lab merge | `chaos-as-default-mode.md` proposed story-card → `/chaos-mode`; production shipped `/user/games` instead — decide if lab surface still needed |
| Model picker in Filament | TODO in chaos docs for manager settings |

---

## Recommended Roadmap

### Sprint 1 — Safety & correctness (1–2 weeks)

- [ ] Game + Prompt + TTS ownership policies
- [ ] Writer Lab resource scoping
- [ ] Fix `cancelled()` typo
- [ ] Gate `/expansion-status`
- [ ] Editorial RED → don't mark COMPLETED
- [ ] Sync `playableStorySlugs.ts` with `LAUNCH_SLUGS` (immediate band-aid)

### Sprint 2 — Pipeline hardening (2–3 weeks)

- [ ] `ShouldBeUnique` on `RunAdaptationPipelineJob`
- [ ] Batch success-only chaining (`then` vs `finally`)
- [ ] Partial fragment validation in merge jobs
- [ ] Standardized job `failed()` handlers
- [ ] Move `env()` to `config()` for runtime services
- [ ] Queue worker config aligned with 8 queues

### Sprint 3 — Test & docs (ongoing)

- [ ] Gameplay feature tests with mocked AI
- [ ] Adaptation job unit tests (merge, editorial, orchestrator)
- [ ] Update `docs/DOCUMENTATION.md` gameplay section for Chaos V2
- [ ] Deprecation banners on stale docs
- [ ] Fix README doc link

### Sprint 4 — DX & onboarding (2–4 weeks)

- [ ] `stories:onboard {slug}` artisan command
- [ ] Filament adaptation status dashboard
- [ ] Per-session retry
- [ ] Update `SimulateGameStartCommand` / document `GameTraceCommand`

### Sprint 5 — R&D → production (quarter)

- [ ] Paragraph-chunked TTS
- [ ] Creative Director image pipeline (covers first)
- [ ] Multi-provider image gallery in Filament
- [ ] Writer Lab on `ChaosEngineService`
- [ ] Reactive story artifacts (pilot on one story)

---

## File Reference — Where Things Live

| Concern | Path |
|---------|------|
| Production engine | `app/Services/ChaosEngineService.php` |
| Game lifecycle | `app/Http/Controllers/User/GameController.php` |
| Turn handling | `app/Http/Controllers/User/Game/PromptController.php` |
| Adaptation entry | `app/Jobs/Adaptation/RunAdaptationPipelineJob.php` |
| Runtime prompt builder | `app/Ai/Adaptation/RuntimeNarratorTemplateBuilder.php` |
| Narrator template | `resources/views/ai/agents/chaos/runtime-narrator-template.blade.php` |
| Story extraction seeder | `database/seeders/AddSingleStorySeeder.php` |
| Playable whitelist | `app/Http/Controllers/User/GameController.php` (`LAUNCH_SLUGS`) |
| Frontend playable check | `resources/js/data/playableStorySlugs.ts` |
| Chaos story config | `app/ChaosMode/ChaosStoryConfig.php` |
| Writer Lab (old engine) | `app/Http/Controllers/Writer/WriterLab/PlaygroundController.php` |
| Image jobs (single-pass) | `app/Jobs/Story/StoryCoverGeneratorJob.php` |
| TTS | `app/Http/Controllers/User/Game/TextToSpeechController.php` |
| R&D specs | `RnD Ideas/` |
| Adaptation specs | `Adaptation layer/Chaos adaptation/` |
| Deferred items | `Adaptation layer/PROCESS_LOG.md` (lines 163–170) |
| CI tests | `.github/workflows/tests.yml` (Pest only — no adaptation validation) |

---

## Bottom Line

The **hard problems are largely solved**: extraction, V2.2 adaptation, cached narrator prompts, and Chaos V2 runtime form a coherent production stack. What remains is **operational excellence**:

- Close security holes before scaling users
- Make the pipeline safe under retry and concurrency
- Align docs, config, and engines so the team isn't debugging three versions of reality
- Ship the R&D specs (images, TTS, artifacts) incrementally on top of the solid base

This repo is closer to production-ready narrative tech than most R&D branches — it needs a focused hardening pass, not a rewrite.

---

*Generated from codebase audit, June 2026. Update this doc when items are resolved or reprioritized.*
