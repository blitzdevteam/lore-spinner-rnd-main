# LoreSpinner — cold-open repair specification

27 September 2026 | Cursor implementation handoff | Integrates Tom's supplied Cold Open Contract

## Implemented review repair (3 October 2026)

The runnable repair is documented in [Cold_Open_Repair_Process.md](Cold_Open_Repair_Process.md). It is generalized: one command, every story, session 1. Peter Pan is the pilot and the worked screen in Appendix A is an example of the desired outcome. That screen is not pasted into the system prompt, and its last line (the question to the player) is not part of the outcome.

Decisions that override this specification for the review repair:

| Topic | This repair |
|---|---|
| Scope | Rewrite `cold_open` only, from the stored diagnosis, voice anchor, and source window. Cards, initial state, and the pipeline agent stay as they are until a candidate is greenlit. |
| Closing question | The prose does not end on a question. The choice buttons carry the decision. |
| Worked example | Appendix A keeps the Peter Pan screen as the quality example. The prompt names no story and no character until that title's record is loaded. |
| Model | `claude-sonnet-4-6` at temperature 0.6. |
| Command | `php artisan stories:repair-cold-open {slug}` in the Laravel Cloud command box. The box runs bash, so the `php artisan` prefix is required. `--dry-run` prints the pull. No flag generates and does not save. `--apply` stores a candidate that passes the checks. |

The sections below are the original 27 September handoff. Where they disagree with the table above, the table is what the review command does.

## Decision and scope

Repair each launch title's **opening package**: entry-point diagnosis, cold-open prose, first choice, handoff state, active moment, and runtime delivery. Rewriting `cold_open` alone leaves the previously diagnosed progression defect intact.

Apply Tom's writing contract at build/repair time. Store the validated opening and deliver it on the first story output. Do not append the entire writing contract to every gameplay turn. Keep the existing model and pricing configuration; the contract's model-quality note is an unverified assertion, not authorization to switch models.

Scope this pass to session 1's first story output across the launch catalog. Later episode openings must preserve carryover state and should be audited separately; do not apply fresh-story defaults to every session or resume. Use the actual launch-catalog query to enumerate titles and reconcile it with the expected 18. Audit every title; repair every noncompliant opening package. Preserve a compliant opening rather than rewriting it solely for batch uniformity.

**Available here:** Tom's complete supplied contract and full current entry-point diagnoses for Peter Pan and Anima are embedded below. The other titles' assets and application repository are not present. This file specifies the catalog repair, but does not claim 18 repaired openings have been generated or published.

## 1. Integration decisions to implement explicitly

| Topic | Required integration |
|---|---|
| Orientation | Place/time/world in the first two sentences; protagonist name, role, and current action within approximately 60 words; explain the immediate event and actionable decision. |
| Voice and viewpoint | Second-person present narration as the supplied protagonist. Preserve each author's voice, including intrusive narration where appropriate. NPC dialogue may naturally use first person and recount past events; do not reject it with a blanket pronoun/tense regex. |
| First choice | Use the title's actual `first_choice_spec` and canonical bindings. Do not copy Peter Pan's worked example or its option meanings into other titles. |
| Closing question | Tom requires it. Add an **opening-only exception** to Sections 14/18 and any postprocessor that removes narrator questions. Normal-turn ending policy stays separate. |
| Word budget | Proposed implementation: cap the complete authored first-screen text at 350 words, including the closing question, three option labels, and freeform hint, counted once each. Store narration and choice counts separately. This conservative counting scope makes Tom's first-output limit explicit. Do not pad to 350. |
| Naming | Identify each presented participant. Where identity is intentionally unknown, make that clear without spoiling it. Do not list an unseen hidden character merely because they are physically nearby in source canon. Separate narrator disclosure from the protagonist having learned a fact in-world. |
| Stakes | Make the practical situation and cost understandable. Do not append an abstract explanation of what the decision means about the player's identity. |
| Delivery | Generate and validate once, then deliver the stored approved prose faithfully. Render the approved choices once through the existing UI and TTS policy; do not also include a second generated option list. |
| Opening state | Initialize the world to what the opening actually establishes. Do not mark an unchosen branch resolved, write inferred personality, or record a promise before the player acts. |
| Restraint reference | Tom references a separate Restraint Contract. Retrieve its actual version from the repository if present. Until located, enforce the explicit no-repetition/no-meaning-commentary/no-filler rules in this attachment; do not fabricate missing instructions. |

Tom's worked example is a craft demonstration, not a source-of-truth state initializer. It leaves the shadow unfastened while presenting the invitation, and its third option asks about “mother”; Peter Pan's current third authored option requires Peter to prove himself with a story or promise. Those meanings are not automatically interchangeable canonical paths.

## 2. What the repair file must carry for EACH title

Create one immutable **repair input record** before generation. Hydrate actual values from the repository/database; a title name and a generic prompt are insufficient. The following names describe a proposed repair-file interface, not existing database columns.

