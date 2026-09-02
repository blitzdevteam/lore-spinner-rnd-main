# Boiler QA — Title tracker

Update when a reviewer **starts**, **exports**, or **finishes analysis**.  
Process: [BOILER-QA-PLAYBOOK.md](../../BOILER-QA-PLAYBOOK.md) · Rubric: [BOILER-FUN-SCOREBOARD.md](../../BOILER-FUN-SCOREBOARD.md)

**Status:** `—` not started · `playing` · `exported` · `scored` · `blocked`

---

## Speckled Band (`the-adventure-of-the-speckled-band`)

| Reviewer | Status | game_id | Turns | Evidence | Analysis | A | B | C | D | E | F | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Curt | — | | | | | | | | | | | |
| Thomas | scored | `01kt2mt8c2jvw7rpp6f2v3ptyp` | 65 | [Debug/thomas-…evidence.json](../Debug/thomas-01kt2mt8c2jvw7rpp6f2v3ptyp-evidence.json) | — | | | | | | | Legacy export; write scored analysis |
| Mike | — | | | | | | | | | | | |
| Tiffany | — | | | | | | | | | | | |
| Shervin | — | | | | | | | | | | | |

**Title rollup:** _pending — need ≥2 scored analyses_

---

## Wasteland (`wasteland`)

| Reviewer | Status | game_id | Turns | Evidence | Analysis | A | B | C | D | E | F | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Curt | — | | | | | | | | | | | |
| Thomas | exported | `01kyk029yz3y3y39b05d60d53x` | 11 | [Thomas log/…evidence.json](../Debug/Thomas%20log/Thomas%20latest%20test%20on-wasteland-01kyk029yz3y3y39b05d60d53x-evidence.json) | — | | | | | | | Baseline in scoreboard |
| Mike | — | | | | | | | | | | | |
| Tiffany | — | | | | | | | | | | | |
| Shervin | — | | | | | | | | | | | |

**Title rollup:** _pending_

---

## PJ's (`pjs`)

| Reviewer | Status | game_id | Turns | Evidence | Analysis | A | B | C | D | E | F | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Curt | — | | | | | | | | | | | |
| Thomas | scored | `01kww7cqt1cv030aybbnzqdktq` | 40 | [Thomas log/thomas-pjs-….json](../Debug/Thomas%20log/thomas-pjs-01kww7cqt1cv030aybbnzqdktq-evidence.json) | [PJS-THOMAS-SCORED-ANALYSIS.md](../Debug/Thomas%20log/PJS-THOMAS-SCORED-ANALYSIS.md) | — | ≈3.3 | ≈3.7 | ≈2.8 | ≈3.0 | — | Reference analysis |
| Mike | — | | | | | | | | | | | |
| Tiffany | — | | | | | | | | | | | |
| Shervin | — | | | | | | | | | | | |

**Title rollup:** _need second reviewer_

---

## Treasure Island (`treasure-island`)

| Reviewer | Status | game_id | Turns | Evidence | Analysis | A | B | C | D | E | F | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Curt | — | | | | | | | | | | | |
| Thomas | — | | | | | | | | | | | |
| Mike | — | | | | | | | | | | | |
| Tiffany | — | | | | | | | | | | | |
| Shervin | — | | | | | | | | | | | |

**Title rollup:** _not started_

---

## New title template

Copy when a story completes Boiler adaptation:

```markdown
## {Title} (`{slug}`)

| Reviewer | Status | game_id | Turns | Evidence | Analysis | A | B | C | D | E | F | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Curt | — | | | | | | | | | | | |
| Thomas | — | | | | | | | | | | | |
| Mike | — | | | | | | | | | | | |
| Tiffany | — | | | | | | | | | | | |
| Shervin | — | | | | | | | | | | | |

**Title rollup:** _pending_
```

Create folder: `docs/Boiler/QA/{slug}/`
