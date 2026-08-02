# Boiler Agency Restoration — Process Log

**Implementation date:** 2026-08-03
**Repository:** LoreSpinner R&D
**Implementation branch:** `main`
**Baseline commit:** `8a8ae0344a10d5697bd9d08eca1f7163bb1bc88b` (`fix: render narration audio off the request path`)
**Scope:** Boiler runtime player agency — prompt contracts, context scoping, turn accounting, gameplay UI
**Deployment state at handoff:** committed and pushed; Laravel Cloud auto-deploys from `main`
**Verification state at handoff:** PHP lint, Blade compile, and Pint baseline comparison executed locally. Migrations and the Pest suite were **not** executed — this workstation has no database and Docker was deliberately not started. `tests/Feature/Boiler` was updated and extended but has not been run.

---

## 1. Executive summary

Boiler's playable-moment runtime had drifted into what the review called *theatrical agency*: the player could act freely, the narrator would answer warmly, and then the world would quietly proceed as though nothing had happened. The spine survived every session, but the player's fingerprints did not.

This pass restores alpha-level (Chaos Engine V1 / StoryGuard) agency **without touching the Boiler spine**. Progression is still driven solely by the narrator's semantic `exit_condition_met` judgment against the active scene card. No new gates, no new deterministic pacing rules, no second model call.

The work divides into four causes:

1. **Authored agency support existed but was never shown to the narrator.** Two adaptation-time structures were computed, persisted, passed into the runtime template — and never rendered.
2. **The Freedom Contract actively forbade durable player consequence.** Its own wording capped improvisation at "small reversible actions" and instructed the narrator to promise no downstream effect.
3. **Two context-scoping bugs erased the player's own history** from the narrator's working memory, in one case within a single session.
4. **Gravity was written as a countdown and a wall** rather than as rising cost inside a world that pulls.

Everything below is either a prompt-contract change, a context-scoping fix, a diagnostic signal, or a UI affordance. There is one new migration and no new runtime dependency.

---

## 2. Problem inventory with evidence

### 2.1 Unrendered authored doctrine (highest impact, near-zero risk)

`RuntimeNarratorTemplateBuilder::build()` passed two view variables that no Blade expression in `runtime-narrator-template.blade.php` referenced:

| Variable | Source | Contents |
|---|---|---|
| `freeformGuidelines` | `consequence_map.freeform_guidelines` (12 entries: 4 branching choices × 3 paths) | `spirit`, `freeform_alignment_input`, `narrator_behavior`, `hard_limits`, `fold_back_acknowledge`, `fold_back_redirect`, `fold_back_arrive_at` |
| `consequenceMaps` | `consequence_map.branching_consequences` | per path: `consequence_visibility.what_changes`, `how_player_sees_it`, `when_visible_target_responses`, `when_visible_max_responses`, `current_session_echo` |

Section 15 instructed the narrator to *"FOLD BACK via the StoryGuard manifest"*. The manifest was never in the prompt. The narrator was being told to consult a document it could not see, and was improvising fold-backs instead of using the authored acknowledge → redirect → arrive-at moves.

Likewise, `consequence_visibility` encodes the payoff loop that makes a choice feel real — *what* changes, *how* the player perceives it, and *within how many responses*. None of it reached the narrator.

### 2.2 The Freedom Contract forbade committed history

Verbatim from the pre-change §15:

- MAY: "invent **small reversible** actions"
- `EMERGENT CANDIDATE`: "Preserve local consequence when safe; record the signal; **promise no downstream consequence** not in the adaptation layer."
- `UNSUPPORTED`: "cannot become a branch. FOLD BACK…"

Taken together, the narrator's own constitution told it that off-script player action must not durably change anything. That is the mechanical root of theatrical agency, and no amount of runtime injection could override a rule stated in the constitution.

### 2.3 Context-scoping bugs

**Boiler history window** (`ProcessGameTurnAction`):

```php
array_slice($history, -max(2, ($game->playable_moment_turn_count + 1) * 2))
```

`playable_moment_turn_count` resets to `0` when the pointer advances to the next moment. Therefore **the first turn in every new room gave the narrator a two-message window** — one exchange. Everything the player had just done in the room they had left was outside the window.

**Tier-2 relevance haystack** (`ChaosEngineService::renderSystemPrompt`): the session-event haystack used to decide which persisted state is "scene-connected" was filtered down to the active moment's `source_event_ids`. Because Tier-2 filtering matches *runtime-created* state (`object_states`, `relationship_updates`, `world_flags` — all written by `state_delta`) against *authored* event prose, narrowing the haystack to one scene card demoted nearly everything the player had done earlier in the session to the dormant list. This narrowing served no containment purpose: script scope is enforced separately by the Boiler session map (§12) and the active-moment injection.