| Context block | Required content |
|---|---|
| Identity and provenance | Story ID/slug/title, session number, mode (Boiler or legacy), source asset versions/hashes, current template version, requested contract version. |
| Tom's contract | Full supplied contract as a versioned reference/text; the executable instruction block plus the integration decisions above. Keep its illustrative example clearly labeled. |
| Current diagnosis — COMPLETE | Raw `entry_point_diagnosis`: chosen moment, scores/rationale, entry adjustment, cut point, protagonist introduction, established/deferred facts, full `first_choice_spec`, format-specific cut and required reintroductions, current `cold_open`, emotional promise, editorial diagnosis, freeform hint if present, and all start-event identifiers. Preserve additional unknown fields. |
| Source truth | Actual selected source event and source excerpt; events between entry and handoff; relevant predecessor/successor events; essential canon rules and constraints. Keep event positions and database IDs distinct. |
| Protagonist and cast | Canonical and display names, role, current action, location, visible participants, relevant relationships, and deliberately withheld identities. Never infer the controlled character from whichever NPC acts in a source passage. |
| Voice | Actual title voice profile, anchor card, valid relevant exemplars, narrator stance, and character diction. Repair known malformed/wrong-viewpoint examples before using them. |
| Current opening package | Actual delivered/cached opening where available, initial state/defaults, active card, opening suggestions, canonical choice/path IDs and consequences, and startup/resume rules. Distinguish previews from recorded delivery. |
| Local route | Current first card, cards affected by the diagnosed cut, legal handoff card and next boundary, exit conditions, branch ownership, and session close requirements. Supply full relevant card payloads, not titles alone. |
| Feedback and defects | Concrete title-specific failures with evidence references; unknowns remain unknown. Do not apply Peter Pan defects to all titles as facts. |
| Change constraints | Allowed patch fields, protected IDs, live-game compatibility, unchanged model config, and required validation gates. |

**Do not flatten different schemas carelessly:** Peter Pan's `format_specific_cut.must_reintroduce` is an array of structured records; Anima's is a string. Normalize a reading view if helpful but preserve the original record and write back through the correct schema. Missing optional fields are not invented historical values.

If the source excerpt, first-choice binding, or handoff context is unavailable, produce a `needs_context` result for that title. Continue auditing other titles; do not fabricate canon or publish an incomplete package.

## 3. The repair workflow

### Step 1 — Export and diagnose before rewriting

- Enumerate the launch catalog and export one full input record per title.
- Store the original diagnosis unchanged with its hash/version and the currently rendered opening.
- Run Tom's five clarity questions against the existing output. Add checks for viewpoint, word count, branch binding, initial-state agreement, and active-card compatibility.
- Classify the repair: `already_compliant`, `prose_only`, `opening_package_mismatch`, `entry_point_reselection_needed`, or `needs_context`. These are proposed repair statuses.

### Step 2 — Resolve the handoff before writing polished prose

For every title, decide and record: **where the opening starts, what it establishes, where player control begins, which choice is pending, and which card is active there.**

Default to preserving the existing justified entry point and intended first choice. Do not move every story to a louder scene or impose one universal opening rhythm. If the current diagnosis and playable cards disagree, propose the smallest package repair rather than obeying both contradictory instructions.

Separate events already established as the authored starting situation from decisions the player is about to make. A prelude may establish supported background or routine motion; it must not silently choose an unresolved branch, grant an item without support, make a promise, or manufacture the player's motives. If an important intervening action is itself intended to be playable, shorten the prelude and repair the first-choice design explicitly instead of auto-completing it.

Do not equate “not included in the opening” with “already completed.” An omitted event must remain pending, be deliberately deferred, or receive a justified bypass treatment. The relevant canon and future dependencies must remain coherent.

### Step 3 — Generate one coherent candidate package

Apply Tom's prompt to the resolved input context. Produce:

- Revised `cold_open` prose, ending with the specific first-choice question.
- Exactly three concise, understandable suggestions derived from the supplied choice spec, plus a separate freeform hint/control.
- Explicit canonical bindings for mapped choices. Never infer A/B/C identity solely from array position, alignment label, or wording similarity.
- Starting/handoff facts and pending events, with source support.
- Proposed active moment and any local card/choice-binding changes required by the handoff.
- A patch to every diagnosis field made stale by the change: cut point, introduction, established/deferred facts, first-choice setup, reintroduction placement, and editorial explanation as applicable. Preserve the original beside the revised diagnosis.
- An exact field-level diff, patch rationale, validation report, and unresolved questions.

Keep visible suggestion labels separate from canonical defining lines. Shortening an option must not change what its branch does. Never create a fake choice ID to satisfy a schema.

### Step 4 — Validate and review

Run deterministic structural checks first, then semantic and voice review. Use a bounded repair pass for failed candidates; repeated failures remain held with a concrete reason rather than triggering unbounded regeneration.

The generation model's assertion that all checks passed is not sufficient evidence. Show the reviewer the first screen and supporting source/state/choice diff. Flag Austen for expert voice review as Tom requests; evaluate all titles for author fidelity.

### Step 5 — Apply, rebuild, and smoke-test

- Write the approved package through the authoritative producer/storage layer, not only a cached string.
- Apply prose, diagnosis, initial state, card/choice changes, and artifact versions consistently. Use the original version/hash as a conflict guard; stale patches must be re-evaluated, not forced through.
- Rebuild Section 13 and every dependent runtime cache. Keep old versions and a per-title rollback manifest.
- Test fresh start, each of the three options, one freeform action, Continue at the first decision, and reload/resume. Run at least the first three gameplay turns to detect replay of opening events or wrong-card initialization.
- Apply the new opening to new starts. Do not reset existing games, replace their experienced history, or replay the new opening on resume. Pin existing runs or handle compatibility explicitly.

