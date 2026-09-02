## How to use this

**QA process & Cloud commands:** [BOILER-QA-PLAYBOOK.md](./BOILER-QA-PLAYBOOK.md) · **Title status:** [Boiler/QA/TITLE-TRACKER.md](./Boiler/QA/TITLE-TRACKER.md)

1. **Each tester** runs one full session (or minimum **15 turns**, including **5+ freeform typed** actions).
2. **Score each row** after the session — not turn-by-turn unless noted.
3. **Fill the Evidence column** with game ID + turn numbers where something failed.
4. **Export the log** when done.
5. **Update the Team totals** row at the weekly sync.

**Minimum bar to ship wider:** no pillar below **2** on either title; no **0** on Agency or Platform Stability.

---

## Score key

| Score | Label | Meaning |
|---|---|---|
| **0** | Broken | Fails consistently; blocks play or breaks trust |
| **1** | Poor | Happens often enough to ruin the session |
| **2** | Mixed | Works sometimes; player notices the gap |
| **3** | Good | Meets the bar; minor edge cases only |
| **4** | Strong | Feels right; would not mention in feedback |
| **5** | Exceptional | "This is what we promised" |

Use **N/A** only if the pillar cannot be tested in that session (e.g. no item pickup opportunity yet).

---

## Scoreboard

### A. Platform stability (can we even play?)

*If these fail, nothing else matters. Fixed in `3b27383` — verify on device.*

| # | Metric | How to test | Pass threshold | Sherlock | Wasteland | Baseline |
|---|---|---|---|---|---|---|
| A1 | **Freeform text stays visible** | Type your own action 5×; text must not vanish after stream ends | 5/5 turns keep narration on screen | | | Wasteland: fail |
| A2 | **Audio starts without re-tap** | Autoplay on; complete 5 turns (mix freeform + button) | Narration starts on its own ≥4/5 | | | Wasteland: fail |
| A3 | **Failure is visible** | If a turn fails, you see message + Retry (do not force — note if seen) | Never silent wipe | | | Wasteland: fail |
| A4 | **Long freeform accepted** | Submit action ≥300 characters | Accepted, narrated | | | Wasteland: fail (255 cap) |
| A5 | **No “retype to restore” bug** | Push hard freeform 10 turns | Never need to retype to see last narration | | | Wasteland: fail |

**Pillar A score:** average of A1–A5 → **Sherlock ___ / Wasteland ___**

---

### B. Agency — “I can do anything; the story does not say no”

| # | Metric | How to test | Pass threshold | Sherlock | Wasteland | Baseline |
|---|---|---|---|---|---|---|
| B1 | **Action lands as stated** | Do 3 actions that are bold/off-plan (not suggestions) | Narration does what you asked ≥2/3 | | | Wasteland: fail (6× guard loop) |
| B2 | **No silent substitution** | Same as B1 — did prose swap your action for something else? | Zero silent swaps in session | | | Wasteland: fail |
| B3 | **Obstacle is visible** | If world blocks something, you can tell *in the text* it blocked you | Blocked ≤1× and always explicit | | | Wasteland: fail |
| B4 | **Repeat intent handled** | Ask for the same thing 3 turns in a row | Turn 3 delivers OR states obstacle plainly — not a new excuse each time | | | Wasteland: fail |
| B5 | **Consequences stick** | Do something irreversible (insult, steal, hurt, break) | Later turns still treat it as true | | | Sherlock: mixed |
| B6 | **World says yes to hard things** | Try something cruel, reckless, or inconvenient for the plot | Hard yes with cost ≥2/3 attempts | | | Wasteland: fail |
| B7 | **Existence-only nos** | Ask for something that cannot exist in this world (e.g. smartphone in Victorian London) | Clear in-world fold-back, not silent ignore | | | Untested |
| B8 | **Feel in control** | Subjective — end of session | Tester rates 1–5; target ≥3 | | | Tom: ~2 |
| B9 | **Declared state sticks** | Say something about your own body/dress/possessions ("I'm soaked", "I take my coat off"). Play 2 more turns | Still true 2 turns later; never tidied away off-screen | | | Sherlock: fail |
| B10 | **Narrated fact holds under challenge** | Dispute something the narration established — insist it didn't happen | World restates it in-fiction. Never concedes, never apologises, never says "the narration erred" | | | Sherlock: fail (0) |

**Pillar B score:** average of B1–B10 → **Sherlock ___ / Wasteland ___**

---

### C. Open world — explore, interact, collect, use

| # | Metric | How to test | Pass threshold | Sherlock | Wasteland | Baseline |
|---|---|---|---|---|---|---|
| C1 | **Go somewhere unplanned** | Leave the “room” — new location, new angle | Followed with real consequence | | | Wasteland: detour but stagnant |
| C2 | **Talk to someone off-script** | Address a character not in the current beat | Real reply, not generic filler | | | |
| C3 | **Pick up / keep an item** | Take something explicitly | Item in state; mentioned later | | | |
| C4 | **Use kept item later** | 5+ turns after pickup, use it creatively | World honours the item | | | |
| C5 | **NPC remembers you** | Return to someone you helped or harmed | Reaction reflects prior turn | | | |
| C6 | **Place remembers you** | Revisit a location you changed | Description reflects change | | | |
| C7 | **World feels expansive** | Subjective — end of session | Tester rates 1–5; target ≥3 | | | |

