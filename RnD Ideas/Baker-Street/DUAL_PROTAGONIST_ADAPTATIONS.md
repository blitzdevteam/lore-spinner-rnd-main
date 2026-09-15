# Baker Street — Dual Protagonist Adaptations (Holmes / Watson)

**Status:** RnD — parked; circle back before Watson re-boil on Cloud  
**Audience:** Product, adaptation pipeline, backend, frontend  
**Purpose:** Keep the existing Holmes-boiled adaptations, add Watson-boiled variants, and let the player choose who they are on the Baker Street world page.

---

## 1. Problem

Launch intent for Baker Street is **play as Dr Watson** beside Holmes — same as Speckled Band.

| Title | Slug | Boil status (Cloud) | Protagonist in boil |
|-------|------|---------------------|---------------------|
| The Speckled Band | `the-adventure-of-the-speckled-band` | Completed | Watson (correct) |
| The Blue Carbuncle | `the-adventure-of-the-blue-carbuncle` | Completed | Holmes (mistake) |
| A Scandal in Bohemia | `a-scandal-in-bohemia` | Completed | Holmes (mistake) |

`ChaosStoryConfig` now lists Watson for all three Baker Street slugs (config + pipeline hints). That fixes **future** boils but does **not** retroactively change artifacts already baked as Holmes.

**Product decision (Daniel):** Do not throw away the Holmes boil. Keep it. Re-boil Watson as a **second variant**. Let the user choose at world entry: *Who do you want to play?*

---

## 2. Why both variants are worth keeping

Holmes and Watson are not a skin swap:

- Different body, knowledge, and agency in the same scenes
- Different cold opens and identity reveals (entry-point diagnosis)
- Different first choices and `freeform_input_hint` placeholders
- Different runtime narrator prompts (cached per session)

The mistaken Holmes boil is still a valid playable fantasy (“be the detective”). Watson is the canonical companion POV for LoreSpinner Baker Street. Both can coexist if the DB and game flow support **variant rows**, not one row overwritten by `--force`.

---

## 3. Current architecture blockers

### 3.1 One adaptation per story

`story_adaptations.story_id` is **unique**. A story can only have one adaptation row.

```php
// database/migrations/2026_04_15_000001_create_story_adaptations_table.php
$table->foreignId('story_id')->unique()->constrained()->cascadeOnDelete();
```

### 3.2 `--force` destroys the existing row

`RunAdaptationPipelineJob` with `--force`:

- Deletes all `session_adaptations` for that row
- Nulls `ip_trimming`, `voice_profile`, `story_session_map`, etc.
- Reuses the same `story_adaptations` id

**Do not run `--force --boiler` for Watson until Holmes is preserved** (export or variant schema).

### 3.3 Runtime assumes a single adaptation

- `Story::adaptation()` → `HasOne`
- `Game` stores only `story_id` — no protagonist / adaptation variant
- Playability, opening, turns, Chaos Mode all resolve `$story->adaptation`

### 3.4 Config ≠ stored boil

`ChaosStoryConfig` protagonist + `EntryPointDiagnosisJob` / `RuntimeNarratorTemplateBuilder` fixes affect **new** pipeline runs. Existing Holmes runtime prompts stay Holmes until re-boiled or swapped via variant selection.

---

## 4. Preserve Holmes now (ops — before any Watson re-boil)

On Cloud, export the two mistaken titles **before** any force re-boil:

```bash
php artisan adaptation:export the-adventure-of-the-blue-carbuncle
php artisan adaptation:export a-scandal-in-bohemia
```

Store JSON in repo or secure backup. Speckled Band (already Watson) can be exported for reference but is not at risk.

---

## 5. Recommended approach — `playable_protagonist` variants

### 5.1 Schema

**`story_adaptations`**

| Column | Type | Notes |
|--------|------|-------|
| `playable_protagonist` | string | e.g. `Watson`, `Holmes` |
| Unique | `(story_id, playable_protagonist)` | Replace unique on `story_id` alone |

Migration steps:

1. Add `playable_protagonist` (nullable → backfill → not null)
2. Backfill existing rows: Speckled Band → `Watson`; Blue Carbuncle + Scandal → `Holmes`
3. Drop unique on `story_id`; add unique on `(story_id, playable_protagonist)`

**`games`**

| Column | Type | Notes |
|--------|------|-------|
| `story_adaptation_id` | FK nullable → `story_adaptations` | Set at game create from player’s choice |

Or store `playable_protagonist` on `games` and resolve adaptation at runtime — FK is cleaner for eager loading.

### 5.2 Pipeline