Pilot Peter Pan and Anima, review the results, then process the rest of the catalog in a resumable batch. Audit all titles and report failures; do not require a full story re-boil to replace opening assets. Any entry/card regeneration is scoped to the affected opening package.

## 4. Specific repairs supported by the supplied files

### Peter Pan: Wendy's Neverland

**Confirmed context:** protagonist is Wendy. The diagnosis starts at Event 39 (`start_event_id=5762`) and intends to hand off at Event 50's window invitation. The delivered opening already repairs the shadow. The current initial card S1_M01 still expects the shadow to be repaired and has no choice IDs.

**Additional binding conflict:** cached Section 11 defines invitation choice `S1_C1` with paths A/B/C and a successor at S1_M06. The normalized card assigns `S1_C1` to S1_M02, while S1_M05 describes the invitation and first canonical branch but lists only `S1_E3`. Changing the initial pointer to S1_M05 alone is therefore insufficient.

**Required candidate:** preserve the diagnosis's invitation-first intention by default, then reconcile all supported preceding facts and the authoritative choice binding. Make the handoff card actually own the invitation choice, or adjust the route/choice design coherently through its producer. Do not blindly mark M01–M04 completed, award their items, resolve their choices, or declare all children ready to fly.

If keeping the invitation would force the system to consume meaningful playable actions, return an explicit alternative: an earlier first decision with a revised `first_choice_spec` and honest tracking. Do not quietly change the entry design inside prose generation. The reviewer should see this product difference in the patch report.

At handoff, the player has not accepted the invitation; John and Michael's states are explicit; shadow status and item ownership match the prose; first input resolves the offered choice and does not return to an obsolete shadow-repair card. The final question survives runtime delivery.

### Anima Machina

**Confirmed context:** diagnosis preserves the literal screenplay opening at Event 1 (`start_event_id=1819`), with handoff at Ilya's RENDAN assignment. The stored cold-open body does not explicitly name Nora even though the diagnosis's proposed reveal lines do. Its first two sentences are “White light. Chrome ribs.”, which fail Tom's plain-location requirement. The stored field also includes editorial wrapper markers such as `=== CUT POINT ===`; whether those reach the player must be checked in the delivery code.

**Required candidate:** retain the lab/pod entry and RENDAN choice. Ground the player plainly in the Amora lab immediately, identify Nora's name and diver role early, and keep the clipped author rhythm. Explain the immediate operation/assignment enough for a new player without introducing deferred Eli/Mirror material. Present Ilya clearly; an unnamed patient can be identified by role, without inventing a name. Use only source-supported time information; do not manufacture a year or hour to satisfy WHERE.

Strip editorial markers from player-facing prose through the defined authoring/rendering boundary. Preserve the existing compliance/refusal/subversion option meanings. Confirm the physical handoff location and legacy choice mapping from its architecture/design records; do not invent a Boiler moment key for this legacy export.

## 5. Required per-title result record

The repair implementation should emit this information in a structured record plus a short readable diff. The names below are proposed, not a migration requirement.

```text
title identity + session + runtime mode
input asset versions/hashes + contract version
status: compliant | candidate | needs_context | failed_validation | approved | applied
current_entry_point_diagnosis: complete original object
diagnosis_findings: rule, evidence, severity, proposed correction
revised_entry_point_diagnosis: complete candidate object
opening_package:
  narration; first question; three visible suggestions; freeform hint
  canonical choice/path bindings and unchanged consequence references
  state at handoff; established/deferred/pending events with provenance
  active card/legacy beat; expected first-input behavior
patches: exact target + expected old version/value + proposed new value + reason
validation: structural results; five clarity answers; voice review; replay results
review: reviewer, decision, unresolved items
apply_manifest: affected records/caches, resulting versions, rollback reference
```

Capture the full current diagnosis in the **input AND result**, so the human can compare why the story originally started there with what the repair proposes. Never replace the diagnosis with a generic summary or just paste Tom's contract above the old prose.

## 6. Gates before a repaired title ships

- [ ] First two sentences establish a concrete place and supported time/context.
- [ ] Protagonist name, role, and immediate action appear within roughly 60 words; narration uses second person and present tense.
- [ ] A newcomer can answer all five of Tom's clarity questions from the first-screen text alone.
- [ ] Presented participants are identified; deliberate mysteries remain legible and unspoiled.
- [ ] The full authored first screen is at most 350 words under the declared counting policy; no padding or editorial markers.
- [ ] The final question, three options, and freeform input are visible once; option meanings and tracked IDs agree.
- [ ] No unresolved player decision, promise, or personality inference has been pre-committed.
- [ ] Prose, starting facts, pending choice, and active card/legacy beat agree.
- [ ] Existing opening-only exceptions prevent question stripping, ordinary-turn length enforcement, and option regeneration from undoing the authored package.
- [ ] Both clicked paths and freeform input resolve correctly; Continue does not choose; subsequent turns do not replay events completed in the opening.
- [ ] Resume does not rerun initialization or redeliver the opening; previous valid games remain intact.
- [ ] Clarity and author-voice review pass; remaining failures are reported rather than hidden by a successful batch count.

## 7. Cursor instruction

