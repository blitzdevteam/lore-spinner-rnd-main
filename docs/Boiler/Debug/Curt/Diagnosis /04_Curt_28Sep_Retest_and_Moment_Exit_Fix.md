# 04 — Curt 28 Sep retest, moment exit fix, and prose tightening

Follows `03_Prompt_Optimization_Changes.md`. Curt replayed Peter Pan on game `01m2np1hmtwkvzg2ce3rtyxfy1` after commits through `1b752584` (six new turns, 2026-09-27). Evidence: `curt-peter-pan-01m2np1hmtwkvzg2ce3rtyxfy1-evidence (28 Sep).json`.

## What improved in the retest

| Area | Old baseline (28-turn run) | 28 Sep retest (6 turns) |
|---|---|---|
| `world_state.knowledge` | 108 entries | 3 |
| `world_state.notes` | 105 | 0 |
| `world_state.player_style` | 74 | 2 |
| Acorn / "demand" / "Name it" in prose | Frequent | None |
| Action fidelity | Often drifted | Each turn matched the prior player action |
| Footnote | Reinjected interpretations | Player-direction handoff (see footnote on game) |

## What still failed

- **Moment pointer:** every response row stayed `moment_key=S1_M01`, `exit_condition_met=false`, through turn 6 (`playable_moment_turn_count=5`, target 4, `gravity_after=5`). Prose had already passed S1_M01’s exit (shadow mended, talking) and was into S1_M02+ material (crowing, lost boys, waking John, fairy dust).
- **Length:** narrator words 201, 163, 201, 121, 203, 231 vs ~130 target; four turns above ~163 ceiling.
- **Curt notes:** flying / window travel alluded to before Peter establishes how; AI “double speak” (hedges, “It is not a question”, trailing “and yet.”); repeated closing image (open window waiting in the dark).
- **Continuity slips:** “he told you so already” (never said); Nana described as gossiping like a person (Nana is the dog).

Cloud tinker on those six prompts confirmed `exit=false` on every row.

## Changes in this commit

### 1. Moment exit — prompt (`ChaosEngineService::buildActiveMomentBlock`)

- After the exit condition text, every non-opening turn gets **EXIT CHECK**: compare history + this turn’s narration to the semantic exit; if outcomes already happened, set `exit_condition_met=true` even if they happened turns ago.
- **STORY CHECK** line no longer ends with “this is not a reason to rush” (that wording encouraged holding the card).

### 2. Moment exit — server fallback (`PlayableMomentResolver`)

- **`heldPastGravity()`:** if the active card has **no** authored `choice_ids` and completed turns exceed `turn_budget.gravity_after`, advance to the next moment in order even when the model returns `exit_condition_met=false`.
- Logs `boiler.moment_advanced_past_gravity`. Cards **with** authored choices are never forced (branches must land through play).
- Curt’s stuck game: sixth committed turn was at count 5 with `gravity_after=5`; the **next** player turn should auto-advance to `S1_M02` if the model still refuses exit.

Catch-up via `caught_up_to_moment_key` is unchanged; this fallback covers the “model never sets exit” case.

### 3. Prose — per-turn instructions (`ChaosEngineService::turnInstructions`)

Applied on every turn after deploy (no runtime-prompt rebuild required):

- **RULE #1:** author’s prose as the next page of their book.
- **SAY IT PLAINLY:** no riddles, hedges, or “what a line is not”; no trailing fragments.
- **NOTHING BEFORE IT IS ESTABLISHED:** no abilities/facts before shown or told on the page (addresses early flying/window talk).
- **STOP WHEN THE ACTION IS ANSWERED:** no closing room/window/sleep padding or moment commentary; length OK only while every paragraph moves forward.

### 4. Prose — cached template (`runtime-narrator-template.blade.php`)

Same three bans under MASTER RULE 1 (DOUBLE SPEAK, UNESTABLISHED FACTS, CLOSING PADDING). Takes effect after **runtime prompt rebuild** for that story.

## Tests

Added to `tests/Feature/Boiler/PlayableMomentResolverTest.php`:

- `advances a choice-free card the narrator keeps holding past gravity`
- `never forces a card that carries an authored choice past gravity`

**Local run (Sail down / `DB_HOST=pgsql` unavailable):** Postgres on `127.0.0.1` (e.g. `lorespinner-pgsql` container), credentials `sail` / `password`, database `testing` (create once, migrate once):

```bash
PGPASSWORD=password psql -h 127.0.0.1 -U sail -d postgres -c "CREATE DATABASE testing;"  # once
APP_ENV=testing DB_HOST=127.0.0.1 DB_USERNAME=sail DB_PASSWORD=password DB_DATABASE=testing php artisan migrate --force --no-interaction  # once

DB_HOST=127.0.0.1 DB_USERNAME=sail DB_PASSWORD=password php artisan test --compact tests/Feature/Boiler/PlayableMomentResolverTest.php
```

Full file: **22 passed**, 96 assertions (~6.5 min). Related unit contracts: **32 passed**, 117 assertions.

With Sail: `vendor/bin/sail artisan test --compact tests/Feature/Boiler/PlayableMomentResolverTest.php`

## Laravel Cloud runbook (after deploy)

1. Deploy this commit to production.
2. **Optional but recommended** for template hard bans (Section 4):  
   `php artisan stories:rebuild-runtime-prompts peter-pan`  
   Per-turn rules in `turnInstructions()` apply immediately without rebuild.
3. Have Curt **continue** the same Peter Pan game (one more turn) or start fresh — verify `moment_key` advances off `S1_M01` and narration does not pre-mention flying before Peter explains it.
4. Export evidence:  
   `php artisan game:export-turn-request 01m2np1hmtwkvzg2ce3rtyxfy1 --response-prompt-id=<latest_response_ulid>`  
   (requires `game:export-turn-request` on Cloud from commit `a41e7f54` or later.)
5. Check logs for `boiler.moment_advanced_past_gravity` vs `boiler.moment_caught_up` / `boiler.moment_catch_up_rejected`.

## What’s next (priority)

1. **Validate in play** — one Curt session after deploy; confirm moment advance and plain prose on flying/fairy dust beats.
2. **Model exit compliance** — if exits stay false but gravity fallback fires often, consider schema examples or a post-turn heuristic that sets exit when exit-condition keywords match recent history (only if logs show the model ignores EXIT CHECK).
3. **Runtime prompt rebuild** — run for `peter-pan` (then catalog) so DOUBLE SPEAK / UNESTABLISHED FACTS / CLOSING PADDING live in the cached prefix, not only in `turnInstructions()`.
4. **Deploy `game:export-turn-request`** if not already on Cloud — needed for assembled request dumps on failing turns.
5. **F5 / length** — if word counts stay high without padding, tune LENGTH CONTRACT feedback using `previous_turn_words` ratio (already in active moment block) or lower temperature further on Boiler only (currently 0.7).
6. **Continuity QA** — spot-check knowledge injection vs conversation history so the model does not assert facts the player never heard (knowledge list is small now; keep watching).