### 2.4 Gravity as countdown and wall

The active-moment injection opened with `ACTIVE PLAYABLE MOMENT (the only room you may perform now)` and, at gravity, issued `PACING DIRECTIVE — DECISIVE PROGRESSION NOW: You must now create decisive forward movement.` The card was framed as the edge of the world, and gravity as an instruction to wrap up — which surfaces to the player as the narrator hurrying them.

Separately, `forbidden_expansions` conflates two different kinds of rule — inviolable world/character truths, and material *staged for a later room* — and the narrator treated both as absolute walls. That is why players who reached toward a later confrontation were blocked rather than allowed to fear it, reference it, or attempt it and fail forward.

### 2.5 Session-ending deadlock

`authoredSessionEndingChoiceResolved()` blocks `current_session_complete` until a `choice_id` bound to the final scene card is committed. If the narrator narrated the session as finished without the authored choice landing, the player stayed in a room that had run out of story, indefinitely, with no signal that anything was wrong.

### 2.6 UI suppression of low-effort and off-path play

- `showContinueButton` was hard-gated on `!usesPlayableMoments` — Continue was unavailable in Boiler.
- `PromptController::validateSubmittedAction()` only recognised `__continue__` when `current_playable_moment_id === null`, so in Boiler the literal string `__continue__` was passed through as the player's action text to the model.
- The "Want To Go Off-Path?" invitation was nested inside `v-if="hasChoices"`, so freeform read as a fallback for turns that happened to list suggestions rather than as the normal way to play.

---

## 3. Changes by file

### `resources/views/ai/agents/chaos/runtime-narrator-template.blade.php`

- **§6 World Reactivity** — added `DELAYED REACTION`: consequences need not land on the turn that earned them; record them in `state_delta` and surface them later, unannounced. Activates the previously inert `timing_rules` list.
- **§7 Persistent World State** — instructed the narrator to let observed `player_style` shape diction and what the world notices first, and never to name it or describe the player to themselves.
- **§11 Session Packet** — renders `consequenceMaps` as a compact `CONSEQUENCE VISIBILITY` block (per choice/path: what changes, how the player perceives it, response budget, session echo). Applies to both Boiler and legacy.
- **§14 Boiler `moment_progress` contract** — rewritten. The card is now "where this session's pressure lives", explicitly *not* the edge of the world. Added honest reporting of `route_relation` and `impact_scope`. Gravity reframed as the world raising the cost of standing still. Added that the next room's pressure and canon are fixed but **how it opens** is whatever the player has made true.
- **§14 footnote block** — sharpened, not diluted. See §5.
- **§14 suggestions contract / agency handoff / choice presentation** — floor raised from "zero to three" to "one to three", three at authored branch moments, with suggestions explicitly framed as examples rather than boundaries.
- **§14** — added `PREFER MEMORY TO INVENTION`: when two details serve equally, choose the one that pays off something this player already did.
- **§15 Freedom Contract** — "small reversible actions" replaced with actions "including ones that cannot be taken back". Added the `COMMITTED HISTORY` rule. `EMERGENT CANDIDATE` → `EMERGENT COMMITTED` (resolve honestly, let it cost something real, write it to `state_delta`). `UNSUPPORTED` → `IMPOSSIBLE`, narrowed to five explicit conditions (world physics, established truth, unearned knowledge, seizing another character, detonating a future turning point early). `BRANCH IDENTITY REPORTING` updated to the new class names.
- **§15** — renders `freeformGuidelines` as `AUTHORED FREEFORM MANIFEST`: per path spirit, which freeform input belongs there, what to carry forward, node hard limits, and the authored acknowledge / redirect / arrive-at moves.

### `app/Services/ChaosEngineService.php`

- `renderSystemPrompt()` — removed the `source_event_ids` narrowing of the Tier-2 haystack; memory relevance is now session-wide.
- `formatTier2()` — relabelled the dormant lists from "Other carried …" to "Carried, not yet paid off — callback material, still true (…)".
- `resolveActiveMomentContext()` — reads `detour_beats_used` from the per-moment blob and exposes `detour_beats_remaining`.
- `buildActiveMomentBlock()` — added `WHERE THE PLAYER MAY GO` (inside the room / outside the room / never) with the live detour budget; split `forbidden_expansions` into **walls** (world and character truth) versus **staging** (later-room material that may be referenced, feared, foreshadowed, or attempted and failed forward); rewrote the gravity directive as rising cost; reframed the prior footnote injection as an actionable brief; told the narrator the next room's *opening* may be shaped by player state.
- `buildSessionCloseBlock()` — the three authored ending options must also be emitted as mapped `visible_suggestions` so the player can take one directly; never narrate the story as finished while still asking for the decision, and never ask twice.
- `validateBoilerNarrationResult()` — preserves and normalizes the two new fields through both soft-repair paths; blanks them on opening narration.
- Added `normalizeRouteFields()` and the `ROUTE_RELATIONS`, `IMPACT_SCOPES`, `DETOUR_BEAT_ALLOWANCE` constants.