- New flag or command variant: **create adaptation variant** (insert new row) vs **force re-run** (wipe existing row).
- Watson boil for Blue Carbuncle / Scandal = **new row** with `playable_protagonist = Watson`, not `--force` on Holmes row.
- Pass `playable_protagonist` into:
  - `ChaosStoryConfig`-equivalent lookup (or override config per variant)
  - IP trim merge, entry-point diagnosis, session map, runtime narrator assembly
- Speckled Band: optional Holmes variant later; launch with Watson-only is fine.

### 5.3 Story model

```text
Story::adaptations()          HasMany
Story::adaptationFor(string)  → row matching protagonist
Story::defaultAdaptation()    → Watson for Baker Street launch default?
```

Deprecate bare `adaptation()` HasOne or make it “default variant” only for backward compat during migration.

### 5.4 Game create flow

1. World page: player picks **Holmes** or **Watson**
2. Player picks **case** (Speckled Band / Blue Carbuncle / Scandal)
3. `CreateGameAction` resolves `story_adaptation_id` for `(story, protagonist)`
4. All runtime paths load adaptation via `$game->storyAdaptation` (or `$game->story_adaptation_id`), not `$story->adaptation`

### 5.5 Playability

A title is playable for a given protagonist when:

- A `story_adaptations` row exists for `(story_id, playable_protagonist)`
- That row’s `adaptation_status === completed`

World UI can show Holmes as “Coming Soon” on cases that only have one variant complete.

---

## 6. Lighter shortcut — duplicate story slugs

If variant schema is too much for first ship:

| Story record | Slug | Protagonist |
|--------------|------|-------------|
| Blue Carbuncle (Holmes) | `the-adventure-of-the-blue-carbuncle` | Holmes (keep current boil) |
| Blue Carbuncle (Watson) | `the-adventure-of-the-blue-carbuncle-watson` | Watson (new story + new boil) |

Pros: no migration; works with today’s one-adaptation-per-story model.  
Cons: duplicated catalog entries, manifest/world seeding noise, two covers/teasers unless shared.

World page groups by case + character. Acceptable for RnD; prefer §5 for launch quality.

---

## 7. World UX (target)

```text
Baker Street
────────────
Who are you?

  [ Dr Watson ]     [ Sherlock Holmes ]

Then — pick a case:

  Speckled Band · Blue Carbuncle · Scandal in Bohemia

  Play / Coming Soon per (case × protagonist) completion matrix
```

Matrix at launch (expected):

| Case | Watson | Holmes |
|------|--------|--------|
| Speckled Band | Play | Coming Soon (or omit) |
| Blue Carbuncle | Re-boil needed | Play (existing) |
| Scandal in Bohemia | Re-boil needed | Play (existing) |

---

## 8. Code already touched (main branch — config only)

Not sufficient for dual DB variants; listed for when we implement:

- `ChaosStoryConfig` — Watson for all three Baker Street slugs
- `ChaosStoryConfig::protagonistFor()`
- `EntryPointDiagnosisJob` — reads config protagonist
- `StorySessionMapJob` + prompt — playable protagonist hint
- `ProcessGameOpeningAction` / `ProcessGameTurnAction` — runtime protagonist from config (turn labels vs `system_prompt.character_name`)

When variants land, protagonist should come from **`story_adaptations.playable_protagonist`** (or game’s chosen adaptation), not a single global config row per slug.

---

## 9. Implementation checklist (when we circle back)

- [ ] Export Holmes JSON for Blue Carbuncle + Scandal (Cloud)
- [ ] Migration: `playable_protagonist` + composite unique
- [ ] Backfill existing adaptation rows
- [ ] Pipeline: `stories:run-adaptation --variant=Watson` (new row, no wipe)
- [ ] `Game.story_adaptation_id` + create flow
- [ ] Replace `$story->adaptation` hot paths with game-scoped adaptation
- [ ] World page character picker + playability matrix
- [ ] Watson re-boil Blue Carbuncle + Scandal (new rows)
- [ ] QA both POVs on same case (cold open, hint, turn labels, choices)
- [ ] Optional: Speckled Band Holmes variant (later)

---

## 10. Open questions

1. **Default protagonist** on deep links / mood cards — Watson?
2. **TTS voice** — same Ellis narrator for both POVs on Baker Street, or different?
3. **Chaos Mode lab** — show both variants per slug?
4. **Writer Lab / exports** — include `playable_protagonist` in export filename and schema?

---

## 11. Related files

- `app/ChaosMode/ChaosStoryConfig.php`
- `app/Jobs/Adaptation/RunAdaptationPipelineJob.php`
- `app/Models/StoryAdaptation.php`, `app/Models/Game.php`
- `database/migrations/2026_04_15_000001_create_story_adaptations_table.php`
- `config/stories.php` — Baker Street launch slugs
- `resources/js/components/WorldAvailableStoryCard.vue` — case picker today (no character step)
