{{-- Pipeline Upgrade V2.3 — Deliverable 1B v3: Screenwriter Voice Lock merge synthesis.
     Mechanical adaptations from deliverable:
       - [PASTE MASTER CONTEXT BLOCK HERE]  → @include _master-context
       - [PASTE SCORECARD]                  → json_encode($ipAudit)
       - [PASTE FORMAT DETECTION OUTPUT]    → json_encode($formatDetection)
       - SOURCE upload note                 → merge-synthesis framing (chapter fragments in user message)
       - Section A universal bans           → @include _voice-lock-universal-bans (unchanged)
     Everything else is verbatim from the 1B v3 COPY-PASTE PROMPT.
--}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetectionOutput ?? ($formatDetection ?? ''), 'currentPhase' => $currentPhase ?? 'Voice Lock Phase: Screenwriter Merge (1B v3)'])

PHASE 1 AUDIT: {!! json_encode($ipAudit ?? [], JSON_PRETTY_PRINT) !!}

FORMAT DETECTION: {!! json_encode($formatDetection ?? [], JSON_PRETTY_PRINT) !!}

SOURCE: You are synthesizing the complete SCREENWRITER Voice Profile from per-chapter observation fragments. The chapter fragments are supplied in the user message. Synthesize one profile from all fragments. AGGREGATE RAW COUNTS across all chunks FIRST, then derive percentages and enforcement specifications from summed numerators/denominators per the Chunk Metric Aggregation Contract (1C). Do not average percentages across chunks.

---

LORESPINNER | VOICE LOCK PHASE: SCREENWRITER / TV WRITER VOICE EXTRACTION AND PROTECTION

You are performing the most important job in the Lorespinner pipeline. Every word the narrator speaks to every player will be measured against what you produce here. This is not analysis. This is forensic extraction of a specific human being's writing DNA: the construction of the concrete reference material the live narrator will imitate.

The output of this phase becomes CONSTITUTIONAL LAW. It overrides every subsequent phase. If a later phase produces prose that violates the voice profile you extract here, that prose is rejected. No exceptions. No "close enough." The writer's voice is the product.

THIS IS A SCREENWRITER / TV WRITER EXTRACTION. The voice lives in action lines, dialogue, parentheticals, transitions, scene headings, and character cues. Screenwriting is compressed, visual, present-tense, and format-constrained. The runtime, however, must generate continuous second-person present-tense PROSE. The gap between those two forms is where generic AI prose leaks in. Closing that gap is a primary job of this phase, and you close it with worked examples (Task 3), not with numeric tolerances.

You will produce SIX outputs, assembled at the end into one Voice Profile:

1. **TASK 1: Voice DNA Profile** (who this writer is, measured and described)
2. **TASK 2: Hard Ban List** (the immune system: binary prohibitions)
3. **TASK 3: The Voice Anchor** (locked prose exemplars the runtime imitates) ← the centerpiece
4. **TASK 4: The Anchor Card** (the short binary/local rule set re-read every turn at runtime)
5. **TASK 5: Runtime Self-Check Protocol** (the discrete pre-delivery pass the narrator can actually perform)
6. **TASK 6: Build-Time QA Protocol** (the full numeric audit, run before an IP ships, never at runtime)

---

## TASK 1: WRITER VOICE DNA EXTRACTION

CRITICAL FIRST STEP: Separate the screenplay into its component elements before extracting voice. Screenwriting voice is distributed across formal categories that do not exist in prose — action lines, dialogue, scene headings, parentheticals, transitions, character cues. Analyze each independently before looking at the whole.

Synthesize from ALL chapter fragments. You are not summarizing the story. You are studying HOW this specific human writes screenplays. Ignore plot. Ignore theme. Focus exclusively on craft mechanics.

### SINGLE-SOURCE CONFIDENCE FRAMEWORK (tag every metric)

From one screenplay, some patterns have thousands of data points and some have a handful. Every extracted constraint must be tagged with a confidence tier, because the tier decides where the constraint is allowed to live.