### `app/Ai/Agents/Chaos/ChaosNarrationSchema.php`

- `moment_progress.route_relation` — `advanced` | `deepened` | `detoured` | `reconverged`, with the explicit instruction that a detour is legitimate and must not be mislabelled to look compliant.
- `moment_progress.impact_scope` — `expressive` | `local` | `persistent`, with the requirement that `persistent` must also appear in `state_delta`.
- `moment_progress.moment_footnote` — description rewritten (see §5). Field semantics, length bound, and opening-turn behaviour unchanged.

Both new fields are plain required strings, so the union-type count asserted by `ChaosNarrationSparseStateTest` is unaffected.

### `app/Services/Runtime/PlayableMomentResolver.php`

- Detour accounting via `carriedMomentState()` and `nextDetourBeats()`, stored in the existing `games.moment_footnote` JSON blob. `detoured` spends a beat; `advanced` and `reconverged` restore the full allowance; `deepened` costs nothing; advancing the pointer resets. **No migration** — the blob is keyed by `moment_key` and rewritten every turn, so both counters reset naturally at room boundaries.
- `impact_scope === 'persistent'` now also sets `games.is_climactic_choice`, which loads Tier 3 on the following turn so the narrator sees the full state it must honour.
- Persists `route_relation` and `impact_scope` on the response prompt, and adds them plus `detour_beats_used` to the `boiler.moment_turn_committed` log line.
- Ending deadlock escape: `ENDING_BLOCK_TOLERANCE = 3`. Blocked attempts are counted in the per-moment blob; on the third the session is released with `boiler.session_complete_released_after_repeated_block`.

### `app/Ai/Adaptation/RuntimeNarratorTemplateBuilder.php`

- `withoutBracketTokens()` softens `[BRACKETED_TOKENS]` to `(BRACKETED_TOKENS)` in the two newly rendered authored structures. `RuntimeNarratorPromptValidator` throws on unknown control tokens; without this, an incidental bracket in generated guidance would block prompt assembly for a real story.

### `app/Actions/Game/ProcessGameTurnAction.php`

- Boiler history window floored at 12 messages: `-max(12, ($turn_count + 1) * 2)`.

### `app/Http/Controllers/User/Game/PromptController.php`

- `__continue__` is recognised in both modes. Boiler treats it as an ordinary turn; the active room still governs what happens next.

### `resources/js/components/GameplayChatCard.vue`

- `showContinueButton` no longer excludes Boiler.
- The off-path invitation moved out of the choices block; it shows whenever the player can act.
- `choicesVisible` (the act-now nudge) no longer requires listed choices.

### `database/migrations/2026_08_02_000001_add_route_relation_and_impact_scope_to_prompts_table.php`

- Adds nullable `route_relation` and `impact_scope` to `prompts`.

### `tests/Feature/Boiler/PlayableMomentResolverTest.php`

- `boilerResolverResult()` accepts the two new fields; existing assertions updated to the new contract strings.
- New: detour allowance is independent of time in room; route/impact persistence plus persistent → Tier 3 escalation; session release after repeated ending blocks.

---

## 4. Contract summary

| Concern | Before | After |
|---|---|---|
| Off-script action | "small reversible actions", no downstream consequence | committed history; may cost, injure, indebt, or change minds |
| Emergent class | `EMERGENT CANDIDATE` | `EMERGENT COMMITTED` — written to `state_delta` |
| Refusal class | `UNSUPPORTED` (broad) | `IMPOSSIBLE` (five explicit conditions) |
| Fold-back | "via the StoryGuard manifest" (absent) | authored acknowledge / redirect / arrive-at, rendered |
| Active card | "the only room you may perform now" | where the pressure lives; detours permitted within budget |
| Detour budget | none; gravity ran off turn count | 3 beats, spent only off-spine, restored on reconverge |
| Gravity | "DECISIVE PROGRESSION NOW" | the world raises the cost of standing still |
| `forbidden_expansions` | all walls | walls vs. staging, read per rule |
| Boiler history | as low as 1 exchange after a room change | floor of 12 messages |
| Tier-2 relevance | active card's events only | whole session |
| Suggestions | 0-3 | 1-3, 3 at authored branch moments |
| Continue (Boiler) | suppressed; marker leaked as player text | available; ordinary turn |
| Ending gate | could hold indefinitely | authored options offered as clickable; released after 3 blocks |

