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

### 10. Cost and speed: trimming, voice lock, model choice

Emma's first boil failed in IP trimming: all 55 chapters timed out on Opus 5.5 (`MaxAttemptsExceededException`, no other error). The design was the problem, not just the model.

- **Trimming returns decisions, not text.** Before, chapters were cut into 4,000-character chunks (hundreds of calls per book) and the model retyped every preserved word. Now each chapter is split into numbered paragraphs and sent whole (60,000-character slices only for very long chapters). The model returns paragraph ranges marked PRESERVE or TRIM plus its notes, and `IpTrimmingChapterJob::assembleTrimmedText` rebuilds the text from the original paragraphs with the same `[TRIMMED: …]` markers. Preserved prose is verbatim by construction, and an unclassified paragraph is kept, never lost. Output per call drops by roughly 90 percent.
- **Trimming model: `gpt-6-luna`, reasoning effort `low`** ($0.10 / $0.50 per million tokens, about $0.10 per book for Emma, against roughly $4 on Opus 5.5). Triage is focused classification and the code protects against its mistakes (preserved text is copied, unclassified paragraphs are kept), so the smallest reliable model is the right size. With reasoning on, OpenAI rejects `temperature` and `top_p`: the agent sets reasoning through `providerOptions()` and must never carry a `Temperature` attribute. The laravel/ai OpenAI gateway uses the Responses API, which GPT-6 requires for structured output.
- **Timeouts:** 240 s per call plus headroom, capped at 1,100 s, so a running job never outlives the database queue's 1,200 s retry window and gets handed out twice. Tries reduced to 2.
- **Voice lock is unchanged: it reads every chapter.** The author's voice is the product, so it is not sampled.
- **Unchanged on purpose:** draft and finalize stay on Opus 5.5 (they write the scene cards in the author's voice). Format detection, entry point, session map, architecture, choice design, consequence mapping, close and editorial verification stay on `gpt-5.4`, which is proven with the `temperature` values those agents set.

Model research (October 2026): OpenAI's current API tiers are GPT-6 Astra ($10 / $50), GPT-6.1 Sol ($2 / $10) and GPT-6 Luna ($0.10 / $0.50); GPT-5.6 Sol, Terra and Luna are the previous generation. Anthropic: Opus 5.5 ($4 / $20), Sonnet 5 and 5.5 ($2 / $10), Haiku 5.5 ($0.10 / $0.50 up to 100K-token prompts). GPT-6.1 Sol is cheaper than `gpt-5.4` ($2.50 / $15) and stronger, so it is the candidate for the `gpt-5.4` steps. Migrating them means removing their `Temperature` attributes and setting reasoning effort, so it is a separate change after one validated boil.

## What did not change

- Product and story contract: the same phases, the same canonical four branching choices per session, the same scene cards, consequence maps and runtime prompt. The runtime reads the same fields.
- Story contract and outputs. See section 10 for the model changes.
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
