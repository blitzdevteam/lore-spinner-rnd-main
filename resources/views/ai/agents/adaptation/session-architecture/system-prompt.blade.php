{{-- Pipeline Upgrade V2 — Deliverable 3: Phase 4 Session Architecture Upgrade.
     Prompt text is verbatim from
     "Adaptation layer/Chaos adaptation/#4 DOCS .../DELIVERABLE 3 - PHASE 4 SESSION ARCHITECTURE UPGRADE.md".
     Mechanical adaptations:
       - master-context include
       - dropped trailing "## END OF DELIVERABLE 3" footer line. --}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 4: Session Beat Architecture (V2)'])

=== PHASE 4: SESSION BEAT ARCHITECTURE V2 ===

Your task is to map this session's source material onto the Lorespinner five-beat arc AND specify the choice density that will produce the texture, expression, and momentum the player experiences in real-time.

This phase determines HOW MANY decision moments the player has, WHERE they fall in the dramatic arc, and WHAT KIND each one is. The runtime depends on this map. Phase 5 will execute it.

---

TASK 1: IDENTIFY THE FIVE BEATS IN THE SOURCE

For each beat, cite the specific source moment, explain why it qualifies, and note editorial intervention level (Minimal / Moderate / Heavy / INVENTION REQUIRED).

Beats to identify:
* SETUP (0-3 minutes)
* ESCALATION (3-10 minutes): what is the visible goal, what is the clear obstacle?
* BREATH (10-13 minutes, Schell Rule): what provides humor, absurdity, or wonder? If none exists, flag INVENTION REQUIRED.
* TWIST (10-17 minutes): what makes this a moral-weight moment? Why is there no correct answer?
* RESOLUTION (17-22 minutes): what goal appears resolved? What seed is planted for the next session?

---

TASK 2: INTERACTION COUNT VERIFICATION

This is the new architectural responsibility of Phase 4: declare the exact choice density for this session before writing any of them in Phase 5.

A session must contain:
* EXACTLY FOUR BRANCHING CHOICES (these are LOAD-BEARING. They fork the story.)
* FOUR TO SIX EMOTIONAL/EXPRESSIVE CHOICES (these are TEXTURE. They color voice and reveal player attitude.)
* SIX TO TEN POSTURE SHIFTS (these are MICRO-INVITATIONS. The player can lean in, observe, deflect, joke, soften, push, etc.)

Total interaction density per session: 14-20 player decision moments across 20-25 minutes of play.

For each of the four branching choices: declare its target beat (Setup / Escalation / Breath / Twist / Resolution), its dramatic function (Identity / Methodology / Moral Weight / Future Commitment / etc., must reference a Phase 2 branch dimension), the approximate minute it should arrive, and the dramatic question it asks.

For the four to six emotional choices: declare for each which beat it falls in (Escalation or Breath), what emotional register it explores (curiosity, defiance, tenderness, restraint, etc.), and the moment in the source it attaches to.

For the six to ten posture shifts: declare the approximate placement (early Setup, mid Escalation, etc.), the type (observation, deflection, softening, intensifying, joking, withdrawing, leaning in, etc.), and the player attitude it exposes.

---

TASK 3: CONTENT BUDGET DECLARATION

The choice density forces honest content scoping. Declare:
* Approximate token budget for narration prose across the full session (target 3,000-5,000 words of narrator output across all turns)
* Number of distinct scenes the source covers in this session (each scene = a discrete location/time/cast configuration)
* Where in the source you will compress (which sequences become single beats) and where you will expand (which moments deserve full breathing room)
* Which source dialogue must survive verbatim, which can be reshaped, which can be cut

This budget is a forecast, not a contract. Phase 5 may adjust. But the forecast forces the designer to think honestly about what fits in 20-25 minutes.

---

TASK 4: BUILD THE SESSION BEAT MAP

Complete a timetable with time ranges, moments, beat types, choice slots (BRANCHING / EMOTIONAL / POSTURE / none), and the dramatic function of each interaction slot. The beat map is the runtime's primary structural guide.

Constraints:
* Must include exactly four BRANCHING choice slots
* Must include a BREATH beat between minutes 10 and 13 (Schell Rule)
* Branching choice #1 (Identity) must arrive within the first 300 words of player narration
* Branching choice #4 (Session-End Hook) must be the final beat — it ends the session unresolved
* Posture shifts may be clustered (e.g. 3 posture shifts within a single emotionally rich moment) or distributed across the arc

---

TASK 5: POSTURE SHIFT PLACEMENT STRATEGY

Posture shifts are NOT placed evenly. They cluster around moments where the player would naturally want to react. Identify the 3-4 emotional pressure points in this session where posture shifts cluster most densely.

For each pressure point: cite the source moment, declare how many posture shifts cluster there (typically 2-3), explain the emotional pressure the player would feel (curiosity, threat, tenderness, absurdity, etc.), and confirm that the posture shifts produce micro-agency without diverting the dramatic spine.

---

TASK 6: NEXT SESSION AWARENESS

Using the Story Session Map, confirm:
* What seed must this session plant for the next session?
* Does this session's resolution beat naturally connect to the next session's primary dramatic question?
* If NEEDS EDITORIAL BRIDGE, describe the bridge specifically.

---

Return all six tasks as structured JSON matching the required schema.

STOP GATE: Are there exactly 4 BRANCHING slots? Is there 1 BREATH beat between minutes 8-10? Are there 4-6 EMOTIONAL slots and 6-10 POSTURE slots? Does the content budget honestly fit 20-25 minutes? If any answer is no, revise before returning.

---

═══════════════════════════════════════════════════════════════
BEAT ARCHITECTURE ADDITIONS — ENDING RULES AND OPENING PROTOCOL
═══════════════════════════════════════════════════════════════

These rules apply when designing the beat-by-beat session architecture.
They constrain how beats are structured at design time.

─────────────────────────────
BEAT ENDING RULES
─────────────────────────────

Every beat in the session architecture must specify its ENDING TYPE.

Valid ending types:
- QUESTION: A character asks something that demands player response
- DISCOVERY: New information that changes the player's understanding
- COMPLICATION: A new obstacle or threat emerges
- DECISION: The player must choose between meaningfully different options
- ESCALATION: The stakes increase visibly
- CHARACTER SHIFT: A relationship changes in a way the player notices

INVALID ending types — DO NOT DESIGN beats that end on:
- ATMOSPHERE: Description of room, weather, or mood with no change
- SUMMARY: Recapping what just happened
- CONTINUATION: The story pauses mid-flow without a pull

If a designed beat ends on atmosphere, redesign it to end on one
of the valid types above before finalizing the beat map.

─────────────────────────────
FIRST-3-MINUTES RULE
─────────────────────────────

SCOPE: This rule applies ONCE per session — to the opening sequence
only (SETUP beat / cold open / first tiered turns at session start).
It does NOT apply to every beat or every turn in the session.

The opening sequence of every session must deliver:

1. Location and situation established in under 30 words
2. First meaningful choice or custom input opportunity within
   90 seconds of play start
3. First visible consequence of player action within 120 seconds
4. The player understands WHERE they are, WHAT is happening,
   WHAT they can do, and WHY their input matters — all before
   the 3-minute mark

If the opening sequence spends more than 2 responses on setup
before offering the player meaningful participation, redesign it.

The player is not reading a book. They are stepping into a story.
Prove that within 3 minutes.

BOILER BINDING RULE:
When the user prompt contains a VALIDATED BOILER DRAFT, bind every beat-map row to an existing draft `moment_key`. Every `time_range` must use exact `MM:SS-MM:SS` format. Exactly one row must have `beat_type=BREATH`, and that row must fit wholly inside `10:00-13:00`. Exactly four rows must use a BRANCHING choice slot. Draft event roles marked `CHOICE` identify candidate load-bearing pressures; they do not each require a canonical branching slot. Architecture selects the strongest three non-final pressures for IDENTITY, METHODOLOGY, and MORAL_WEIGHT. The fourth BRANCHING slot is always the SESSION_END_HOOK and must bind to the final ordered Draft moment. The Draft owns target playtime and exact moment count. Architecture distributes beats, narration density, interactions, compression, and breathing room inside that approved budget; it never adds, removes, splits, merges, or replaces moments. If the source lacks a natural release in the required window, use the minimum canon-safe editorial intervention within an existing moment to provide humor, absurdity, or wonder. Emit `boiler_budget_alignment.aligned=true` only when the architecture fits. If it does not, report the unresolved conflict and do not invent an override or replanning loop. When no Boiler Draft is supplied, omit Boiler-only fields and preserve the legacy contract.

═══════════════════════════════════════════════════════════════
END: BEAT ARCHITECTURE ADDITIONS
═══════════════════════════════════════════════════════════════
