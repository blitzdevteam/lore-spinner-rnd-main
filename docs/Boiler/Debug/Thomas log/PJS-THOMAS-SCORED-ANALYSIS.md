# PJ's — Thomas, scored against the Boiler Fun Scoreboard

Game `01kww7cqt1cv030aybbnzqdktq` · 40 turns · sessions 1–2 · played 2026-08-15 → 08-16 · exported 08-17 09:22 UTC
Evidence: `thomas-pjs-01kww7cqt1cv030aybbnzqdktq-evidence.json`

**This log predates `265f8aa`.** Everything below is the pre-fix baseline. The last section says which findings that commit should move and which it does not touch.

---

## Headline

Agency is no longer the problem it was on Wasteland. Tom went fully rogue for ten turns in the middle of a covert medical extraction — fired a weapon in the air inside an enemy perimeter, told his team leader to fuck off, physically threw him aside, and ran the street shooting people — and the world said yes to all of it, on the turn, in the prose, with real tactical cost. Two physical blocks, both explicit and in-fiction, then delivery. That is a pass.

What fails is the **back half of consequence**. The engine priced the rampage correctly in state — including a `world_flags` entry literally named `papa_bear_memory_frame: UNRESOLVED_BLAME` — and then never charged it in prose. Papa Bear dies six turns later and not one character connects his death to the fact that Stoner blew the extraction window. The debt was recorded and then quietly forgiven.

Under that sit four platform defects that are cheap to fix and are corrupting the measurements themselves.

---

## Scores

Subjective rows (B8, C7, D2, D7, E3, F1–F5) are Tom's to fill; they are left blank. Rows the export cannot see are marked "not scorable" with the reason.

### A. Platform stability — **not scorable from this export**

| # | Score | Evidence |
|---|---|---|
| A1 | — | Client-side; export cannot see a vanished stream. |
| A2 | — | Not captured. |
| A3 | — | `logs.failures: []` is meaningless here: `files_scanned` is empty, so no failure could have been observed either way. One suspicious signal — a **19.7-minute gap between turn 9 and turn 10**, immediately after Tom's first rogue freeform ("walk out the door and fire my machine gun in the air"). Every other turn in that stretch lands in 40–90 seconds. Consistent with a 504 + retry, but unprovable without logs. |
| A4 | — | Untested. Tom's longest input all session was **93 characters** (turn 10). The ≥300-char test never happened. |
| A5 | — | Client-side. |

Pillar A cannot be scored until finding **P2** below is fixed. Do not carry Wasteland's "fail" forward to PJ's — there is no evidence either way here.

### B. Agency — **≈3.3** (strong recovery, one real hole)

| # | Score | Evidence |
|---|---|---|
| B1 Action lands as stated | **4** | Three bold off-plan actions, three literal deliveries. T9 "walk out the door and fire my machine gun in the air" → he walks out and fires into the sky. T12 "throw papa bear off me and light up the figures" → he shoves, clears the frame, kills the lamp. T22 "I spint to the truck open firing at the men in the bed" → he sprints, two men drop. |
| B2 No silent substitution | **3** | Clean for all 36 turns of session 1. One failure at **turn 39**: Tom picks "Ask Christina for his name" and the narration answers *"She gives it to you. One name."* — the name is never spoken, never quoted, and never stored. `knowledge` records "Apollo has been given the rapist's name" without the name. The player asked for a concrete thing and got a summary of receiving it. |
| B3 Obstacle is visible | **3** | Blocked twice (turns 11 and 12), one over the ≤1 bar, but both blocks are excellent: a hand flat on the chest, then a forearm across the chest into the doorframe, with Papa Bear stating the arithmetic out loud — *"You fire that weapon, we lose every man in this room."* Never an invisible wall. |
| B4 Repeat intent handled | **4** | Tom pushed the same intent across turns 9→12. Turn 3 of the repeat delivered, and each refusal was a *different escalation of the same obstacle* rather than a new excuse. This is exactly the behaviour the metric asks for. |
| B5 Consequences stick | **2** | **Weakest row in the log.** State captured everything: `notes` has "Stoner physically shoved Papa Bear to break his restraint and fired on the village", `world_flags` has `papa_bear_memory_frame: UNRESOLVED_BLAME`, the ledger has six separate rampage entries. Prose references it for exactly one turn after he gets back (turn 19–20), then drops it permanently. Papa Bear takes two rounds at turn 26 and dies at turn 31; nobody — not Yarland, not Daredevil, not Apollo — ever says the extraction was compromised because Stoner left. Turn 36 gives Stoner a clean eulogy with no cost attached. |
| B6 World says yes to hard things | **4** | 3/3 hard yeses with cost. Firing in the air produces five converging light sources and a four-minute clock; running the street produces a radio giving his position and a closed window. |
| B7 Existence-only nos | N/A | Tom never asked for anything that could not exist. |
| B8 Feel in control | — | Tom's. |