```text
Implement a versioned cold-open repair pipeline using this specification and
Tom's contract embedded below. First locate the existing entry-point generator,
diagnosis storage, initial-state builder, choice bindings, runtime Section 13
assembly, startup/resume dispatcher, and opening output postprocessors.

Export complete current entry-point diagnoses and relevant source/voice/route
context for every launch title. Preserve those originals in each repair record.
Classify existing failures and resolve the intended handoff before generating
new prose. Use the actual canonical choice records, not the worked example's
options. Reconcile questions, budgets, suggestions, state, and active moments
as opening-only integration rules.

Produce reviewable candidate packages and exact diffs. Pilot Peter Pan and
Anima, preserving their different voices and schema modes. Repair the specific
Peter Pan invitation/card/binding mismatch; preserve Anima's literal lab entry.
Validate the first screen and first gameplay turns before application. Then
process the rest of the catalog with per-title status, bounded retries, version
guards, cache rebuilds, and rollback. Do not re-boil full stories, change the
model config, reset live games, or invent missing context to make a batch pass.
```

## Appendices

The source contract and current diagnosis snapshots below are evidence and repair context, not instructions to execute obsolete prose or to overwrite new state. Runtime asset/source hydration remains required by Section 2.


### Appendix A — Tom's supplied contract (paragraph text)

Source: `COLD OPEN CONTRACT — UNIVERSAL FIRST-OUTPUT (ALL TITLES).docx`. Paragraph wording retained; Word formatting is not reproduced.

SHA-256: `9b2946cff55ffe60d41f59ce23d30aee4b131731c18bc6b96f3d51f702da0274`

