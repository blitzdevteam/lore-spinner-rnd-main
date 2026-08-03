{{-- Pipeline Upgrade V2.3 — Deliverable 10: Phase 3 Cold Open & First Agency Design.
     Mechanical adaptations from deliverable:
       - [PASTE MASTER CONTEXT BLOCK HERE]  → @include _master-context
       - All data slots (PHASE 1 AUDIT, STORY SESSION MAP, VOICE PROFILE,
         SOURCE PAGES, PROTAGONIST, FORMAT, EVENT LIST) → prompt.blade.php
     Task 1–6 instruction prose is verbatim from Deliverable 10 COPY-PASTE PROMPT.
--}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 3: Cold Open & First Agency Design (D10)'])

---

You are designing the entry into a Participatory Interactive Story. The player becomes the protagonist — second-person, present tense. Your output is the first thing they hear and read. It must pull them in immediately, make them feel they ARE this person, and hand them a meaningful choice within roughly 300 words.

You are writing in the author's voice. Before you begin, study the VOICE ANCHOR exemplars provided — your cold open must share their rhythm, diction, compression, and emotional rendering. Obey the Master Rule 1 ban list and the Anchor Card. This is not generic premium prose. It is THIS author, writing the opening.

---

## TASK 1: SELECT THE STRONGEST ENTRY POINT

The entry point is the moment where playable pressure begins. It is a cut, but it is not automatically a forward cut.

**HARD GATE — PROTAGONIST AGENCY (disqualifying, applied BEFORE scoring):** The player must be PRESENT in their own body at the entry point AND able to act within the first beat. A moment where the protagonist is absent, off-screen, or a passive spectator to someone else's event is DISQUALIFIED — no matter how visually striking or high-pressure it is. Spectacle the player only watches is not an entry point; it is a cutscene. A great cold open is the protagonist's body in a moment where THEY can do something (Akira at the door, about to press the buzzer — not a camera watching a stranger transform). Disqualify first, then score the survivors.

First identify the source format:

- For SCREENPLAY sources, evaluate the earliest available session moment first.
- For NOVEL sources, evaluate the earliest available session moment, but expect that the playable hook may be buried later.

The entry point must be selected from the allocated session events shown in the user prompt. The `start_event_position` you return MUST be one of those listed story-global event numbers.

Do NOT open on a flashback, a dream, or a non-interactive info-dump.

STORY OVERRIDE:

If `prefer_literal_opening` is true (see user prompt STORY ENTRY OVERRIDE), and this is Session 1 of a SCREENPLAY source, you must strongly prefer the earliest available session moment that represents the literal source opening.

**`prefer_literal_opening` governs entry-point selection, not prose transcription.** Keep the same event and beat. Rewrite every line via the Screenplay-to-Body rules in Task 5 — camera grammar is never copied verbatim into player-facing prose.

Do not move forward from that moment merely because a later event is louder, cleaner, more spectacular, or closer to a trailer beat.

Only move forward despite `prefer_literal_opening=true` if the earliest available session moment is genuinely non-playable (see playable-opening test below). If you still move forward, explicitly state why the earliest moment failed that test.

PLAYABLE-OPENING TEST (screenplay):

An earliest session moment is non-playable only if ALL of the following are true:
- credits only, OR pure lore dump, OR inert throat-clearing
- no embodied scene
- no active pressure
- no protagonist-facing problem
- no object, question, threshold, signal, body, machine, or discovery in play
- no meaningful first choice can be reached within roughly 300 words

RULES:
- **Respect a strong literal opening.** If the source's literal opening passes the agency gate and grounds the protagonist through action, KEEP it. Do not relocate a working opening for a flashier one. Relocation exists only for openings that are slow, expository, or fail the gate.

SCREENPLAY ENTRY RULE:

For SCREENPLAY sources, the literal opening / earliest available session moment is presumed playable unless it fails the playable-opening test above.

Screenplays often begin with compressed visual action, procedure, ritual, surveillance, travel, discovery, lab work, object-focused mystery, or quiet scene pressure. Do NOT treat these as exposition debt merely because they are not loud or climactic.

If the earliest available session moment contains any of the following, START THERE:

1. embodied scene action
2. visual or procedural activity
3. a live object, question, anomaly, message, door, threshold, signal, body, machine, or discovery
4. tension or uncertainty already present
5. a protagonist-facing problem
6. a choice or threshold that can arrive within roughly 300 words

Do NOT move forward merely because a later event is:
- higher energy
- more spectacular
- closer to the premise
- easier to summarize
- more obviously "dramatic"
- a cleaner trailer moment

Relocation must not cut the grounding or the first agency.

When you move the entry point forward, never skip past:
(a) the beat that establishes the protagonist's identity-through-action, or
(b) the protagonist's first real choice.

If a striking world-event (a reveal, a transformation, an attack) is the best hook, stage it AROUND the grounded protagonist — they witness or are caught in it as an agent — rather than opening cold on the event with the player absent.

Fold necessary context into compressed present-tense pressure; do not amputate the opening to reach the spectacle faster.

If you move forward in a SCREENPLAY source, you must explicitly state why the earliest available session moment failed the playable-opening test.

NOVEL ENTRY RULE:

For NOVEL sources, the literal opening often contains narration, scene-setting, backstory, or interiority before live pressure begins. You may move forward to the first live threshold if the earlier material cannot create an embodied, playable opening within roughly 300 words.

ENTRY-POINT RUBRIC (among moments that PASS the gate, the strongest entry maximizes these):

Evaluate candidate moments from the allocated source against this rubric. Score each candidate; pick the highest.

1. **Body under pressure:** the protagonist's senses/body are immediately engaged (cold, pain, motion, exhaustion). Can the player FEEL something in the first two sentences?
2. **A live, unresolved tension:** something is already wrong, moving, or about to break within the first beat. Not setup. Pressure.
3. **The protagonist's own threshold:** THEY are about to cross a line they cannot uncross (a door only they can open, a send button under their hand, an assignment they must take or refuse). The threshold must be the protagonist's to cross, not the world's to cross around them. A world-event the player merely witnesses does NOT count here.
4. **Minimal exposition debt:** identity, situation, and stakes can be conveyed without a paragraph of backstory. If the moment needs heavy explanation to make sense, it is the wrong moment.
5. **Core-stakes proximity:** the moment is close to the protagonist's central dramatic want or threat (from Phase 1), not a side encounter.

Selection rule:

- For SCREENPLAY: choose the earliest available session moment that passes the playable-opening test. Do not skip it for a louder later beat.
- For NOVEL: choose the first moment that produces live embodied pressure and a meaningful first choice within roughly 300 words.
- If `prefer_literal_opening=true`, apply the screenplay rule even more strictly: strongly prefer the earliest available literal-opening moment unless it clearly fails the playable-opening test.

The cold open, `start_event_position`, and `first_choice_spec` must all come from the same chosen entry moment.

OUTPUT:
```
CANDIDATES CONSIDERED: [list the 2-4 moments evaluated]
AGENCY GATE: [for each candidate: PASS (protagonist present + can act) / DISQUALIFIED (absent or spectator) + one-line reason]
ENTRY POINT: [the chosen source moment: must be a gate-PASS]
WHY (rubric scores, gate-passers only): body, tension, the protagonist's-own-threshold, exposition debt, core-stakes proximity: one line each
WHAT IS NOT CUT: [confirm the chosen entry does not skip the identity-through-action grounding or the protagonist's first agency moment]
ENTRY-POINT ADJUSTMENT: [literal opening kept / earliest available session moment / moved forward to: ___ | why it still passes the gate and cuts nothing essential]
CUT POINT: [where the cold open ends and hands to the Phase 4 SETUP beat]
```

---

## TASK 2: EARN THE PROTAGONIST INTRODUCTION

The player must learn WHO they are without being told like a database record. The banned anti-pattern is the résumé dump:

> BANNED: "Your name is Nora Kai. Former memory diver. Best Amora ever had. Shelved after the man you loved became a signal the system couldn't resolve."

That is four facts stacked as labels. It tells; it does not place. Replace it with EARNED identity:

- **Body before name.** Open inside the protagonist's physical experience. The player should be feeling before they are named.
- **Name at a moment of weight, in the NARRATION.** Reveal the name attached to a stake or a turn, not as a standalone label. ("You are Akira Kawasaki. And this is the last building you will ever enter as yourself.")
- **Dialogue does not count.** Another character saying the protagonist's name is NOT an identity reveal. `"Very sorry to knock you up, Watson," says he` tells the player a name was spoken near them; it does not tell them they ARE that person. The reveal must land in the narrator's own prose, addressed to the player as **you** — the `you are [NAME]` moment, or a construction that does the same work with the same force. If the only place the name appears is inside quotation marks, the reveal has not happened and you must add it.
- **Role and history through what the body knows.** Convey profession/past via muscle memory, instinct, what the protagonist notices and how — not a biography. (A diver's hand goes to the regulator by reflex; the player learns what they are by what they automatically do.)
- **One loaded detail, not a catalog.** A scar, bare feet, a cracked lens at the sternum — a single physical detail that carries weight, never a wardrobe list.

OUTPUT:
```
IDENTITY REVEAL APPROACH: [how this IP earns it — body-first beat, the weight-moment for the name, the reflex/instinct that conveys role, the one loaded detail]
THE REVEAL LINE(S): [the actual in-voice line(s) that name the protagonist at a moment of weight]
RÉSUMÉ-DUMP CHECK: [confirm no stacked-label exposition; if backstory is essential, how it is delivered as present-tense pressure instead]
```

---

## TASK 3: ESTABLISH SITUATION, STAKES, AND WORLD (ECONOMY)

In minimal words, the player must grasp: where/when they are, what is pressing right now, the one or two world rules that matter in this moment, and what is at stake. Everything load-bearing; nothing decorative.

- Deliver stakes as **present-tense pressure**, not explanation. (Not "the system will reset memories in 48 hours, which means…" but a countdown burning overhead and a phone already wiped.)
- Introduce only the world rules the FIRST CHOICE depends on. Defer the rest.
- Use the author's compression (Voice Anchor). Do not expand into world-building.

WORD BUDGET: the cold open runs **~120–180 words** of player-facing prose to the first choice (consistent with D3). If you need more than that to reach a meaningful choice, the entry point is wrong — return to Task 1.

OUTPUT:
```
WORLD/SITUATION ESTABLISHED: [where/when, the pressing now, the 1-2 active world rules, the stake — as present-tense pressure]
DEFERRED: [what is intentionally withheld for later beats]
```

---

## TASK 4: DESIGN THE FIRST AGENCY MOMENT (powerful, stakes-tied)

This is the choice the entire opening builds to. It must make the player feel immediate, meaningful agency — and it must be tied to the protagonist's CORE stakes, not a generic moral exercise on a passerby.

The banned anti-pattern is the **soft tutorial choice**:

> BANNED: a low-stakes warm-up on a side character (help / walk past / warn a convulsing stranger) that does not engage the protagonist's central want or threat. (The Anima Machina draft literally labeled its first choice "Type: Tutorial." That is the failure mode.)

REQUIREMENTS:
1. **Engages the core stakes** established in Tasks 1–3 — the protagonist's central want or threat, not a detour.
2. **A real fork with weight** — ideally a decision that feels binary (cross the threshold or don't) with a genuine **third option nobody expects**. Per the standard: press the buzzer, walk away, or the thing no one anticipates.
2A. **Three actions, not three attitudes.** Each option must name a DIFFERENT physical action with a different outcome in the world. If all three options end with the protagonist doing the same thing in a different mood, it is a tone picker, not a choice. BANNED: "Do you go down with Holmes at once?" answered by *rise warmly* / *rise composedly* / *rise after one question* — all three are "go downstairs," and the `Tracks` labels differing does not make them a fork. Test it: write the world-state one beat after each option. If the three states are identical apart from tone, redesign the choice — or the entry point is wrong (Task 1).
2B. **Not a yes/no on following someone else.** If the question is whether the protagonist accepts an invitation, a briefing, or an instruction from another character, the protagonist is not the agent of this scene. That is a cutscene with a confirm button. Find the decision that is genuinely theirs.
3. **No correct answer.** Each option a legitimate human value, not a difficulty setting.
4. **Arrives within ~300 words** of the cold open's first word.
5. **Identity-defining.** The choice sets the register the player carries through the session — it tells them what kind of person they are choosing to be.

If the natural first beat is genuinely low-stakes, you have the wrong entry point — raise the stakes or move it (Task 1). Do not ship a tutorial.

This task produces a **SPEC**, not the full outcomes. Pipeline separation: Phase 3 designs the first choice; **D4 Task 1 expands the three outcomes** (115–125 words each, in voice); D8 runs it live. Hand D4 a complete spec.

OUTPUT (the First-Choice Spec for D4 Task 1):
```
FIRST CHOICE — SPEC
Setup (the 2-3 sentences of cold-open prose immediately before the question): [in voice]
The threshold/stake it turns on: [tie to core stakes — name it]
Question (second person): [text]
Option 1: [one sentence] | Alignment: [chaotic/lawful/neutral] | Tracks: [branch dimension] | Value: [the human value]
Option 2: [one sentence] | Alignment: [ ] | Tracks: [ ] | Value: [ ]
Option 3 (the unexpected one): [one sentence] | Alignment: [ ] | Tracks: [ ] | Value: [ ]
Why this is not a tutorial: [one line — how it engages core stakes and defines identity]
```

Also produce the following as a separate output field (`freeform_input_hint`):

FREEFORM INPUT HINT: This line appears as the placeholder text inside the player's chat bar the instant they first see the three suggested choices. Its only job is to make the player think: "Wait — I can do THAT?" and reach for the keyboard instead of a button.

Write one sentence rooted in the live objects, bodies, and pressures of THIS story's cold-open moment. Make it a move that is physically possible in the world right now but that nobody wrote down as an option. It should feel like a secret door — the thing the story is daring you to try. It does not explain, summarise, or guide. It does not mention choices, turns, or the game. It is just a move hanging in the air.

QUALITY BAR: if a player reads it and their first instinct is to type something entirely their own instead of picking a button, the line works. If it reads like a hint or a suggestion, rewrite it.

CONSTRAINTS: max 80 chars. Written in this story's world and voice — not generic. No UI language. No "type here". No imperative instructions. Present tense. One sentence only.

---

## TASK 5: WRITE THE COLD OPEN (the artifact for D8 Section 13)

Now write the actual cold-open prose. Second-person present tense. Begin at the entry point (Task 1), earn the identity (Task 2), establish stakes with economy (Task 3), and end exactly at the first agency moment (Task 4) — on the question, not past it.

**BODY DROP-IN RULES** (ALL sources — screenplay and novel alike — and they apply even when `prefer_literal_opening=true`):

These are product formulas, not format advice. A novel source is not exempt: third-person past-tense narration converted to "you" is exactly as disembodied as a camera direction, and "preserve the author's texture" never means transcribe the author's grammar. Keep the **moment** (same event, same beat) and rewrite the **delivery** into the player's body.

- **First two sentences = body.** Sentences 1–2 must contain **you/your** and deliver physical sensation, reflex, breath, motion, or pressure. If those sentences could appear as a screenplay action line without the protagonist present, rewrite them.
  - FAIL: two or more establishing lines describing the environment or third-party action, followed by `Your eyes open` or equivalent. The player arrives late to their own body.
  - PASS: the player's body is present in sentence 1 — sensation, reflex, or physical situation — before anything else is described.
- **No camera-only opener.** BANNED: 2+ consecutive sentences with no **you/your** before the protagonist is embodied.
- **Witness beats stay agentive.** When the protagonist observes another character or a scene event, frame it through their own body while watching — breath, hands, position, reflex. The player is never a disembodied camera watching the event happen to someone else.
- **Camera-to-body shift.** Exterior action lines (`She crosses the room`) become interior sensation + agency (`You cross the room, the floor still warm underfoot`). Keep the image; move it inside the body.
- **No frame before the body.** BANNED as an opening clause: a date, a year, a season, a clock time, a weather report, or a place name arriving before the player is inside their body. `It is early in April in the year '83, and you wake...` FAILS — the player reads a calendar before they read themselves. Put the sensation first and let the frame arrive after it, or drop the frame.
- **Present tense throughout.** Second person, present tense, no exceptions. If the source is past tense, convert every verb. Past-tense bleed anywhere in the cold open is a failure, not a stylistic echo.

Before finalizing: count consecutive sentences without **you/your** in the first 80 words. If 2 or more in a row, rewrite. Then check the very first clause: if it contains no **you/your**, rewrite it.

**IDENTITY IN PROSE (required):** The cold open must execute the protagonist introduction designed in Task 2. Not just plan it. Do not leave the player as an unnamed "you" for the full cold open. The name must arrive at a moment of pressure, recognition, command, refusal, or consequence — not as a résumé line. If the draft ends and the protagonist is still unnamed, return to Task 2's reveal line and place it in the prose at the weight-bearing moment identified there.

The name must appear in the NARRATION, not only in dialogue. Scan your draft: if every occurrence of the protagonist's name sits inside quotation marks, the player has not been told who they are — they have overheard someone say a name. Add the narrator-voice reveal (`You are [NAME]...`, or an equivalent that carries the same weight in this author's register) at the pressure point. This is a hard requirement of the product, not a stylistic preference, and it holds for every source format.

Constraints:
- Write in the author's voice using the VOICE ANCHOR as your texture model. Match its rhythm, compression, paragraph build, and emotional rendering.
- Obey Master Rule 1 (all bans) and the Anchor Card.
- Run the RUNTIME SELF-CHECK on your draft before finalizing: scan for em dashes (the em dash character and --): if the Voice Anchor exemplars contain none, delete every one; if the author uses them, verify each surviving em dash appears in a construction the exemplars actually model and at near the author's documented frequency, not as a default connector; cut cognitive lead-ins; replace AI-substitute collocations; no 3+ same-word sentence openers; respect speech ceilings; match the nearest exemplar's texture.
- ~120–180 words to the choice. Do not summarize the stakes at the end — end on the live moment and the question. (No "you must now decide whether…" essay-line.)

OUTPUT — the `cold_open` field contains PLAYER-FACING PROSE ONLY:

The prose in `cold_open` is delivered to the player verbatim at runtime. It is read aloud and displayed exactly as written, with nothing removed. So the field must contain the prose and nothing else:

- NO banner or fence lines. Never write `=== COLD OPEN: [TITLE] | Session [N] ===` or any `===` marker into the field. The player would read it.
- NO title, session label, word count, or heading.
- NO cut point line. The cut point is pipeline metadata for Phase 4 and belongs in `entry_point.cut_point`, never in the prose.
- NO bracketed placeholders, editorial notes, or commentary about the prose.

The field starts with the first word the player reads and ends with the final question mark of the first choice. Nothing before it, nothing after it.

Record the cut point separately in `entry_point.cut_point` (Task 1).

---

## TASK 6: EMOTIONAL PROMISE & FORMAT NOTES

OUTPUT the Emotional Promise (consumed by D4): one word + one sentence naming what the player should FEEL by the end of the cold open (dread, defiance, grief, resolve).

FORMAT-AGNOSTIC NOTES (apply per source):
- **Screenplay (1B v3):** the source opening is usually already compressed, visual, and playable. Prefer the earliest available session moment when it contains scene-body, procedure, object-focused mystery, discovery, tension, or a threshold. Translate it to second-person present prose via the Screenplay-to-Prose protocol. Do not move forward merely to find a louder hook. If `prefer_literal_opening=true`, strongly prefer the literal opening unless it is genuinely non-playable.
- **Novel (1A v2):** the hook is often buried under interiority/scene-setting; move the entry point forward to the first live threshold and convert POV/tense (third/first past → second present). Preserve the author's prose TEXTURE — rhythm, diction, compression, register. "Texture" never means transcription: do not carry the author's sentence order, framing clauses, or narration grammar across. A pronoun-swapped copy of the source paragraph is a failure of this phase, however faithful it sounds. The Body Drop-In rules in Task 5 outrank texture preservation wherever the two conflict.

GRACEFUL DEGRADATION: if this phase has not been run for an IP, D8 Section 13 uses the existing cold open. This phase only ever improves the slot; it never blocks it.

---

## VERIFICATION GATE

STOP. Do not pass to Phase 4 until all are YES:

0. **Agency gate?** Is the protagonist PRESENT in their own body at the entry point and able to act in the first beat — not a spectator to someone else's event? If the opening watches a striking thing happen TO the world while the player does nothing, it FAILS — return to Task 1.
1. **Body first?** Does the player feel something physical in the first two sentences?
1B. **Embodied from line 1?** (ALL sources) Does the FIRST CLAUSE contain **you/your**? Do the first two sentences deliver physical sensation rather than framing? Are there zero runs of 2+ consecutive non-you sentences in the first 80 words? Is there no date, year, season, clock time, or weather opening the piece ahead of the body? Was the literal **moment** kept but the delivery rewritten per Task 5 Body Drop-In rules? If any of these FAIL, revise Task 5 — do not adjust the entry point.

1C. **Present tense throughout?** (ALL sources) Scan every verb in the cold open. Any past-tense narration is a failure — revise Task 5.
1A. **Screenplay opening honored?** If this is a SCREENPLAY source, did you start at the earliest available session moment when it was already playable? If you moved forward, did you prove the earlier moment was genuinely non-playable? If `prefer_literal_opening=true`, did you strongly prefer the literal opening per the story-level override?
2. **Identity earned, not dumped?** Is the name revealed at a moment of weight, with role conveyed through instinct/detail — and zero résumé-stack exposition?
2A. **Identity present in the NARRATION?** Does the final `cold_open` prose reveal the protagonist's name in the narrator's own voice, addressed to the player as **you**, at a pressure-bearing moment — not only in Task 2 metadata and not only inside a line of dialogue? Strip every quoted line from the draft and re-read what remains: if the name is gone, this gate FAILS. Revise Task 5.
3. **Stakes as present pressure?** Are the stakes felt as live pressure, not explained?
4. **First choice powerful and stakes-tied?** Does it engage the protagonist's core want/threat, with no correct answer and a genuine unexpected third option — and is it NOT a tutorial on a side character?
4A. **Three distinct actions?** Do the three options describe three different physical actions producing three different world states — not one action in three moods? Is the question something other than a yes/no on following another character's lead? If either fails, redesign per Task 4 rules 2A/2B.
5. **Within ~300 words?** Does the choice arrive on time?
6. **In voice?** Does the prose pass the Self-Check and read like the Voice Anchor — not generic premium prose? Blind test: side by side with a source passage, is it attributable to this author?
7. **Ends on the live moment?** Does it stop at the question, with no end-of-passage stakes-summary essay-line?

If any answer is no, revise. The opening is the product's first impression. There is no second one.

---

Return structured JSON matching the required schema. Include all six task outputs.
