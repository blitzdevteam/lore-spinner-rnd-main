# Boiler pipeline hardening (October 2026)

Commit: `8eef1138` on `main`. Supporting commits for the manual repair tool: `d8891f08`, `4b0020a2`, `24a8876f`, `1d90fcd5`, `11d797ab`.

## Why

Peter Pan needed eleven manual repair passes after its boil (Curt and Tom QA, Oct 5). Every defect class traced to something the pipeline never checked or never knew. This pass moves those lessons into generation, so a new boil (Emma first) comes out clean and repair stays a manual tool for exceptions.

## What changed

### 1. Entry points run first (cross-session handoffs)

Before: each session ran its whole chain in parallel and alone. A session's close was written without seeing the next session's opening, so every boundary disagreed.

Now (`SessionAdaptationChain::dispatchBatch`):

1. Batch 1: `EntryPointDiagnosisJob` for every session, in parallel.
2. Batch 2: the rest of each session's chain, in parallel, for sessions whose entry point succeeded.

`SessionCloseJob` and `ConsequenceMappingJob` receive the next session's authored cold open (`SessionHandoff::nextSessionOpening`), and know when they are writing the final session (`SessionHandoff::isFinalSession`). Single-session reruns (`jobsForSession`) still start with the entry point.

Cost: one extra round of waiting for the entry-point calls. No extra model calls.

### 2. Entry point: continuity, no closing question, validated

- For session 2 and later the prompt includes the previous session's last two source events. The cold open must continue from there and must state plainly any skipped event that changes where the protagonist is, who is with them, or what has been done to them (capture, separation, journey).
- The start event may not skip a must-breathe event from the Story Session Map.
- The cold open ends on the action the first choice answers. It no longer ends on the choice question (the old prompt required that, which contradicted the runtime and the repair contract).
- The cold open stops where the first scene card begins and never tells an event a card will play.
- `must_reintroduce` facts are marked COLD_OPEN only when the cold open itself states them.
- New `EntryPointDiagnosisValidator` (start event in session, no skipped must-breathe events, the cold-open contract from `ColdOpenRepairValidator`) with a three-attempt repair loop.

### 3. Draft: story order enforced

`BoilerDraftValidator::validateStoryOrder` rejects a draft when:

- a card goes back in the story (its earliest event or its anchor comes before the previous card's), or
- a card stages an event from before the entry point (the cold open covers those; they take CUT, ECHO or MONTAGE with a reintroduction note).

This is the Peter Pan double-gun and lagoon-rewind defect.

### 4. The player is only the protagonist

`PlayableProtagonist::for($story)` names the protagonist, and `_protagonist-rule.blade.php` is included in the entry point, draft, choice design, consequence mapping, session close and finalize prompts:

- only the protagonist is "you";
- scenes without the protagonist are third-person interludes;
- every desire, affordance, choice option, posture shift and session-end option belongs to the protagonist, where they actually are, including during interludes.

Enforced in code: every branching option (`ChoiceDesignJob::validatePlayerOwnedOptions`) and every session-end option (`SessionCloseValidator`) must address the player as "you".

### 5. Choice design rules

Prompt rules (not hard checks): a choice is bound to the card where its setup has already played; no option may destroy or resolve something a later card needs; an outcome stops before the next card's events; flags and inventory describe their own option.

### 6. Session close: validated, with a repair loop

Before: one call, no repair. Now `SessionCloseValidator` with three attempts:

- resolution, transition and final line may not end on a question;
- the final line may not talk to the player about choosing;
- session-end options must be the player's own action.

Prompt changes: present tense with interludes in third person (no more "second person throughout"), stop before what the choice outcomes narrate, `next_session_opens` written from the next session's real cold open, final session lands on the book's ending.

### 7. Finalize: one continuous sequence

Finalize now receives the protagonist and the cold open. The first card begins where the cold open ends, each card begins where the previous bridge leaves off, bridges never stage the next card's opening, opening images stop before their own choice, and dialogue is quoted and attributed.

### 8. Banned punctuation cleaned automatically

`BannedProseValidator::neutralizeArtifact` cleans every prose string in entry point, choice design, consequence mapping and session close outputs (semicolons, em dashes, double hyphens, ellipses), capitalizing the next sentence. Identifier keys (ids, moment keys, labels, markers) are untouched.

### 9. Truncated responses are never accepted

`GeneratesValidatedArtifact::completeArtifact` rejects any response that stopped at the output-token limit, so it is retried instead of saved with missing sections. Applied to entry point, draft, architecture, choice design, consequence mapping, close and finalize.

## What did not change

- Product and story contract: the same phases, the same canonical four branching choices per session, the same scene cards, consequence maps and runtime prompt. The runtime reads the same fields.
- Models: draft and finalize on Opus 5.5; entry point, architecture, choice design, consequence mapping, close and editorial verification on GPT-5.4.
- Already-boiled titles are untouched. These changes only affect new boils and reruns.
- Repair stays manual (`stories:repair-session`), not a pipeline step.

## What to watch on the first boil (Emma)

Stricter checks mean more repair attempts. In the logs, look for `boiler.<phase>_validation_failed` (a check failed and the model is fixing it) and `boiler.<phase>_corrected` (fixed). A session that fails a check three times now fails visibly with the reason instead of saving the defect. Send any such failure for review.

Open decisions: remove `EditorialVerificationJob` (advisory only, one call per session), and whether to move entry point, choice design, consequence mapping and close to Opus 5.5.

## Tests

- `tests/Unit/BoilerArtifactValidatorTest.php` (story order, pre-entry staging)
- `tests/Unit/BoilerHandoffValidatorTest.php` (entry point and close validators)
- `tests/Unit/BannedProseValidatorTest.php` (artifact cleanup keeps identifiers)
- `tests/Feature/Boiler/SessionAdaptationChainTest.php` (entry points first, handoff lookup)