```text
Cold Open Contract — Universal First Output (All Titles)

Goal: every story's first output (≤350 words) leaves a first-time player with zero confusion — they know exactly where they are, who they are, what is happening, and what they must do — written in second person, present tense, as the named protagonist, and 100% in the title's author voice. Plugs into the opening generation (the entry-point cold_open / Section 13), delivered on turn 1.

RULE 0 — ALWAYS SECOND PERSON, PRESENT TENSE, AS THE NAMED CHARACTER (platform standard, non-negotiable)

LoreSpinner is participatory: the player becomes the protagonist. Therefore every opening — for this title and every title this prompt runs — is written in second person ("you"), present tense, addressed to the specific main character the logic supplies (here, Wendy Darling). The player is never an observer. "You are Wendy Darling, and you have only just woken," never "Wendy woke."

Voice is preserved inside second person, never by changing POV. The author's rhythm, diction, wit, and stance all live in the second-person narration.

For most authors this is natural (Poe's fever, Doyle's dry observation, an adventure title's momentum) — deliver it in second person.

For narrator-voice authors (Barrie, Austen): keep the intrusive/ironic authorial stance within the second person — a narrator who speaks to "you" and still knows more than you do. Barrie's asides survive directly ("which is, of course, no way to mend a shadow at all"). Austen's irony survives as narration about you that sees what you don't ("you are not wounded; you are entertained"). Do not drop to third person to preserve the narrator — carry the wit in the second-person voice. (Austen is the hardest case; flag her openings for an expert read.)

THE ONE CRAFT RULE ABOVE ALL

Clarity of orientation is non-negotiable and comes first. The author's voice governs HOW each beat is said; it never excuses leaving the player confused about where they are, who they are, who else is present, or what they must do. An oblique or ornate author still delivers all four beats plainly enough that a reader with zero prior knowledge understands them. Voice colors the orientation; it does not withhold it.

THE FOUR REQUIRED BEATS (all four, every opening, ≤350 words, second person)

WHERE — world + place. In the first two sentences, ground the player in the concrete setting: the place, the time, and just enough of the world to stand in it. Name the location plainly ("this is your nursery, in a London house, in the middle of the night"). The player must never wonder "where am I?"

WHO — identity + current action. Name the protagonist and their role within the first ~60 words, in second person, and say what "you" are doing right now ("You are Wendy Darling, the eldest, and you are the only one awake"). Earn it with an action or telling detail — but never leave it implicit.

WHAT'S HAPPENING — the inciting event, and everyone in the scene named. State what is occurring around you. Any other character present must be identified, not left as "a boy" or "a stranger." If the source names them, name them; if a mystery is intended, make it legibly a mystery ("a figure you do not recognise"), never an accidental gap.

OBJECTIVE — the situation to navigate + a clear first choice. State the situation you must handle, survive, or react to, and end on a clear, understandable first choice — a real decision the player comprehends. Never end on an atmospheric image the player cannot act on; end on the question and what it costs to answer.

CONSTRAINTS

Second person, present tense, as the named protagonist (Rule 0). No exceptions.

≤350 words. Within the budget, the four beats come first; atmosphere and voice serve them, never replace them. (Length was never the problem — priority was.)

100% in the author's voice, via the title's Voice Anchor: the author's rhythm, diction, and stance, rendered in second person.

End on the first agency moment — the choice from the entry-point first_choice_spec (canon-tied and tracked), plus the freeform option.

No repetition, no meaning-commentary, no filler (the Restraint Contract applies here too).

PASTE-READY PROMPT BLOCK (drop into the entry-point cold-open / Section 13 generation)

COLD OPEN CONTRACT — turn 1 only. Write the story's first output in ≤350 words, in SECONDPERSON, PRESENT TENSE, addressing the player directly as {{PROTAGONIST_NAME}}, in THISauthor's voice (see {{VOICE_ANCHOR}}). The player IS {{PROTAGONIST_NAME}}; never an observer.A first-time player with zero knowledge of this book must finish it completely un-confused.Deliver all four beats, in this order of priority:  1. WHERE: In the first two sentences, ground them — the place, the time, the world enough     to stand in. Name the location plainly.  2. WHO: Within the first ~60 words, name {{PROTAGONIST_NAME}} and their role and say what     "you" are doing right now.  3. WHAT: State what is happening around you. NAME every character present — never "a boy"     or "a stranger" when the source gives a name. If a mystery is intended, make it legibly     a mystery, not an accidental gap.  4. OBJECTIVE: State the situation you must navigate or react to, and END on a clear first     choice you fully understand, then the authored choices from {{FIRST_CHOICE_SPEC}}.Second person is mandatory. Clarity comes first; your voice is HOW you say each beat, never areason to withhold it. For a narrator-voice author (e.g. Barrie, Austen), keep the intrusive/ironic narratorial stance INSIDE the second person — a narrator who speaks to "you" and knowsmore than you do — but never switch to third person. No atmospheric ending the player cannotact on. No repetition, no commentary on meaning, no filler.Then apply the CLARITY TEST; if any answer is unclear, revise before delivering.CLARITY TEST (a stranger to this story must answer all five from your text alone):  - Where am I?   - Who am I?   - Who else is here?   - What just happened / is happening?  - What do I need to decide or do right now?

WORKED FIRST SCREEN — what the user sees (Peter Pan / Wendy Darling)

Second person, present tense, ~300 words, all four beats, Barrie's voice, ends on the choice.

It is the middle of the night, and this is your nursery — the top room of a tall house in London, where you and your two brothers have slept every night of your lives. You are Wendy Darling, the eldest, and just now you are the only one awake. Across the room, John and Michael sleep on. Your mother and father are still out at their dinner party, so the house is quiet — quiet, and not quite right. The night-light has burned out. The window your mother always latches stands wide open to the cold.

And there is a boy on the floor.

He is no older than you, dressed all in skeleton leaves, and he is crying — not the way one cries for help, but the way a child cries over a toy that has broken. The broken thing is his own shadow, come loose of him and refusing to go back on. He has been trying to fasten it with a cake of soap, which is, of course, no way to mend a shadow at all.

His name, he tells you — as though you ought to have known it all along — is Peter Pan. And perhaps you did know it, for you have heard of him in stories. He has flown in from a place called Neverland, where there are boys with no mothers, and no one to tuck them in or tell them tales at night. That, he says, is what he came for. Someone to give the lost boys their stories. Someone to be their mother.

Then he holds out his hand, bare and certain, and asks you to come with him — out the open window, off into the dark, this very night.

Do you take his hand?

Take his hand and go to the window.

Tell him you must wake John and Michael first.

Refuse, and ask what he means by "mother."

Or say or do something of your own…

Every clarity question is answerable, it ends on a real choice, it is fully second person — and it still sounds like Barrie.

HOW IT PLUGS IN & STAYS AUTHOR-TRUE

Governs the entry-point cold_open (built once per title) and the turn-1 delivery; feeds Section 13. Additive — it replaces only the opening-generation instruction. Uses {{PROTAGONIST_NAME}}, {{VOICE_ANCHOR}}, {{FIRST_CHOICE_SPEC}} so it drops into the Boiler assembly for any title.

Author-agnostic: the four beats, second-person rule, and clarity test are universal; the voice register comes from each title's Voice Anchor. Poe's opening is feverish and breathless, Austen's wry and knowing — both in second person, each in its own hand.

Validation: run the CLARITY TEST (five-question gate) plus the blind-voice read on every generated opening. Any opening that fails a clarity question, or drifts out of second person, is rejected and regenerated — no confusing or third-person openings ship.

Model note: this quality is what a capable model (Sonnet 4.6 / Opus 4.8) produces from this contract. On Haiku it will still under-orient and drift; the contract is necessary but lands only on a model that can obey it.
```

### Appendix B — Peter Pan current entry-point diagnosis

Complete original object from the supplied export; not a proposed repaired version.

