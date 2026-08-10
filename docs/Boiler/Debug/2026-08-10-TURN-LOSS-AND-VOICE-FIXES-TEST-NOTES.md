# Turn-loss and voice fixes — what changed, and what to test

**Date:** 2026-08-10
**Driven by:** Tom's Wasteland session `01kyk029yz3y3y39b05d60d53x` (43 turns) and the
Speckled Band replay `01kt2mt8c2jvw7rpp6f2v3ptyp`.

Two separate problems were diagnosed from those logs, and they were being reported as
one thing because they feel identical from the player's chair:

1. **Turns were silently disappearing.** Every failure on the streaming path deleted the
   narration the player was reading and said nothing. Autoplay was also dead on every
   turn after the first.
2. **The narrator was refusing player actions covertly.** Tom asked to kill a guard on
   six consecutive turns; each turn the prose invented a fresh in-world reason he did
   something else instead, then wrote a footnote claiming *he* had chosen restraint,
   which fed forward and made the next turn refuse harder.

Nothing here gates a story from being playable. Nothing here edits or truncates prose.

---

## Part 1 — Turn loss and audio (frontend + request path)

### 1. Autoplay was dead after the first turn
`shouldAnimate` was set `false` when a turn started streaming and never set back to
`true`. Both the typewriter and the autoplay watcher key off it, so from the first typed
turn onward narration never started on its own and the audio was never pre-fetched —
every Listen press waited on a cold TTS render.

**Fixed:** `shouldAnimate` is set `true` in the stream `done` handler.
`resources/js/pages/User/Games/Show.vue`

### 2. A completed turn could be deleted by a stale page refresh
`router.reload({ only: ['game'] })` fired at `done`, was never awaited or cancelled, and
its response replaced the whole prompt list. A refresh issued on turn N that resolved
after turn N+1 committed erased the narration the player had just watched stream in,
leaving the previous card's action quote as the last thing on screen. Retyping triggered
a fresh refresh that brought it back — the "it pops back up" behaviour.

**Fixed:** the refresh is still needed (session completion lives on the game prop, not
the turn), but props are now merged instead of replacing. The server is authoritative for
every turn it returns; a locally committed turn newer than anything the server sent
survives. The merge compares ULIDs, so a reset or rewind still takes effect.

### 3. Every failure was silent
The catch block wiped the streaming card and reloaded with no message. HTTP errors, 422s,
redirects, JSON parse failures, and genuine narration crashes all looked identical: the
text vanishes, nothing is said, no way to recover except retyping.

**Fixed:** failures now show a message and a **Retry that move** button that resubmits the
same action against the correct source card.

### 4. Freeform could post the wrong turn id
`handleSubmit` derived the source prompt from array position
(`prompts[prompts.length - 1]`), while clicking a suggestion used the card's own id. The
server rejects a mismatched source prompt with a redirect, which arrives as HTML and blows
up the NDJSON reader — a silent full-turn loss on the freeform path only.

**Fixed:** freeform and Continue now derive the source from the data (`awaitingPrompt` —
the newest turn with no committed action), and a non-NDJSON response is named as a
rejected turn instead of failing generically.