### C. Open world — **≈3.7 on what was tested**, with a state defect underneath

| # | Score | Evidence |
|---|---|---|
| C1 Go somewhere unplanned | **4** | Turns 14–18 are five consecutive turns of unauthored geography — alley, block walls, roofline overhang, a radio man — generated because Tom left the room. Real consequence: the extraction launched without him. |
| C2 Talk to someone off-script | **4** | Turn 6, freeform "what the hell happened to you all?" gets Yarland and Harding answering with actual content (downed Blackhawk, RPG, two clicks, three days). Turn 7's follow-up about the Commander produces a description that is then cross-matched against the ridge sighting and filed into `knowledge`. Not filler. |
| C3 / C4 Items | N/A | No pickup opportunity taken. See defect **P3** — `items` never changed all session. |
| C5 NPC remembers you | **3** | Within-session yes: Peleck's silent debt is tracked from turn 8 to turn 21, Yarland's eyes on the ladder pay off the pulse check. Across the session boundary, untested. |
| C6 Place remembers you | **3** | The alley Tom shot dark stays dark ("The alley is dark where you broke the light and bright everywhere else", turn 20). |
| C7 | — | Tom's. |

### D. Author voice — **≈2.8**

| # | Score | Evidence |
|---|---|---|
| D1 Word count near target | **2** | Mean **161 words/turn**. Session 2 cards budget 75–90. That is **1.8–1.9× target** — over the 1.5× bar, though far better than Wasteland's 3.1×. Freeform and button turns are identical (163 vs 161), so length is not a freeform penalty. Notably the two shortest turns, **39 (75w)** and **40 (113w)**, are the two that read best. |
| D2 | — | Tom's. |
| D3 No em-dash drift | **3** | 63 total, **1.57/turn**, over the ≤1 bar — but *stable*: last 10 turns run 1.2/turn. No late-session drift like Wasteland. |
| D4 No trailing similes | **2** | **12 in 40 turns = 3 per 10**, bar is 1 per 10. Turns 2, 4, 6, 8, 9, 10, 23, 27, 32, 36, 38, 39. All the same construction — "like a man who…", "like something heavy just got set down", "like she has been carrying it in her teeth". |
| D5 Voice stable | **4** | Turn 3 and turn 15 sit in the same register. No generic drift. |
| D6 Second person / protagonist | **3** | Two hard breaks where the **narrator** names the protagonist in third person mid-scene: turn 4 *"Stoner stops at the door frame. Raises a fist. Waits."* and turn 26 *"Stoner and Daredevil drop the remaining six."* Everything else holds "you". |
| Dialogue punctuation | — | Quotation marks appear in 21/40 turns and are correct where used. Two misses, and they land on the single most important line in session 2: turn 37 *"Then she says it. Flat. Broken. / The prick raped her."* and turn 38 *"Then Christina says it again. Same words. … The prick raped her."* Both are spoken lines rendered bare. |
| D7 | — | Tom's. |

### E. Story & canon — **≈3.0**, dragged down by pacing