```json
{
  "story": {
    "id": 51,
    "title": "Peter Pan: Wendy's Neverland",
    "slug": "peter-pan"
  },
  "source_file": "upload/full-boiler-output(1).json",
  "source_exported_at": "2026-09-21T12:21:57+00:00",
  "session_number": 1,
  "current_entry_point_diagnosis": {
    "entry_point": {
      "chosen_moment": "Event 39: Wendy wakes and speaks with Peter.",
      "rubric_scores": "Body: High — Wendy can wake, rise barefoot from bed, hear crying, and cross the nursery at once.\nTension: High — a strange boy is in the dark nursery, crying because he cannot mend his shadow and says he has no mother.\nThreshold: High — this is Wendy’s own threshold of approach, trust, and caretaking; she chooses whether to cross from bed to boy.\nExposition debt: Low — shadow, nursery, strange boy, and danger all read immediately without paragraph explanation.\nCore-stakes proximity: Very high — this is the first live meeting between Wendy and Peter, where trust, home, wonder, and mothering first lock together.",
      "entry_point_adjustment": "moved forward to: Event 39 — because Events 14-38 are rich setup but not Wendy’s first strong playable threshold within 300 words. Event 17 fails the agency standard for this session because Wendy is absent; Events 35-38 place Peter in action while Wendy still sleeps. Event 39 is the first moment where the player is present in Wendy’s body and can act on the story’s core dramatic question.",
      "cut_point": "The cold open ends on Wendy’s first decision after Peter says he came for stories and catches her toward the nursery window: whether she yields, delays for her brothers, or tests him first, handing off to the Phase 4 setup at Event 50."
    },
    "protagonist_introduction": {
      "identity_reveal_approach": "Body-first beat: Wendy wakes to cold feet, dark nursery, and the sound of a boy crying on the floor. The name lands when her getting out of bed becomes the irrevocable crossing into Peter’s world: You are Wendy Darling, and once you put your feet on this floor for him, the nursery is not only a nursery any more. Her role comes through instinct: she does not scream or hide, but reaches for her housewife and sets about mending what is torn. One loaded detail: the housewife in her hand, small and domestic, yet as fateful as any sword.",
      "reveal_lines": "You are Wendy Darling, and because you are that, a boy in tears on the nursery floor is not a marvel first but something to be set right.\nYour housewife is in your hand before you quite know you have taken it.",
      "resume_dump_check": "No stacked-label exposition is used. Wendy’s identity as tidy, nurturing, wonder-drawn, and nursery-formed is delivered through present action: rising, comforting, sewing, and deciding what to do at the window."
    },
    "situation_stakes_world": {
      "established": "Night in the Darling nursery; the pressing now is that a strange boy from the window has recovered his detached shadow, failed to fasten it, and is asking Wendy to leave home for Neverland. Active world rules: shadows are physical things that can be sewn on, and impossible visitors may come by the nursery window as if it were the door proper. The stake is immediate and personal: if Wendy trusts him and crosses to the window, home is behind her; if she refuses, Peter may vanish with the stories and wonder she has half-known all along.",
      "deferred": "Mrs. Darling’s prior discovery of leaves, the full fatal-Friday chain, Tinker Bell’s jealousy in detail, the mechanics of fairy dust, Peter’s fuller history, and the larger geography of Neverland are withheld for later beats."
    },
    "first_choice_spec": {
      "setup_prose": "Peter has told you, with the pride of a boy announcing supper, that he came for stories because neither he nor the lost boys know any. Then, as if stories and flight and disobedience are all one simple household matter, he catches you by the hand and draws you toward the open window. The night beyond it waits like a black nursery with the roof taken off.",
      "threshold_stake": "Whether Wendy crosses from home into Peter’s world now, and on what terms — trust versus caution, and adventure-pull versus caretaking duty.",
      "question": "What do you do?",
      "option_1": "You go with Peter at once, trusting the window because he is there and the adventure is already calling your name.",
      "option_1_alignment": "chaotic",
      "option_1_tracks": "trust_vs_caution",
      "option_1_value": "wonder and bold trust",
      "option_2": "You hold back and insist that John and Michael must be taught to fly before you leave any nursery properly.",
      "option_2_alignment": "lawful",
      "option_2_tracks": "caretaking_vs_self_enchantment",
      "option_2_value": "duty and protective fairness",
      "option_3_unexpected": "You keep Peter from the window for one more minute and make him prove himself with a story or promise of his own.",
      "option_3_alignment": "neutral",
      "option_3_tracks": "clear-sightedness within trust_vs_caution",
      "option_3_value": "discernment and earned trust",
      "not_a_tutorial": "This choice is the story’s central threshold: Wendy defines whether she enters Neverland as a swept-away child, a responsible elder sister, or a clear-eyed bargainer with wonder itself."
    },
    "format_specific_cut": {
      "cut_point": "Start moved from earlier nursery setup to Event 39, then cold open cuts at Event 50’s threshold question.",
      "original_before_cut": "Events 14-38: Mrs. Darling questions Wendy about Peter; skeleton leaves are found; the nursery danger is seeded; Peter later returns for his shadow, finds it, fails to attach it with soap, and cries.",
      "cut_eliminates": "The parental investigation, Nana and the fatal-Friday setup, Peter’s physical arrival and recovery of the shadow before Wendy wakes, and the explicit demonstration that he has already tried and failed alone.",
      "must_reintroduce": [
        {
          "fact": "Peter’s shadow is a detachable, physical thing, and the immediate problem is that he cannot make it stick; this matters because Wendy’s first bond with him is practical caretaking, not abstract fascination.",
          "marker": "COLD_OPEN",
          "target_moment_key": "S1_M01",
          "key_terms": [
            "shadow",
            "soap",
            "foot",
            "housewife"
          ]
        },
        {
          "fact": "Peter entered by the nursery window, which matters because the window is already functioning as a live threshold between home and the impossible.",
          "marker": "COLD_OPEN",
          "target_moment_key": "S1_M01",
          "key_terms": [
            "window",
            "nursery",
            "open",
            "night"
          ]
        },
        {
          "fact": "Wendy has already heard of Peter and half-believed in him before this meeting; this matters because she is not meeting a total stranger in a realist frame.",
          "marker": "LATER",
          "target_moment_key": "S1_M02",
          "key_terms": [
            "Peter Pan",
            "heard",
            "leaves",
            "window"
          ]
        },
        {
          "fact": "Tinker Bell is present in the nursery world and bound up with Peter’s arrival; this matters because fairy jealousy will soon complicate trust.",
          "marker": "LATER",
          "target_moment_key": "S1_M03",
          "key_terms": [
            "Tinker Bell",
            "drawer",
            "fairy",
            "light"
          ]
        },
        {
          "fact": "John and Michael are asleep nearby and can be included or excluded from Wendy’s decision; this matters because Wendy’s caretaking instinct extends beyond herself.",
          "marker": "COLD_OPEN",
          "target_moment_key": "S1_M01",
          "key_terms": [
            "John",
            "Michael",
            "beds",
            "brothers"
          ]
        }
      ]
    },
    "cold_open": "Your feet find the cold floor before your wits are quite awake, and the nursery feels wrong at once; the night-lights are gone, the window stands open, and a boy is crying on the floor as if this were the most ordinary misery in the world. You are Wendy Darling, and because you are that, you do not scream. A torn thing asks to be mended. His shadow lies across his foot, black and sulky; there is soap beside him, which is not the way to sew anything, as you know very well.\n\nOf course a boy who comes in by the window and brings his own shadow loose is not to be judged by common rules. Still, there he is, all in skeleton leaves, proud even in weeping. Your housewife is in your hand before you know it, and presently the shadow behaves, though a little creased, which is often the best one can hope of this world.\n\nThen he tells you he came for stories, because the lost boys have none; and, as if that settled everything, he catches your hand and draws you toward the open window. John and Michael sleep behind you. The dark waits in front. What do you do?",
    "emotional_promise": "The emotional promise of this cold open is: transgression. A user arrives feeling curious and wanting to cross.",
    "freeform_input_hint": "The window is open, but perhaps the bargain is not yet made.",
    "editorial_diagnosis": "I cut the entire preamble before Wendy’s embodied threshold, though much of it is delightful and canon-bearing, because Session 1 needs the player in Wendy’s body and acting within the first beat. Events 14-16 establish Peter as hearsay and domestic unease, but they are not yet Wendy’s active pressure. Event 17 is vivid, yet Wendy is absent; opening there would make the player a spectator to Mrs. Darling’s shock. Events 18-38 deepen the nursery mystery and set up the shadow beautifully, but the agency still belongs first to adults, Nana, Tink, and Peter. The first moment that fuses body, pressure, identity, and core stakes for Wendy is Event 39: she wakes, hears crying, sees the impossible boy, and can choose whether to approach. That cut preserves the foundational identity-through-action beat, because Wendy’s essential nature is expressed immediately in how she responds: not with analysis, but with mending. I then carry forward enough of Event 40 and the approach to Event 50 to let the opening culminate in the true threshold of Session 1: whether she goes to the window, delays for her brothers, or requires Peter to earn trust. What was cut is mainly exposition debt and off-body setup; what remains is the first playable knot where home, wonder, and Wendy’s caretaking all become one decision.",
    "start_event_position": 39,
    "start_event_id": 5762,
    "start_event_chapter_position": 3,
    "start_event_local_position": 7
  }
}
```

