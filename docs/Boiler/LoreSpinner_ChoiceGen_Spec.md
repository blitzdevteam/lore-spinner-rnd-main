# LoreSpinner — Choice Generation Logic

**Spec: 3 Generated Options Per Output**
Reference for AI programmer · Fixed 2nd-person protagonist · Memory + StoryGuard active

---

## Core Principle

The three generated options exist to do two things the player's free-text slot cannot:

1. **Out-imagine the player.** Since the player can always type their own path, any merely-*reasonable* option is redundant — they'd have thought of it themselves. Every generated option must clear the bar: *more tempting than what I'd have typed.*
2. **Pressure StoryGuard on purpose.** Challenge = fun. Generate options that lean toward the canon edge, then let the existing absorption/refraction layer catch them.

Options are pre-authored "yes, and" attempts — never safe defaults.

---

## Hard Gates

Reject and regenerate any option that fails. A valid trio must satisfy **all five**:

1. **Orthogonality** — no two options share the same *axis*, *target*, or *consequence domain*.
2. **Distinct anchor** — each option pulls a *different open loop* from live memory (unmet goal / threatened relationship / unanswered question / coveted object / unpaid debt). No two options pull the same loop.
3. **Distinct consequence domain** — the three touch three different domains: `relationship | world-state | information | resource | identity`.
4. **In-range band** — each option is something *this protagonist* could plausibly do (CharacterGuard). Not at the puppet-edge, not all dead-center. Spread across the interior of their canon range.
5. **StoryGuard tension** — **at least one** option actively probes a canon boundary the absorption layer will have to catch. Never a fully-safe trio.

---

## The Default Trio

Assign one option to each slot. All three anchored to a **different character contradiction** pulled from the story bible.

| Slot | Function | Domain | Temptation | Horizon |
|---|---|---|---|---|
| **PURSUIT** | Advances the protagonist's *own* active goal | world-state / resource | gain | immediate |
| **PROVOCATION** | The protagonist's *canon shadow* (established darker/wilder register), dared in 2nd person. Should most often trip StoryGuard. Never generic edginess — *this character's* shadow only. | world-state (unpredictable) | transgression | variable |
| **DEEPENING** | Turns inward/sideways — a character, a secret, the self. Does not advance plot. Plants or pays a callback. | relationship / identity / information | curiosity + connection | seeds long |

---

## Memory Integration

### Read (input to generation)

Anchor options to three registers before generating:

- **Deed** — what literally happened → informs world-state options
- **Perception** — what past acts revealed about "you" → informs in-range band + NPC framing
- **Rapport** — running trust / fear / debt / affection per NPC → informs which loops are hot

### Write (output of the choice)

Every option must declare, **at generation time**, which register(s) it writes to if chosen. An option that writes to nothing is generic — reject it.

---

## Phase Modulation

Same gates, different bias:

- **On-spine** → bias **DEEPENING**. High meaning-divergence, low destination-divergence.
- **Off-spine (excursion turns 1–2)** → escalate divergence; let world-state actually bend; reward the departure.
- **Return beat** → generate three *different roads back*, each honoring what the excursion revealed.

---

## The Shy-Player Guarantee

*The entire point of the system.* The three options must be individually strong enough that picking one delivers **100% agency without free-text.**

**Test per option:** *Would a player be glad they picked this even though they didn't invent it?*

If any option is merely functional rather than tempting → reject the trio. Three **differently-exciting** options — never one exciting + two filler.

---

## Rejection-Sampling Loop

```
generate candidate trio
  → run hard gates 1–5 (boolean; any fail → regenerate the failing option)
  → score soft layers:
        temptation-variety
        register-spread
        callback-potential
        anchor-heat
  → if any option scores below threshold → regenerate that option only
  → ship when: all gates pass AND all three clear soft threshold
```

**Fail closed.** A generic trio must be *rejectable by the logic*, not caught by eye.

---

## One-Line Summary

> Generate 3 options that are orthogonal by construction (different axis / target / domain / anchor / register), each in-character but spread across the protagonist's canon range, at least one probing StoryGuard, each writing to a distinct memory register — and reject-and-regenerate any option that isn't independently tempting enough to give a non-typing player full agency.