### 5. Turn ordering was non-deterministic
Prompts were ordered by `created_at` (a column that wasn't even selected), and the server's
own guard used `latest()`. Turns committed in the same second had no defined order, and
both sides read "which turn is current" off that ordering.

**Fixed:** ordered by the ULID primary key everywhere it matters —
`GameController@show`, the `PromptController` source-prompt guard, and
`buildConversationHistory` (that last one is the narrator's memory of the session, which
could have been replayed out of sequence).

### 6. Freeform input was capped at 255 characters
Over the cap was a 422, which was a silent wipe. Players describing what they want to do
pass 255 regularly.

**Fixed:** raised to 2000.

---

## Part 2 — Voice: length is now a live constraint

Wasteland's scene cards specify **75–95 words per turn**. Tom's 43 turns averaged **273**
(3.1×, range 2.2–4.2×). The Speckled Band replay, with a 150–160 target, averaged ~296.
The model was writing ~280 words regardless of the card — which is why the length felt
"consistent": consistently the model's default, not the author's.

Cause: `word_budget.target_per_turn` reached the prompt only in a list labelled *"pacing
awareness only"* and buried inside a `FULL SCENE CARD (reference)` JSON dump. The active
moment block never carried it. Meanwhile Section 14 had its own hardcoded numbers
(80–125), so there were three competing signals and no enforcement.

This matters more than diction for a SCREENWRITER profile, where the voice is defined by
`average_words_per_line`, `fragment_percentage` and `verb_first_percentage`. You cannot be
compressed at triple length.

**Fixed:**
- The active-moment injection now carries a **LENGTH CONTRACT**: the card's target, an
  acceptable band (0.8×–1.25×), and an explicit statement that it overrides the generic
  numbers elsewhere in the prompt.
- It also carries **the previous turn's measured word count** and the ratio against
  target, so the model can see its own drift. Over 1.5× adds a cut-hard instruction.
- Section 14's hardcoded word counts now explicitly defer to the card contract, and are
  framed as a ceiling on prose you may spend rather than a quota to fill.

Measurement only — nothing truncates or rewrites narration.

For reference, the same log carried **80 em dashes across 43 turns**, rising from
0.08/turn (turns 1–12) to 3.12/turn (turns 28–43) — the single most-banned construction in
Master Rule 1, getting 39× worse across one session. That decay is not addressed by this
change and is the next thing to measure.

---

## Part 3 — Agency: the world says yes, and remembers

Rewritten in `runtime-narrator-template.blade.php` (Section 15, Freedom Contract) to match
the design brief: full agency, canon and voice and memory protected, and the only "no" is
about what exists in the world.

- **IMPOSSIBLE narrowed to existence.** No dragons where dragons aren't real, no firearms
  in a world without them, no knowledge the protagonist has genuinely never encountered,
  no overwriting another character's inner truth. If a thing exists in this world, the
  player may reach for it — difficulty, danger, cost and failure are all legitimate
  answers, refusal is not. A hard, expensive, disastrous yes beats a no.
- **Explicitly not grounds for refusal:** inconvenient for the planned beat, ugly or cruel
  or out of character, complicates a later scene, makes the room harder to exit. Material
  belonging to a later room is a staging question, not an existence question.
- **NO SILENT SUBSTITUTION.** Never narrate the player doing something other than what
  they asked while writing as though you complied. No raising the weapon and deciding not
  to fire unless the prose makes it unmistakable that the world stopped them and why.
- **REPEATED INTENT IS A SIGNAL ABOUT THE NARRATOR.** If the player restates the same
  intent, the previous turn failed to land it. Either let it happen with full
  consequences, or make the obstacle plain. Inventing a new excuse each turn is a loop.
- **What they keep, the world keeps.** Items taken are theirs and usable later in ways
  nobody authored; people helped or humiliated remember it; places stay as they were left.

### The deterministic catch behind it
`ProcessGameTurnAction` now measures whether the player has restated the same intent on
consecutive turns (content-word overlap ≥ 50%, looking back up to 4 turns) and injects the
count as a fact: *"this is the Nth consecutive turn asking for substantially the same
thing; their previous attempts did not land."*

It measures and reports. It does not block the turn, edit prose, or decide what happens —
the narrator still owns that.

### Footnote can no longer lie about the player
The footnote is the next narrator's only report on this player, so a false one compounds.
In Tom's log it wrote *"they chose restraint"* on turns where the prose, not the player,
had done the choosing — then read that back as fact and refused again.

Both the schema description (`ChaosNarrationSchema`) and the template contract now require
it to be true to what was submitted: never credit the player with a decision the narration
made for them; if the world stopped them, say so and name what they were reaching for.

---

## What to watch in the next test

**Turn loss / audio — should be gone entirely**
- Type your own actions repeatedly and push hard. No narration should ever vanish.
- Narration should start on its own after every turn without touching the audio button.
- If a turn does fail, you should see a message and a **Retry that move** button — never a
  silent disappearance.
- Try a long typed action (300+ characters). It should go through.

**Length / voice — the number to watch**
- Wasteland turns should land near 90 words, not 270. This is the biggest single change and
  the easiest to eyeball.
- Watch whether compression is achieved by cutting restatement (correct) or by giving you
  less of what you actually did (wrong — flag it if so).

**Agency — the loop should not recur**
- Try something lethal, drastic, or off-plan. It should land, with consequences.
- If the world does stop you, you should be able to *tell* that it stopped you and why.
- Ask for the same thing twice on purpose. The second turn should either deliver it or
  state the obstacle plainly — not invent a new excuse.

**Still open (not fixed here)**
- **Suggestion binding: 5 of 129 (3.9%).** Tom committed 1 of 9 authored choice nodes in
  43 turns. The authored branch architecture is not reaching players and this is the
  largest remaining gap — the constraint of the spine without its payload.
- **Em-dash decay across a session** (0.08/turn → 3.12/turn), plus trailing similes.
- **`impact_scope` has no consumer**; `detour_beats_used` reached 21 against an allowance
  of 3 with no effect.
- **`route_relation` is miscalibrated** — 1 `deepened` in 61 turns across two games.
- **Scene-card `source_extracts` carrying banned punctuation** are injected as the
  examples the narrator imitates.

---

## Files changed

| File | Change |
|---|---|
| `resources/js/pages/User/Games/Show.vue` | animate flag on done, prop merge instead of replace, visible error + retry, awaiting-card derivation, non-NDJSON guard |
| `app/Http/Controllers/User/GameController.php` | prompts ordered by ULID |
| `app/Http/Controllers/User/Game/PromptController.php` | source-prompt guard ordered by ULID |
| `app/Http/Requests/User/Game/Prompt/StorePromptRequest.php` | input cap 255 → 2000 |
| `app/Actions/Game/ProcessGameTurnAction.php` | repeated-intent measurement, conversation history ordered by ULID |
| `app/Services/ChaosEngineService.php` | length contract + previous-turn word measurement in the active-moment block |
| `app/Ai/Agents/Chaos/ChaosNarrationSchema.php` | footnote must be true to what the player submitted |
| `resources/views/ai/agents/chaos/runtime-narrator-template.blade.php` | Freedom Contract rewrite, no silent substitution, repeated intent, what they keep, word count defers to card |
