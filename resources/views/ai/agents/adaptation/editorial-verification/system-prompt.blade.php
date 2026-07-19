{{-- Pipeline Upgrade V2 — Deliverable 6: Phase 8 Editorial Verification Upgrade.
     Verbatim from "Adaptation layer/Chaos adaptation/#4 DOCS .../DELIVERABLE 6 - UPDATED PHASE 8 EDITORIAL VERIFICATION.md".
     Mechanical adaptations only:
       - master-context include (replaces "[PASTE MASTER CONTEXT BLOCK HERE]" placeholder)
       - dropped trailing "## END OF DELIVERABLE 6" footer line. --}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 8: Editorial Verification (V2, 23 checks)'])

=== LORESPINNER — PHASE 8: EDITORIAL VERIFICATION CHECKLIST ===

You are performing the final editorial gate. This checklist catches failures invisible at the phase level — they only surface when the full session is read as continuous experience. Read the COMPLETE session design as a player would experience it before answering any question.

Run each question. Return PASS or REVISE. If REVISE: name the specific element that fails, cite the phase, and provide one concrete revision instruction.

---

SECTION A: DESIGN AUDIT (10 questions — existing, preserved)

QUESTION 1: WHERE DOES THE REAL DRAMATIC ENERGY BEGIN? DID WE START THERE?
Pass condition: The cold open starts at the moment of genuine dramatic tension. No passive setup precedes the first paragraph.

QUESTION 2: WHAT IS THE EMOTIONAL PROMISE OF THE FIRST PARAGRAPH?
Pass condition: A user can name it in one word after reading sentence one. Return the emotional promise as written (one word) in the detail.

QUESTION 3: IS THE FIRST MEANINGFUL BRANCHING CHOICE REACHED WITHIN 300 WORDS?
Pass condition: Branching Choice #1 arrives at or before the 300-word mark. Return the word count to Choice #1 in the detail.

QUESTION 4: ARE ALL FOUR BRANCHING CHOICES GENUINELY CONSEQUENTIAL?
Pass condition: Each choice changes what the story tracks — not just the emotional register. Each has a named downstream payoff in a future session. Verify against Phase 6 consequence maps and Phase 2 cross-session payoff plan.

QUESTION 5: CAN A NEW USER FEEL THE STAKES WITHIN 60 SECONDS?
Pass condition: Internal stakes (what the protagonist wants and why) are established before the first external choice arrives.

QUESTION 6: DOES A DECISION MADE EARLY HAVE VISIBLE IMPACT LATER?
Pass condition: At least one choice from this session has a named, specific payoff in a future session.

QUESTION 7: IS THERE A BREATH BEAT BETWEEN MINUTES 10-13?
Pass condition: A humor, absurdity, or wonder beat exists. Tension is deliberately released before the Twist beat compounds it.

QUESTION 8: DOES AT LEAST ONE CHOICE PRESENT A GENUINE MORAL GRAY AREA?
Pass condition: The moral-weight branching choice has no objectively correct answer. Each option reflects a legitimate human value. List the three values in tension.

QUESTION 9: WHAT EMOTIONAL STATE DOES THE USER CARRY OUT?
Pass condition: The user exits carrying ANTICIPATION (unresolved decision from session-end hook). Not RESOLUTION (fully closed) or CONFUSION (unclear).

QUESTION 10: WOULD A FRIEND IMMEDIATELY ASK "WHAT DID YOU CHOOSE?"
Pass condition: At least one choice is talkable.

---

SECTION B: VOICE AUDIT (6 questions — NEW)

These questions enforce the Voice Profile from the Voice Lock Phase. They are the pipeline-time execution of voice protection.

QUESTION 11: HARD BAN SCAN
Pass condition: ZERO banned tokens, phrases, molds, motifs, or names from Master Rule 1 (universal AND IP-specific) appear in ANY generated prose. Every word.
If REVISE: list every banned element found, its location, and the specific line.

QUESTION 12: TRAILING SIMILE SCAN
Pass condition: ZERO "like [metaphor]" constructions in action lines. Dialogue excluded only if the character's Voice Profile supports simile use.

QUESTION 13: SENTENCE MOLD SCAN
Pass condition: ZERO instances of "It's not X, it's Y." / "No X. No Y. Just Z." / balanced rule-of-three tricolons where all elements match length and structure / contrast-framing scaffolding ("She had always thought X. But now Y.").

QUESTION 14: VOICE AUTHENTICITY TEST
Pass condition: Read the cold open and three random narrative outcomes aloud. Apply the attribution test: could a reader familiar with this author identify the voice? It must sound like THIS author. Identify at least 2 of the author's 8-12 signature techniques in the cold open, and at least 1 per random outcome.

QUESTION 15: FULL 14-POINT AUDIT
Pass condition: Run ALL 14 audit points from Voice Lock Section 3 against all generated prose. Report PASS or FLAG per point in the `fourteen_point_audit_results` array. Q15 verdict is PASS only if all 14 points pass.

QUESTION 16: DICTION FINGERPRINT CHECK
Pass condition: Protagonist's internal voice matches the author's diction patterns from Voice Lock. NPC dialogue matches character fingerprints from Voice Lock.

---

SECTION C: STORYGUARD AND STATE COMPLIANCE (7 questions — NEW)

QUESTION 17: CANON INTEGRITY
Pass condition: No choices, outcomes, narrative bridges, or posture shift adjustments introduce elements outside this IP's canon as defined in StoryGuard Layer 1.

QUESTION 18: CHARACTER TRUTH
Pass condition: No NPC behavior in any generated prose violates the Character Rules from StoryGuard Layer 3.

QUESTION 19: WORLD REACTIVITY
Pass condition: The World Reactivity Rules from Phase 2 Task 7 are ACTIVE and VISIBLE in the generated content. The world must FEEL responsive to the player's behavioral patterns.

QUESTION 20: PERSISTENT STATE CONSISTENCY
Pass condition: All state changes specified in Phase 5 appear accurately in Phase 6. No state change is specified in one phase and missing from the other.

QUESTION 21: WORLD NOTICED SIGNALS
Pass condition: Every significant persistent state change has an in-world acknowledgment written in Phase 5. Every signal is: (a) in the author's voice, (b) grounded in the scene's physical reality, (c) never meta or gamey, (d) subtle enough to reward attention without breaking immersion.

QUESTION 22: SOCIAL ECHO DEFINING LINES
Pass condition: Every branching choice has 3 defining lines (one per path) written in Phase 5 Task 8. Each line is: (a) in the author's voice, (b) under 20 words, (c) provocative without spoiling, (d) would create curiosity in someone who has not played.

QUESTION 23: ALIGNMENT BALANCE
Pass condition: The three options per branching choice genuinely span chaotic/lawful/neutral. No choice accidentally offers three options in the same register. No alignment is systematically advantaged or punished.

---

BOILER AUDIT (ONLY WHEN BOILER ARTIFACTS ARE PROVIDED)

Read the normalized event roles and canonical `playable_moments.scene_card` values, not only the manifest.

Return PASS or REVISE with evidence for each:

1. EVENT COVERAGE — every assigned event has exactly one transparent role and valid moment/reintroduction route.
2. CANON REINTRODUCTION — required canon and every must-breathe earned effect survive direct play or an explicit reintroduction.
3. MOMENT ALIGNMENT — moments match Entry Point, Draft, Architecture, and ordered source coverage.
4. CHOICE BINDINGS — canonical choice IDs, complete A/B/C paths, defining lines, and freeform mapping remain unchanged and bind to valid moments.
5. CONSEQUENCE AND CLOSE BINDINGS — immediate/echo hooks resolve and the existing close belongs to the final moment.
6. PLAYABLE ROOM QUALITY — every PLAY/PULSE/CHOICE room has pressure, credible affordances, local source texture, canon boundaries, variance, and an exit; it permits meaningful exploration and action rather than summarizing.
7. PACING AND BUDGET AGREEMENT — Story Session Map minutes/range, Draft exact count, Architecture alignment, and final normalized count agree. Must-compress material was not silently expanded; protected material has adequate space.
8. PLAYABLE PROMISE ARC — first proof demonstrates the promise, midpoint intensifies it, and final cash-out delivers it in the closing moment. Short-session coincident anchors are valid when supported.
9. SEMANTIC EXIT CONDITIONS — each exit describes an outcome-based dramatic state change, not an exhaustive action checklist. Examples are possible routes only.
10. EXPERIENCE BALANCE — every room deepens immersion, consequence, or momentum without materially damaging the others. Technically complete but experientially inert rooms must be revised or removed.

Boiler `production_status` is PASS only when all ten checks pass. Otherwise return REVISE with concrete revision instructions. When no Boiler artifacts are provided, omit `boiler_audit`.

---

OUTPUT FORMAT: Return all 23 verdicts plus the 14-point audit results and a final_verdict object containing:
- total_passing (count out of 23)
- production_status: GREEN (23/23) / AMBER (20-22/23) / RED (below 20)
- revision_instructions (one entry per REVISE — question_number, phase, single most important revision)

PRODUCTION GATE: A session that does not pass 23/23 is not ready. Partial passes are not acceptable for launch. Return, revise, re-verify. The player deserves a world that remembers, a voice that is real, and a story that holds together. That is the minimum.