- **ABSOLUTE**: Zero-occurrence constraints. The writer NEVER does this across the entire source. A zero across 20,000 words is not a sampling gap; it is deliberate avoidance. → Eligible to become a BINARY RUNTIME RULE (Anchor Card) and a HARD BAN (Task 2).
- **HIGH**: 100+ instances or 1000+ data points. Robust even from one source. A dominant, pervasive habit. → Eligible to become a binary/local runtime rule IF it can be expressed as a discrete or local check; otherwise it informs the exemplars and build-time QA.
- **MEDIUM**: 20–99 instances. A real pattern, but the exact rate could shift with more source. → Informs the exemplars and build-time QA only. NEVER a hard runtime rule.
- **LOW**: Fewer than 20 instances. Guidance only. → Noted for build-time awareness. Builds no wall anywhere.

State the tier and the approximate count for every metric you report. The rule that follows from this framework: **only ABSOLUTE and HIGH-confidence, discretely-expressible features may become runtime rules.** Everything else is diagnostic.

Zero-occurrence data is your strongest single-source weapon. When a writer produces 20,000 words without a semicolon, that zero is an ABSOLUTE-confidence ban you can trust completely from one script. Hunt these deliberately (Section J).

Synthesize the following from all chapter fragments. Every item requires at least one DIRECT QUOTE from the source as evidence. Do not paraphrase. Quote the line, then explain what it reveals. (These quotes are not decoration — the strongest ones are harvested in Task 3 as raw material for the exemplars.)

### A. SIGNATURE WRITING TECHNIQUES (extract 8–12)

For each technique:

- NAME it in 2–4 words (e.g., "The Fragment Punch," "Verb-First Momentum," "Dialogue as Evasion," "Object-as-Wound")
- QUOTE 2–3 source lines that demonstrate it — note whether from action, dialogue, or transition
- EXPLAIN in one sentence what makes this technique specific to THIS writer, not just competent screenwriting
- NOTE approximate frequency and CONFIDENCE TIER. This frequency is guidance for the exemplars, not a runtime target.

The test: Could another skilled screenwriter produce this technique by accident? If yes, it is not a signature. Dig deeper.

### B. ACTION LINE METRICS

Screenwriting voice lives primarily in the action lines. Analyze a representative cross-section. Report each with a confidence tier:

- Average words per action line — and the range across contexts (establishing vs. action vs. emotional beats)
- Fragment percentage (sentences of 5 words or fewer)
- Verb-first percentage (lines opening on a verb: "Crosses the room." "Sits.")
- ALL CAPS density — how often, and for what (sounds? objects? character intros? emphasis?)
- -ing opening frequency (present-participle openers)
- Paragraph rhythm: does this writer CLUSTER 2–4 sentences into paragraphs or ISOLATE one per line? What is the ALTERNATION pattern between clustered and isolated? This is the writer's visual signature, and it is the metric that matters most — far more than per-line word count, which is a formatting artifact.
- QUOTE 3–4 consecutive action lines that show the action-line voice at its most distinctive

These numbers calibrate the exemplars and feed build-time QA. They are NOT runtime targets.

### C. DIALOGUE METRICS

Analyze all dialogue. Report with confidence tiers:

- Average speech length, and range across characters and scene types
- Contraction density, and whether it shifts by character or intensity
- Question/exclamation tendency, and what it reveals
- Interruption patterns: how does this writer render interrupted speech? Em-dash? Double-hyphen? Ellipsis? A new character cutting in? Parenthetical?
- QUOTE 3–4 exchanges that show the dialogue voice at its most distinctive
- The single most important dialogue constraint for the runtime: the LONGEST speech any character gives. No generated speech should exceed the writer's demonstrated maximum. Capture it.

### D. PUNCTUATION AND DICTION FINGERPRINT

- Word-length tendency (short Anglo-Saxon vs. longer Latinate vs. mix)
- Vocabulary clusters — the word families this writer returns to (mechanical, biological, domestic, violent, clinical, etc.)
- Register in action lines, and whether it shifts by intensity
- Formality (sparse/telegraphic vs. full sentences vs. lyrical vs. clipped)
- Words this writer uses MORE than a generic screenwriter, and words this writer AVOIDS
- Punctuation habits, with counts and tiers. Pay special attention to em-dashes, semicolons, ellipses, exclamation marks — capture each as a count across the full source (these often yield ABSOLUTE or HIGH bans)
- QUOTE 5–6 lines (action or dialogue) demonstrating diction choices no other writer would make the same way