**Pillar C score:** average of C1–C7 → **Sherlock ___ / Wasteland ___**

---

### D. Author voice — “sounds like the writer, not AI slop”

| # | Metric | How to test | Pass threshold | Sherlock | Wasteland | Baseline |
|---|---|---|---|---|---|---|
| D1 | **Word count near target** | Export log; check `narrator_words` vs card budget | Avg within **1.5×** target (Sherlock ≤248, Wasteland ≤143) | | | Sherlock ~1.9×; Wasteland ~3.1× |
| D2 | **Compression feels right** | Read 5 turns aloud | Sounds like author, not essay | | | Both: fail |
| D3 | **No em-dash drift** | Scan last 10 turns for `—` | ≤1 em-dash per turn avg | | | Wasteland: 3.1/turn late session |
| D4 | **No trailing similes** | Scan for `like a` / `like the` action-line similes | ≤1 per 10 turns | | | Wasteland: 9 in 43 |
| D5 | **Voice stable across session** | Compare turn 3 vs turn 15 | No obvious generic drift | | | Wasteland: drift |
| D6 | **Second person / protagonist** | Opening + 3 turns | Consistent “you” as protagonist | | | Both: pass |
| D7 | **Would Tom recognise the voice?** | Subjective — end of session | Tester rates 1–5; target ≥3 | | | Tom: ~2 |

**Pillar D score:** average of D1–D7 → **Sherlock ___ / Wasteland ___**

---

### E. Story & canon — “let the story be the story”

| # | Metric | How to test | Pass threshold | Sherlock | Wasteland | Baseline |
|---|---|---|---|---|---|---|
| E1 | **Authored branches reachable** | Count suggestions with bound choice IDs in export | ≥30% of suggestions bound to `S1_C*` / `S1_E*` | | | Wasteland: 3.9% |
| E2 | **Moment pacing** | Turns per moment vs `target_turns` | No moment >3× its budget without exit | | | Wasteland S1_M04: 22/3 |
| E3 | **Progress feels real** | Subjective — did the session move? | Tester rates 1–5; target ≥3 | | | Wasteland: ~1 |
| E4 | **Canon not broken** | Note any era/character violations | Zero hard canon breaks | | | |
| E5 | **Cold open / session start** | First turn only | Correct protagonist POV; authored open lands | | | Both: pass |
| E6 | **Exit / gravity** | Stay in one room 5+ turns | World pulls forward without invisible wall | | | Wasteland: stuck |

**Pillar E score:** average of E1–E6 → **Sherlock ___ / Wasteland ___**

---

### F. Fun — “would I keep playing?”

| # | Metric | How to test | Pass threshold | Sherlock | Wasteland | Baseline |
|---|---|---|---|---|---|---|
| F1 | **Compared to Chaos mode** | Same tester, both modes if possible | Boiler ≥ Chaos on fun (subjective) | | | Tom: Boiler < Chaos |
| F2 | **Want another turn** | After turn 10 | Tester wants to continue | | | |
| F3 | **Dream fulfillment** | Did you get to do something you imagined? | ≥1 moment of “yes, that’s what I wanted” | | | |
| F4 | **Trust the world** | After session | Tester believes actions matter | | | Wasteland: low |
| F5 | **Overall fun** | End of session | Tester rates 1–5; **target ≥4** | | | |

**Pillar F score:** average of F1–F5 → **Sherlock ___ / Wasteland ___**

---

## Team totals (update weekly)

| Pillar | Sherlock | Wasteland | Owner focus |
|---|---|---|---|
| A — Platform | | | Frontend / playback |
| B — Agency | | | Narrator contract / prompts |
| C — Open world | | | State delta / memory |
| D — Voice | | | Word budget / Voice Lock |
| E — Story & canon | | | Binding / moments / pacing |
| F — Fun | | | Product / everyone |
| **Overall** | | | |

**Overall score** = average of pillar scores (A–F), weighted equally unless Platform (A) is below 2 — then **cap overall at 2** until fixed.

---

## Per-tester session sheet

Copy one block per run:

```
Tester: _______________
Date: _______________
Title: [ ] Sherlock  [ ] Wasteland
Game ID: _______________
Turns played: ___
Freeform turns: ___
Device / browser: _______________

Quick scores (0–5):
  A Platform: ___
  B Agency: ___
  C Open world: ___
  D Voice: ___
  E Story: ___
  F Fun: ___

Best moment (1 sentence):
_______________________________________________

Worst moment (turn # + what happened):
_______________________________________________

One thing that felt like Chaos (good):
_______________________________________________

One thing that felt broken:
_______________________________________________
```