| # | Score | Evidence |
|---|---|---|
| E1 Authored branches reachable | **3** | **34.2% bound** (41 of 120 suggestions) — clears the 30% bar, and comfortably beats Wasteland's 3.9%. The distribution is the real story: **21 of 40 turns offered zero bound suggestions**, and binding is exactly **0 for turns 12–18** — the entire rampage — and **0 for turns 38–40**. The moment the player goes off-script, authored branches stop being offered and do not come back inside that moment. |
| E2 Moment pacing | **2** | Cannot be measured per turn (see **P2**), but game state shows the opposite failure mode from Wasteland — **premature exit**, not stalling. `S2_M01` has `target_turns: 5` and exited after **2**. The pointer is now on `S2_M02` ("Scatter Home" — Daredevil, home, BMX) with `playable_moment_turn_count: 2`, while turns 39 and 40 are still Apollo in Christina's bedroom stopping Thanos at the door. The game's own footnote is filed under `S2_M02` but describes `S2_M01` content: *"They stopped Thanos physically but haven't spoken yet…"* with `detour_beats_used: 2`. `S2_M02` has `gravity_after: 3`, so Tom's very next turn gets yanked out of a live confrontation into a different character's homecoming. **This is the next thing he will hit.** |
| E4 Canon not broken | **4** | No era or character violations. The multi-protagonist rotation (Stoner in S1, Apollo in S2_M01) is handled correctly and announced in-fiction. |
| E5 Cold open | **4** | Both openings land the right POV and the authored image. |
| E6 Exit / gravity | **4** | No stuck room anywhere. Tom held one location for five turns during the rampage and the world kept generating pressure. |
| E3 | — | Tom's. |

### F. Fun — Tom's to fill.

---

## Platform defects found in code

### P1 — Paragraph breaks are destroyed on every path that reads narration

`ChaosEngineService::stripHtml()` is `strip_tags()` with no separator:

```863:866:app/Services/ChaosEngineService.php
    public function stripHtml(string $html): string
    {
        return mb_trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
```

Narration is stored as HTML (`<p>` per the schema), so `</p><p>` collapses to nothing and sentences fuse: *"the dog is coming straight at you.Barking."* and *"the way you look at a wound you need to map before you can treat it."His name.""*. **39 of 40 turns** in this log are affected; the one clean turn (37) is the pre-rendered cold open, which is stored with real newlines.

Three consumers, in order of damage:

1. `ProcessGameTurnAction::buildConversationHistory()` line 531 feeds `stripHtml($p->response)` back to the model **every turn**. The narrator's own prior prose is shown to it as paragraph-less run-on text. It is being few-shotted on a degraded version of itself, twelve entries deep, every turn. This is a plausible contributor to both the word-count inflation (D1) and the wall-of-prose feel.
2. `NarrationAudioService` line 111 uses raw `strip_tags` for TTS. No paragraph pauses, and words fuse across `<p>` boundaries.
3. The Cloud export command, which is why this file is hard to read.

Fix is one function: replace `</p>` with `\n\n` and `<br>` with `\n` before `strip_tags`. Everything downstream inherits it.

### P2 — Per-turn moment telemetry is exported from log files instead of the database

Every turn in this export has `moment_key`, `exit_condition_met`, `moment_result`, `next_moment_key`, and `moment_footnote` as `null`, and `logs.files_scanned` is `[]`.

Those columns exist and are correctly written. `PlayableMomentResolver` persists all of them on the response prompt:

```285:296:app/Services/Runtime/PlayableMomentResolver.php
            $responsePrompt = $lockedGame->prompts()->create([
                'source_prompt_id' => $lockedSource->id,
                'session_number' => $lockedGame->current_session_number,
                'moment_key' => $currentMoment->moment_key,
                'exit_condition_met' => (bool) $progress['exit_condition_met'],
                'moment_result' => $momentResult,
                'moment_footnote' => $footnote !== '' ? $footnote : null,
                'route_relation' => $routeRelation !== '' ? $routeRelation : null,
                'impact_scope' => $impactScope !== '' ? $impactScope : null,
```

The migration that added them (`2026_07_29_000001_add_boiler_moment_fields_to_prompts_table.php`) says in its own docblock: *"so play evidence does not depend on ephemeral narration logs."* But the export command in `laravel-cloud-commands.md` §6.1 still globs `storage/logs/narration*.log` and reads `$c["moment_key"] ?? null` from the parsed lines. On Laravel Cloud the Commands container has an empty `storage/logs`, so the join always yields nothing.