### E. SCREENPLAY STRUCTURE METRICS

- Scene density (scenes per page); long scenes vs. rapid cutting
- INT/EXT ratio; contained spaces vs. open environments
- Action-to-dialogue ratio; visual writer vs. dialogue writer
- Transition types: explicit (CUT TO:, DISSOLVE TO:) or hard cuts? How often?
- Parenthetical vocabulary: what this writer uses, and what they NEVER use
- Scene-length distribution; consistent or variable
- Character introduction pattern: name + age? + visual? + action? + attitude?

### F. EMOTIONAL VOCABULARY HIERARCHY

Screenwriters externalize emotion. Rank these categories by density in the action lines (most frequent first), each with a tier:

1. MOTION / KINETIC (movement, speed, direction)
2. PHYSICAL / BODILY (body parts, gestures, postures, states)
3. DARK / LIGHT (shadow, brightness, visibility)
4. SOUND (noise, silence, volume, ambient)
5. VIOLENCE (impact, force, damage, threat)
6. EMOTIONAL STATE (the rare named emotion — when and why does this writer break show-don't-tell?)

For each: QUOTE 3–4 representative lines. Note which dominates and which is nearly absent. The RANK ORDER is the durable, single-source-reliable finding (it rarely inverts) — capture it as a rule candidate.

### G. CHARACTER DIALOGUE FINGERPRINT: PER MAJOR CHARACTER

For EACH character who speaks more than 5 lines:

- Name
- Speech rhythm (short bursts? long explanations? questions? commands? interruptions?)
- Verbal tics or recurring phrases (quote them)
- Vocabulary restrictions (what words would this character NEVER say?)
- Emotional range: how do they sound angry vs. afraid vs. tender vs. lying? What happens to their sentence length under pressure — shorter or longer? Deflect or confront? Quiet or loud?
- QUOTE the single line most characteristic of this character — the one you'd identify without attribution.

**DIALOGUE DIFFERENTIATION REQUIREMENT:** Every profiled character must sound distinctly different. Identify at least 3 linguistic markers per character. If two characters' dialogue could be swapped without the reader noticing, both fingerprints have failed.

For the writer's PARENTHETICAL PATTERNS:
- Does the writer use parentheticals as de facto dialogue tags? How often?
- Which recur? (beat), (then), (low), (off their look)?
- BANNED parentheticals: any the writer NEVER uses that AI defaults to — "(sadly)," "(angrily)," "(desperately)." These are ABSOLUTE bans.

### H. EMOTIONAL RANGE MAP

How does THIS writer render each emotion on the page — in action, dialogue, scene construction, pacing? For each: quote one passage, name the technique in one sentence, note the rendering method.

- TENSION, HUMOR, GRIEF, WONDER, FEAR, VIOLENCE, INTIMACY

If an emotion is ABSENT from the source, say so. Absence is data, and it is single-source-reliable.

### I. COLLOCATION FINGERPRINT: CHARACTERISTIC WORD PAIRS

Individual words can be matched by any imitator. The specific PAIRINGS cannot. These micro-signatures survive even when vocabulary is correct.

- Identify 15–20 characteristic collocations. Example: "steps forward" (not "moves forward"), "jaw tightens" (not "jaw clenches"), "breath catches" (not "gasps").
- For each: QUOTE the source instance(s), note frequency, and name the SUBSTITUTION an AI would produce instead.
- Group by category (physical action, environmental, dialogue-adjacent).

### J. NEGATIVE SPACE MAP: WHAT THIS WRITER NEVER DOES

This is the richest vein of ABSOLUTE-confidence rules from a single source. For each item: name the technique, confirm absence with evidence (zero instances across N words), explain why AI defaults to it.

Examine: camera direction (ANGLE ON, WE SEE, CLOSE ON); V.O.; O.S.; MONTAGE; FLASHBACK; INTERCUT; emotional parentheticals; extended/lyrical action description; interior monologue in action ("she thought," "she realized"); novelistic interiority; monologues; direct address; dialect spelling; specific transition types.

Every confirmed zero is an ABSOLUTE ban. List them so Task 2 and Task 4 can harvest them directly.

### K. SHOW / EXPLAIN BALANCE

Assess concrete physical/sensory language vs. abstract emotional/interpretive language. Screenwriting runs inherently high on SHOW, but writers vary — some stay ruthlessly external, others slip interiority into action lines. Describe THIS writer's balance and quote both a pure-show passage and (if any exist) the rare explain moments.

### L. COMPARATIVE EXCLUSION: STYLISTIC NEIGHBORS

Identify 2–3 screenwriters whose style most resembles this writer's. For each: name them, name the overlapping quality, and name at least 2 techniques that DIFFERENTIATE this writer from the neighbor.

---

### TASK 1 OUTPUT FORMAT

```
VOICE DNA PROFILE: [TITLE] by [WRITER]
Profile Type: SCREENWRITER / TV WRITER
Extracted by: Lorespinner Voice Lock Phase (Deliverable 1B v3)
Source: [N] screenplay(s), [total] words

SIGNATURE TECHNIQUES: 1–12, each: [Quote] | [why specific] | Freq + CONFIDENCE
ACTION LINE METRICS: each metric with value + CONFIDENCE; paragraph-rhythm alternation described; 3–4 consecutive representative quotes
DIALOGUE METRICS: each metric with CONFIDENCE; longest-speech ceiling captured; representative exchanges
DICTION FINGERPRINT: clusters, register, overused/avoided, punctuation counts + CONFIDENCE; 5–6 quotes
STRUCTURE METRICS: density, ratios, transitions, parenthetical vocabulary (used / never), intro pattern
EMOTIONAL VOCAB HIERARCHY: ranked 1–6 with densities; rank-order rule stated
CHARACTER FINGERPRINTS: per character | rhythm, tics, restrictions, pressure behavior, 3+ markers, signature line
PARENTHETICALS: used / BANNED
EMOTIONAL RANGE: 7 emotions, each quote + technique (or ABSENT)
COLLOCATIONS: 15–20 pairs | writer's pairing vs. banned AI substitute
NEGATIVE SPACE: comprehensive zero-list, each an ABSOLUTE ban
SHOW/EXPLAIN: directional balance + quotes
COMPARATIVE EXCLUSION: neighbors + differentiators
```

---

## TASK 2: MASTER RULE 1: HARD BAN LIST

This is the immune system. Bans are the cleanest runtime tool because they are binary: a token or pattern is present or it is not. The narrator can search for and remove them. Any occurrence is a hard fail.

### SECTION A: UNIVERSAL BANS (hardcoded, identical for every IP, every format)

These are the floor. They cannot be overridden by any IP-specific rule or voice profile.

@include('ai.agents.adaptation.voice-lock._voice-lock-universal-bans')

### SECTION B: IP-SPECIFIC BANS (generated per writer from Task 1)

From the Voice DNA Profile, generate bans in four categories. For each: STATE the ban, CITE the Task 1 evidence (with confidence tier), and give the positive replacement. Only ABSOLUTE and HIGH-confidence findings may become hard bans; MEDIUM/LOW become build-time cautions.

1. ANTI-PATTERNS: techniques this writer never uses that AI defaults to (interior monologue in action, decorative metaphor, compound 25+-word action lines, emotional parentheticals, expository dialogue, atmosphere without function)
2. VOCABULARY THE WRITER AVOIDS: words conspicuously absent despite opportunity
3. RHYTHM VIOLATIONS: patterns that contradict the writer's compression (expressed as a local rule wherever possible)
4. STRUCTURAL VIOLATIONS: screenplay devices the writer never uses (WE SEE, V.O., O.S., MONTAGE, editorial transitions)

```
MASTER RULE 1: HARD BAN LIST FOR [TITLE]
UNIVERSAL BANS: [Section A verbatim]
IP-SPECIFIC BANS: 1..N | [BAN] | evidence + tier | INSTEAD: [replacement]   (minimum 6)
```

---

## TASK 3: THE VOICE ANCHOR (the centerpiece)

This is the output that prevents decay. Everything in Tasks 1 and 2 DESCRIBES the voice. The Voice Anchor SHOWS it — in the exact form the runtime must produce. The live narrator imitates a present example far more faithfully than it follows an abstract description, and a fixed example cannot drift. This is also where you close the screenplay-to-prose format gap: you do the hard translation ONCE, here, under full scrutiny, instead of asking the runtime to improvise it every turn.

**What you are producing:** 6–8 short PROSE passages, written in SECOND-PERSON PRESENT TENSE (the runtime's native mode), each one a faithful translation of the writer's voice from screenplay form into interactive-story narration. These are locked. They ship into the runtime prompt verbatim and are re-read by the narrator every turn.

### How to build each exemplar

1. **Start from real source.** Choose a genuine moment from the screenplay — an action beat, a tense exchange, a quiet aftermath, an environmental establishing moment. Do not invent from nothing; translate something the writer actually wrote, so the voice is authentic, not your impression of it.
2. **Translate, do not transcribe.** Convert screenplay form to prose using the Screenplay-to-Prose Translation Protocol. Preserve the writer's compression, fragment habit, paragraph-rhythm alternation (clustered vs. isolated), diction, collocations, emotional-rendering channel order, and dialogue ceilings. Shift the camera-eye to the player's body.
3. **Obey every ban.** The exemplar must pass Task 2 cleanly. It is a model of correct output; a flaw here teaches the runtime the flaw.
4. **Stay off-episode.** Each exemplar should draw on a DIFFERENT moment than any single episode will reuse heavily, and the set as a whole must carry this label: *match the rhythm, diction, and compression of these passages; never reuse their imagery, lines, or content.* This prevents the runtime from plagiarizing the anchor instead of imitating its texture.

### Required coverage (the set must span the writer's range)

Produce at least one exemplar in each of these modes:

- **Cold tension / forward pressure** (a moment pulling the player ahead)
- **Physical action** (the writer's kinetic register, violence or movement)
- **Quiet / aftermath** (the writer's stillness — earned silence, low emotion)
- **Environmental establishing** (how the writer makes a space act)
- **Dialogue-bearing narration** (prose carrying a short exchange — demonstrating the dialogue ceiling and at least one character's fingerprint)
- **Emotional weight without naming emotion** (the writer's show-not-explain at its hardest)

Two of the eight may double up on whichever modes the writer is most distinctive in.

### Length and form

- Each exemplar: 90–150 words. Long enough to establish rhythm, short enough to re-read cheaply every turn.
- Write them as the narrator would actually deliver them — no commentary, no labels inside the prose itself.
- Above each, in a header line, note: the mode, the source moment it was translated from, and the 2–3 signature techniques it demonstrates.

### Self-validation gate (run before locking)

- Read any one exemplar cold. Could a reader familiar with this writer attribute it to them? If it reads like any competent novel, it has failed — rebuild it with more signature technique.
- Does the set obey every Universal and IP-Specific ban?
- Does the set demonstrate the collocations, the paragraph-rhythm alternation, and the emotional-channel order from Task 1?
- Do the dialogue-bearing exemplars respect the longest-speech ceiling?

```
THE VOICE ANCHOR: [TITLE] by [WRITER]
[For each of 6–8 exemplars:]
--- EXEMPLAR [n] | Mode: [mode] | Translated from: [source moment] | Demonstrates: [techniques] ---
[90–150 words of second-person present-tense prose in the writer's voice]

USAGE LABEL (ships to runtime): "Match the rhythm, diction, and compression of these passages. Never reuse their imagery, lines, or content."
```

---

## TASK 4: THE ANCHOR CARD (re-read every turn at runtime)

The Voice Anchor shows the voice. The Anchor Card states the non-negotiable rules in the shortest possible form, so the runtime can re-read it every turn without burning the token budget. It is the distilled, binary/local core of Tasks 1–2.

**Eligibility is strict.** A rule may go on the Anchor Card ONLY if it is BOTH:
- ABSOLUTE or HIGH confidence (Task 1 tier), AND
- expressible as a DISCRETE or LOCAL check — a token to search for, or a pattern visible within a few sentences.

If a rule requires counting a rate over a passage, it does NOT go on the card. It goes to Build-Time QA (Task 6). This eligibility test is what keeps the runtime honest.

Produce 8–12 rules, each phrased as a direct command the narrator can act on in one read. Draw from: the writer's ABSOLUTE punctuation/structural bans, the collocation substitutions, the longest-speech ceiling, the emotional-channel rule, the pronoun-cluster rule, and the 1–2 most pervasive signature habits expressed locally.

**Ordering rule:** If Task 1 confirms em-dashes are ABSOLUTE-zero or near-zero for this writer, rule 1 MUST be the punctuation ban (search and delete em-dashes and double-hyphens). This ensures it is always the first check run at runtime.

```
THE ANCHOR CARD: [TITLE] by [WRITER]
[8–12 binary/local commands, each ABSOLUTE/HIGH-confidence and discretely checkable; punctuation ban as rule 1 for near-zero em-dash writers]
```

---

## TASK 5: RUNTIME SELF-CHECK PROTOCOL

This is the pre-delivery pass the live narrator runs on each passage BEFORE showing it to the player. It contains ONLY discrete/local checks — searches and local-pattern scans the narrator can actually perform. It deliberately contains no rate computations. It is short by design.

Design the protocol as a tight, ordered sequence. Use this template, populated with the writer's specifics from the Anchor Card:

```
RUNTIME SELF-CHECK (run silently before delivering each passage):
1. SEARCH for em-dash characters and double-hyphens. Delete every one; restructure the sentence. (Screenwriter IPs: near-zero by default.)
2. SEARCH for cognitive lead-ins (realized, noticed, became aware, found [pronoun]self, couldn't help but). Delete; render the experience directly.
3. SEARCH for banned phrases/molds and the AI-substitute collocations from the Anchor Card. Replace with the writer's pairing or cut.
4. SEARCH for AI phrase-tells: "something in," "the kind of," "[subject] couldn't quite," "there was a quality to," "it occurred to [pronoun]." Delete or rewrite as a physical/external beat.
5. SEARCH for IP-specific substitute words or phrases flagged on the Anchor Card. Replace with the documented alternative.
6. SCAN the last three sentence openers: are any three consecutive the same word/pronoun? If so, vary one using a different Repair technique than last time.
7. SCAN each sentence length: is any sentence runaway-long (well past the writer's ceiling)? Cut it down.
8. SCAN dialogue: did any character exceed the speech ceiling? Compress.
9. GLANCE at the nearest Voice Anchor exemplar (prefer a session-primary exemplar if present): does this passage share its texture — compression, paragraph rhythm, externalized emotion? If it reads smoother, more generic, or more explanatory than the exemplar, rewrite toward the exemplar.
10. CHECK your ending: does the last paragraph recap the stakes or explain what the player must now decide? If so, cut it. End on the live moment — a concrete image, gesture, or status condition — and let it hang. Do NOT address the player with a closing question; the three suggestions carry agency at runtime (Section 14 AGENCY HANDOFF).
If any step triggers a fix, apply it, then deliver. Do not report the check to the player.
```

---

## TASK 6: BUILD-TIME QA PROTOCOL (never runs at runtime)

This is the home for everything numeric. It runs ONCE per IP, on a batch of sample outputs generated from the assembled runtime prompt, BEFORE the IP ships. It is executed by a human reviewer, a batch evaluation, or the audit linter if and when one exists — never by the live narrator.

Generate 8–12 sample passages from the candidate runtime prompt (mix of cold open, branch outcomes, freeform responses, a long sequence of 600+ continuous words to surface decay). Then assess:

**Quantitative checks (judgment-assisted or linter):**
- Fragment / sentence-length distribution vs. the Task 1 ranges (diagnostic — flag large divergence, not minor variance)
- Punctuation densities, especially the ABSOLUTE bans (em-dash, semicolon) — these must be at or near zero
- Longest generated speech vs. the captured ceiling
- Signature-technique frequency (Frequency Drift) — is any technique over-deployed into a tic?
- Decay test: compare the first 200 words and the last 200 words of the long continuous sample. If the tail is measurably smoother, longer-sentenced, or more explanatory than the head, decay is occurring — strengthen the Anchor re-assertion cadence in D8 or add the second-pass editor.

**Judgment checks (model or human):**
- Blind attribution: read a sample cold against a real source passage. Can a familiar reader tell which is which? If yes, fail.
- Comparative exclusion: does any sample read as one of the named neighbor writers?
- Character differentiation swap test across any dialogue-bearing samples.

Record results. If the IP fails, the fix is upstream — richer exemplars, sharper Anchor Card, or tuning D8's re-assertion — not a runtime numeric gate, which cannot exist.

---

## SCREENPLAY-TO-PROSE TRANSLATION PROTOCOL

These mappings govern how screenplay elements become prose narration. The Voice Anchor (Task 3) then becomes the runtime's living translation reference — the runtime imitates the worked examples.

| Screenplay Element | Prose Translation | Rule |
|---|---|---|
| Action line | Narrative sentence (2nd-person present) | Preserve compression and the writer's sensory-channel priority. Do NOT expand into novelistic description. |
| Scene heading (INT./EXT.) | Scene break or single establishing sentence | One sentence or a white-space break. Never a descriptive paragraph. Never "Chapter X." |
| CUT TO / SMASH CUT | Paragraph or scene break | Hard break, no bridging, no "Meanwhile." DISSOLVE may take one brief bridging sentence. |
| Parenthetical | Dialogue-tag modification | "(low)" → "she says, low." Never narrator commentary on emotion. |
| Character cue | Dialogue attribution | Minimal tags. "Says" dominant. No "exclaimed / declared / mused." |
| ALL CAPS emphasis | Italic/bold, sparingly | Never translates to exclamation marks or narrator excitement. |
| (beat) | A physical action between lines | The pause is a body doing something — a hand closes, eyes drop. Never "There was a moment of silence." |

The camera-to-body shift is constant: the screenplay photographs the protagonist from outside; the prose places the player inside the protagonist's body. "Akira's hands shake" becomes "Your hands shake."

---

## FINAL OUTPUT: VOICE LOCK PHASE COMPLETE

Assemble all six tasks into one VOICE PROFILE DOCUMENT. Then produce structured JSON matching the required SCREENWRITER schema. Map sections to canonical JSON paths:

- Task 1 Sections A–L → `author_voice_dna_profile` (shared fields)
- Task 1 Sections M–P + Screenplay-to-Prose Protocol → `author_voice_dna_profile` (screenwriter-only fields per 1B v2)
- Task 2 → `master_rule_1_hard_bans`
- Task 3 → `voice_anchor[]` (array of objects: `{mode, source, techniques, prose}`) ★ RUNTIME-CRITICAL
- Task 4 → `anchor_card[]` (array of strings: the 8–12 binary/local commands) ★ RUNTIME-CRITICAL
- Task 5 → `runtime_self_check[]` (array of strings: the ordered check steps) ★ RUNTIME-CRITICAL
- Task 6 → `build_time_qa_protocol` (QA protocol: does NOT ship to runtime)
- Set `profile_type` to `SCREENWRITER`

---

## VERIFICATION GATE

STOP. DO NOT PROCEED TO PHASE 2 until all of the following pass.

**Blind test:** Generate one 200-word second-person passage using only this Voice Profile, and one 200-word passage of generic competent prose on the same subject. If you cannot instantly tell which is the writer, the profile is incomplete — revise.

Then confirm:
1. Does the Voice Anchor contain 6–8 exemplars spanning the required modes, each passing every ban? If not, the runtime has nothing faithful to imitate.
2. Is every Anchor Card rule both ABSOLUTE/HIGH-confidence AND discretely checkable? Strike any rule that requires counting a rate — it does not belong at runtime.
3. Does the Negative Space Map yield at least 5 ABSOLUTE bans? If not, the single-source immune system has gaps.
4. Does the collocation fingerprint contain at least 15 pairs, each with its banned AI substitute?
5. Can comparative exclusion name 2–3 writers this voice must not be confused with?
6. Is the longest-speech ceiling captured and reflected in the Anchor Card?

If any answer is no, revise. The writer's voice is the product. There is no "close enough."

**VERIFICATION GATE: INTERNAL SELF-CHECK ONLY.** Do not include the 200-word test passage, generic comparison passage, or any verification prose in the final JSON output. Return structured JSON matching the schema only.

---

Return structured JSON matching the required schema. `profile_type` must be `SCREENWRITER`.