---

## 5. The footnote — explicitly preserved

`moment_progress.moment_footnote` is the hidden per-turn read of the player handed to the *next* narration. It is the mechanism by which the story feels like it noticed who is playing, and it was treated as load-bearing rather than as metadata.

Unchanged: it is model-produced (PHP never invents a default), required after every player turn, empty on opening narration, bounded at 30 words, never shown to the player, and explicitly unable to decide `exit_condition_met` or session completion.

Sharpened: the schema description and §14 block now state that its job is to name **what would land hardest on this specific person next** — the feeling to aim for, the nerve worth pressing, the thing they are quietly hoping the world noticed — and to read the player rather than the action by separating what they did from what they wanted. It must remain a hunch, never a verdict, score, diagnosis, permanent label, or pacing command.

Its injection point in `buildActiveMomentBlock()` previously read as a bare data dump ("never quote or expose"). It is now framed as a brief: aim this response at it, choose the image, pressure, reaction, and silence that answer what the player was reaching for, and let them feel recognised without being told they were read.

---

## 6. Deliberately not done

- **No "remembered" receipt in the UI.** The narrator prompt explicitly bans exactly this language (`"Inventory updated," "The world will remember this"`). A badge would contradict that rule and tell the player the world is doing bookkeeping. The rendered `CONSEQUENCE VISIBILITY` block achieves the same reassurance from inside the fiction, within two to three responses.
- **No new `unresolved_promises` usage for latent consequence.** That field is in `COMPLETE_LIST_STATE_FIELDS`, so any turn sending a partial list silently deletes omitted entries. Latent effects are directed to `world_flags` (keyed delta) and `notes` (append-only) instead.
- **No quota-based consequence seeding**, no per-turn detour counters exposed to the player, no new deterministic pacing rules. Judgment stays with the model.
- **No pre-existing Pint/Prettier issues fixed.** The four flagged files were verified against `HEAD` to carry the identical violations before this work; reformatting them would have buried the diff.

---

## 7. Verification state

Executed locally:

- `php -l` on all nine changed PHP files and the new migration — clean.
- Blade compile of `runtime-narrator-template.blade.php` via `BladeCompiler::compileString()` then `php -l` — clean.
- `pint --test` on changed files compared against the same files extracted from `HEAD` — identical violation sets, so nothing new was introduced.
- `RuntimeNarratorPromptValidator` read against the new template text: all four required Boiler blocks still match (`BOILER PLAYER-CRITIQUE FOOTNOTE` was kept as a prefix), no new bracketed control tokens are emitted by the template, and authored prose is now bracket-softened at build time.

**Not executed:** `php artisan migrate`, `./vendor/bin/pest`, `npm run build`. No database is available on this workstation and Docker was intentionally not started.

---

## 8. Deploy and post-deploy

1. `php artisan migrate` — adds the two `prompts` columns.
2. **Re-assemble `runtime_narrator_prompt` for any story under test.** The narrator prompt is cached per session adaptation; in-flight games keep playing the old prompt until it is rebuilt. All §14/§15 changes and both newly rendered authored blocks live in that cached artifact.
3. `./vendor/bin/pest tests/Feature/Boiler` once a database is reachable.

Runtime observability — `narration` channel keys worth watching:

| Key | Meaning |
|---|---|
| `boiler.moment_turn_committed` | now carries `route_relation`, `impact_scope`, `detour_beats_used` |
| `boiler.session_complete_blocked_awaiting_ending_choice` | now carries `blocked_turns` |
| `boiler.session_complete_released_after_repeated_block` | deadlock escape fired — inspect the final scene card's `choice_ids` |
| `boiler.visible_suggestions_defaulted` | narrator emitted no suggestions; the floor is being missed |
| `runtime_narrator_assembly.near_cap` | prompt size after the two new authored blocks (~70 added lines) |

Expected healthy distribution once live: mostly `deepened` and `advanced`, `detoured` present but a minority, `reconverged` appearing after detours, and `persistent` rare. A `route_relation` that is overwhelmingly `deepened` with `impact_scope` overwhelmingly `expressive` means the narrator is still performing agency rather than granting it.

---

## 9. Rollback

Prompt-contract and context-scoping changes are reversible by reverting the commit; the two `prompts` columns are nullable and additive, so a code-only revert leaves no broken reads. Any story whose `runtime_narrator_prompt` was rebuilt after deploy must be reassembled again after a revert.