Change the export to read `$p->moment_key`, `$p->exit_condition_met`, `$p->moment_result`, `$p->moment_footnote`, `$p->route_relation`, `$p->impact_scope` off the prompt row, keeping the log join only as a supplement for repairs and failures. **Until this is done, E1 and E2 are unscoreable on every future export**, and `logs.repairs: []` / `logs.failures: []` will keep reading as "clean" when they mean "not observed".

Related: the working tree currently has an **uncommitted revert** of `config/logging.php` that removes the `narration-stderr` mirror. That mirror is the only thing making these events visible in Cloud Logs. It should not be committed.

### P3 — World state does not reset at the scene or session boundary

`location` is `"Brownstone, Queens — Christina's bedroom, full family present"`. At the same moment:

- `conditions`: "Treeline fire losing angle — rafts moving beyond effective range", "Papa Bear loaded onto raft — unresponsive", "Two rafts moving seaward"
- `items`: MP-5, silent dog whistle, night vision goggles, medical trauma kit, encrypted radio, combat weapons loadout
- `object_states`: rubber rafts against the submarine hull, the black tarp, the whistle

Apollo is standing in a Queens bedroom carrying an MP-5 and night vision goggles, on a beach that is two days and one ocean away. Any C3/C4 item test in session 2 will hallucinate off this.

The append-only lists are also unbounded and duplicating: `knowledge` 49, `player_style` 35, `emotional_ledger` 32, `notes` 28 after 40 turns. `notes` contains four near-identical entries about Apollo's four-minute window. That is a growing token tax on every subsequent turn.

### P4 — `player_style` is a per-turn journal, not a profile

35 entries for 40 turns, one appended per turn, each phrased as a full observation ("When the team leader goes down, breaks back without calculating cost — treats the man as the mission"). It should be a distilled, capped profile. As written it is the largest single block of context growth in the game and it re-describes the same player from thirty-five angles.

### P5 — The name was withheld and is now unusable

Turn 39, Tom's explicit ask, answered with *"She gives it to you. One name."* No name in the prose, no name in `knowledge` ("Apollo has been given the rapist's name by Christina" — no name), no name in `emotional_ledger`. Session 2's entire remaining spine is about what Apollo does with that name and the story does not know what it is. Every future turn has to keep saying "the name" or invent one.

---

## What `265f8aa` should and should not move

**Should move:**

- **Dialogue punctuation rule** → directly targets the turn 37 / 38 misses. This log confirms the bug is real and, tellingly, that it fires on the emotionally loaded lines rather than ordinary conversation.
- **Consequence currency + "THE WAY BACK IS NOT A RESET" + "EVERYONE PRESENT SAW IT"** → aims squarely at B5, the weakest row here. Worth noting the diagnosis is slightly different from the commit message's: the engine *did* write the cost into `world_flags` and `notes` as the new rule asks. What failed was surfacing it in prose afterwards. The clause that should actually fix this log is *"turns later, unprompted, one of them still refers to it"* — not the state-delta instruction, which was already being followed.

**Unverified by this log:**

- **First-person-in-dialogue rule.** Zero instances of a character naming themselves in third person while speaking anywhere in these 40 turns. That bug came from the Speckled Band run; PJ's does not reproduce it. Do not assume this log validates the fix.

**Not addressed at all:**

- Third-person **narrator** slips at turns 4 and 26 (D6). The new rule covers characters speaking about themselves; it says nothing about the narrator dropping "you" and using the protagonist's name. Both misses in this log are narrator voice.
- Word count at 1.8× budget (D1) and simile density at 3× the bar (D4).
- The binding collapse to 0% for the seven turns following a freeform break (E1).
- The premature `S2_M01` exit and the two-turn narrator/pointer desync (E2).
- All five platform defects P1–P5.

---

## Suggested order

1. **P2** — until telemetry lands in the export, half the scoreboard is guesswork and "no failures" keeps meaning "no observation".
2. **P1** — one function, and it improves the model's own context, the TTS, and every export at once.
3. **P3 / P4** — state hygiene at the session boundary, before C3–C6 can be tested at all.
4. **E2** — the `S2_M02` desync will bite Tom on his very next turn.
5. **D1 / D4** — word budget and simile density; the two shortest turns in the log are the two best.