### Appendix C — Anima current entry-point diagnosis

Complete original object from the supplied export; not a proposed repaired version.

```json
{
  "story": {
    "id": 25,
    "title": "Anima Machina",
    "slug": "anima-machina"
  },
  "source_file": "project_sources/05-anima-adaptation-v23.json",
  "source_exported_at": "2026-06-23T16:12:38+00:00",
  "session_number": 1,
  "current_entry_point_diagnosis": {
    "entry_point": {
      "chosen_moment": "Event 1: Dive loop is cleared in the lab. Nora is inside the pod, embodied, under physical and procedural pressure, and can act immediately as the dive ends and Ilya’s assignment follows within the first beat.",
      "rubric_scores": "Body: Very high. Frost on glass, port glow, breath returning, cable at the neck. Tension: High. A dead husband vanishes from the patient’s screen and Nora exits a system built on managed grief. Threshold: High. The first real line-crossing is Nora’s own refusal or acceptance of the next dive assignment from Ilya, reached directly from this opening. Exposition debt: Low. The pod, HUD, cleared loop, and patient’s empty grasp teach the world by action. Core-stakes proximity: High. This is Nora’s identity-through-action as a grief diver inside the exact system she will later defy.",
      "entry_point_adjustment": "literal opening kept. This is Session 1 of a screenplay source with prefer_literal_opening=true, and the earliest available session moment is already playable. It contains embodied action, active pressure, a protagonist-facing problem, and a meaningful first choice can be reached within roughly 300 words.",
      "cut_point": "The cold open ends in the debrief corridor as Ilya sets down the RENDAN shard and Nora must decide how to answer the assignment. Phase 4 SETUP then carries the immediate fallout of that decision."
    },
    "protagonist_introduction": {
      "identity_reveal_approach": "Body first through the pod opening, the port cooling at her throat, and the practiced removal of the cable. Her role is earned through reflex and competence inside the lattice and in how she clears the loop without hesitation. Her name lands when Ilya addresses the assignment pressure and the refusal becomes personal. One loaded detail carries residue and history: the throat lit by the port.",
      "reveal_lines": "The pod opens. Cold air on your face. The light at your throat dies down by degrees. Then Ilya, already at the console, says your name like a file she has not closed. Nora.",
      "resume_dump_check": "No stacked labels. No biography block. Profession, status, and damage are delivered through present-tense procedure, system text, and the body’s practiced knowledge under pressure."
    },
    "situation_stakes_world": {
      "established": "You are in an Amora dive lab, just back from clearing someone else’s grief loop. The immediate pressure is physical recovery from the dive and the moral residue of watching a dead husband vanish from a screen while the living patient reaches for cold rail. Two world rules are active now: memory-diving is done through pod and port hardware, and cleared grief loops erase the projected dead from the interface. The stake is immediate and personal. Ilya has another job ready. Taking it means going back into the machine. Refusing it means stepping out of line in front of the system that owns your work.",
      "deferred": "Eli’s death, the Mirror, the eight-month gap, compliance notices, citywide reset pressure, Jace, Riven, and the larger hunted-fugitive arc are withheld for later beats."
    },
    "first_choice_spec": {
      "setup_prose": "Ilya sets the shard beside you. RENDAN. No greeting. Just the label between you. The pod hisses shut behind your back. The patient across the lab is awake now, reaching for a face the screen does not hold anymore.",
      "threshold_stake": "Whether Nora stays a functioning instrument of Amora or asserts self-ownership at the first institutional demand. This turns directly on compliance_vs_defiance and on her relationship to managed grief labor.",
      "question": "What do you do with the shard?",
      "option_1": "Take the RENDAN shard and hear Ilya out before you decide.",
      "option_1_alignment": "lawful",
      "option_1_tracks": "compliance_vs_defiance",
      "option_1_value": "control through procedure",
      "option_2": "Leave the shard where it is and walk out without a word.",
      "option_2_alignment": "chaotic",
      "option_2_tracks": "compliance_vs_defiance",
      "option_2_value": "self-ownership",
      "option_3_unexpected": "Palm the shard, not for the dive, but to keep it out of Amora’s hands.",
      "option_3_alignment": "neutral",
      "option_3_tracks": "compliance_vs_defiance",
      "option_3_value": "protective suspicion",
      "not_a_tutorial": "This is not a side kindness test. It is Nora’s first identity-defining collision with the system that uses her body and labor, and it sets the session’s central line: comply, refuse, or subvert."
    },
    "cold_open": "=== COLD OPEN: ANIMA MACHINA — Session 1 ===\nWhite light. Chrome ribs. Frost on the glass.\n\nYour eyes open hard.\n\nThe port at your throat goes dark. Breath comes back in pieces. Across the lab, the second pod is still. The shaking inside it has stopped.\n\nA monitor above the patient reads: GRIEF LOOP / CLEARED.\n\nOn his screen, a dead man had been mid-sentence. Warm. Holding.\n\nNow the loop thins. Goes.\n\nThe patient wakes and reaches for him anyway. Finds cold rail.\n\nThe pod hisses open behind you. You pull the cable from your neck. Slow. Practiced. Wipe the residue away with two fingers.\n\nA glass corridor. Clinical light.\n\nIlya waits at the console in white, level, already holding the next thing they want from you. She sets a shard down between your hand and hers.\n\nRENDAN.\n\nNo greeting.\n\nAcross the room, the patient is still staring at an empty screen.\n\nIlya leaves the shard where you can take it.\n\nWhat do you do with it?\n=== CUT POINT === Hands to Phase 4 SETUP beat in the debrief corridor, with Ilya’s assignment and Nora’s first act of compliance, refusal, or subversion.",
    "emotional_promise": "The emotional promise of this cold open is: defiance. A user arrives feeling clinically trapped and wanting to choose who gets to own their grief.",
    "editorial_diagnosis": "Candidates considered: Event 1, Event 2, Event 4, Event 6. Agency gate: Event 1 PASS because Nora is present in her body and can act as the dive ends. Event 2 PASS because Nora is embodied in the corridor and can accept or refuse the shard. Event 4 PASS because Nora is present, discovers Eli, and can choose how to answer the Mirror. Event 6 PASS because Nora wakes, receives compliance notices, and can act. Because this is a screenplay source with prefer_literal_opening=true, the earliest available session moment is presumed playable unless it fails the test. Event 1 does not fail. It is not credits, not lore dump, not inert throat-clearing. It has body, machine, signal, pressure, and a reachable choice. Therefore relocation is not allowed. What is not cut: Nora’s identity-through-action as a diver clearing grief, and her first real agency moment with Ilya’s shard. Moving forward to Event 4 would create a stronger trailer beat but would wrongly amputate the literal opening, the profession grounding, and the institutional frame that makes her later defiance meaningful. Moving forward to Event 6 would skip the moral cost of her work and flatten the opening into notice-driven exposition. So the surgery is compression, not relocation: begin at the pod, strip to the active beats, and hand the player the shard before 300 words.",
    "format_specific_cut": {
      "cut_point": "Begin at Event 1 and compress directly from pod exit to Ilya placing the RENDAN shard in the debrief corridor.",
      "original_before_cut": "The source literal opening includes the full pod image, the interior lattice of the dive, the patient aftermath, and the debrief corridor with Ilya’s assignment.",
      "cut_eliminates": "Extended visual dwell inside the lattice, extra corridor business, and any delay between clearing the loop and Ilya’s assignment that would slow first agency.",
      "must_reintroduce": "Nora’s competence as a diver, the patient’s cold reach after the dead husband vanishes, Ilya’s controlled authority, and the RENDAN label as the first live threshold."
    },
    "start_event_position": 1,
    "start_event_id": 1819,
    "start_event_chapter_position": 1,
    "start_event_local_position": 1
  }
}
```
