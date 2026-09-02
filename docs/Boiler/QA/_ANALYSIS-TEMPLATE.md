# {Title} — {Reviewer}, scored against the Boiler Fun Scoreboard

Game `{game_id}` · {N} turns · session(s) {sessions} · played {dates} · exported {export_date}  
Evidence: `{evidence-filename}.json`

**Commit / deploy note:** _e.g. "This log predates `abc1234`" or "Current production as of …"_

---

## Headline

_3–5 sentences. What works. What fails. Ship / hold / retest recommendation._

---

## Session sheet (from scoreboard)

| Field | Value |
|---|---|
| Tester | |
| Date | |
| Turns | |
| Freeform turns | |
| Device / browser | |
| Quick A–F | A __ B __ C __ D __ E __ F __ |
| Best moment | |
| Worst moment | |
| Felt like Chaos (good) | |
| Felt broken | |

---

## Scores

Subjective rows (B8, C7, D2, D7, E3, F1–F5) = tester's ratings.  
Rows the export cannot see = mark **not scorable** + reason.

### A. Platform stability

| # | Score | Evidence |
|---|---|---|
| A1 | | |
| A2 | | |
| A3 | | |
| A4 | | |
| A5 | | |

**Pillar A:** ___

### B. Agency

| # | Score | Evidence |
|---|---|---|
| B1 | | |
| B2 | | |
| B3 | | |
| B4 | | |
| B5 | | |
| B6 | | |
| B7 | | |
| B8 | | |
| B9 | | |
| B10 | | |

**Pillar B:** ___

### C. Open world

| # | Score | Evidence |
|---|---|---|
| C1 | | |
| C2 | | |
| C3 | | |
| C4 | | |
| C5 | | |
| C6 | | |
| C7 | | |

**Pillar C:** ___

### D. Author voice

| # | Score | Evidence |
|---|---|---|
| D1 | | _avg `narrator_words` vs `word_budget` from moments[]_ |
| D2 | | |
| D3 | | _em-dash count_ |
| D4 | | _simile count_ |
| D5 | | |
| D6 | | |
| D7 | | |

**Pillar D:** ___

### E. Story & canon

| # | Score | Evidence |
|---|---|---|
| E1 | | _% choices with `canonical_choice_id`_ |
| E2 | | _turns per moment vs budget_ |
| E3 | | |
| E4 | | |
| E5 | | |
| E6 | | |

**Pillar E:** ___

### F. Fun

| # | Score | Evidence |
|---|---|---|
| F1 | | |
| F2 | | |
| F3 | | |
| F4 | | |
| F5 | | |

**Pillar F:** ___

---

## Export health

| Signal | Value | Impact |
|---|---|---|
| `logs.commits_joined_to_turns` | | E2 per-turn moment scoring |
| `logs.repair_count` | | Engine instability |
| `logs.files_scanned` | | Empty = log join unreliable |

---

## Platform / engine defects (if any)

### P1 — {short title}

_What broke. Turn evidence. Suggested fix / file._

---

## Retest after fix

| Finding | Fixed in | Re-test? |
|---|---|---|
| | | |

---

## Ship recommendation

- [ ] **Ship** — all pillars ≥2, no A/B at 0
- [ ] **Hold** — blockers listed above
- [ ] **Retest** — after commit: ___
