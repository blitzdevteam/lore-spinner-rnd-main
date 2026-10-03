# Cold open repair

3 October 2026. How the review repair works, what it reads, and what it writes.

This repair runs on any story, one title at a time. The same contract is used for every title. Peter Pan is the pilot we check first, not a special case inside the prompt. It does not change the entry-point pipeline agent (`EntryPointDiagnosisAgent`). That agent still has the old opening instructions. Move this prompt into the pipeline only after a repaired opening is greenlit.

## Is this the first session?

Yes. Tom's contract is the **first output a new player reads**: session 1, turn 1.

The command defaults to session 1:

```
stories:repair-cold-open peter-pan
```

That session's stored `entry_point_diagnosis.cold_open` is the prose the game delivers word for word when a new game starts. The repair rewrites that field. It does not rewrite later turns, and it does not rebuild the cached runtime narrator prompt. Live delivery already injects `cold_open` from the diagnosis on the session-start turn.

`--session=` can point at another session number, but the prompt is still Tom's first-output contract. It does not include the previous session's handoff. Use it for session 1 until a later-session opening is designed on purpose. If you pass another session, the command says so before it does anything.

## What the model is told

The system prompt is Tom's Cold Open Contract (the text in `LoreSpinner_Cold_Open_Repair_Spec.md`, Appendix A), with the story's own values filled into his three slots:

| Tom's slot | Filled from |
|---|---|
| `PROTAGONIST_NAME` | Launch catalog, then the story session map, then the IP audit. Peter Pan is `Wendy Darling`. |
| `VOICE_ANCHOR` | `story_adaptations.voice_profile.voice_anchor` and `anchor_card`. |
| `FIRST_CHOICE_SPEC` | `entry_point_diagnosis.first_choice_spec` for that session. |

The contract's rules are his: Rule 0 (second person, present tense, as the named protagonist), the four beats (where, who, what is happening, the decision), the clarity test, and the voice-anchor rule.

Tom's worked Peter Pan screen is an example of the desired outcome, kept in the spec. It is not pasted into the system prompt. A prompt that runs on every title does not name that story, its characters, or its scene. Those details arrive only when that story's own record is loaded into the slots. The example's last line, a question to the player, is not part of the desired outcome. The prose ends on the action of the choice. The story's own question is passed in so the model knows the decision, and it is marked as text that must not be written.

The user message is the rest of the record, so the model is not guessing:

- What the diagnosis says is already true (`situation_stakes_world.established`)
- What must stay out (`deferred`)
- Facts marked `COLD_OPEN` in `format_specific_cut.must_reintroduce`. Facts marked `LATER` are left out. A title that stores `must_reintroduce` as one string (Anima) keeps that string.
- The previous `cold_open`, so the model can see the opening it is replacing and must not finish a task the player has not chosen
- Source events from `start_event_position` through the next 11 events, capped so the prompt stays bounded

## How a run works

1. Resolve the story by slug or numeric id. It must have an adaptation.
2. Load that session's `entry_point_diagnosis`. Session 1 if `--session` is omitted.
3. Load the voice profile and the source events for that session.
4. Stop if the protagonist name, the voice anchor, or the situation is missing. Nothing is invented to fill a gap.
5. `--dry-run` prints the protagonist, the cold-open facts, and the question being kept off the prose. It does not call the model and does not write. `--show-prompt` on a dry run prints Tom's filled system prompt and the user message.
6. Without `--dry-run`, `ColdOpenRepairAgent` calls `claude-sonnet-4-6` at temperature 0.6. The system prompt is Tom's contract, with no title baked in. The user message is the pulled record. The model returns one field: `cold_open`.
7. The candidate is rejected if it is empty, contains an editorial `===` marker, runs past 350 words, does not address the player as "you" at the start, does not name the protagonist within the first 80 words, or ends on a question. A rejected candidate is printed and not saved.
8. A candidate that passes is printed and still not saved, unless you also passed `--apply`.
9. `--apply` writes the new prose onto `entry_point_diagnosis.cold_open` only. The rest of the diagnosis (the entry point, the first-choice spec, the emotional promise, the start event) stays as it was.

Existing games are not reset. The next new start of that story reads the stored cold open.

## Structure

| Piece | Role |
|---|---|
| `app/Console/Commands/RepairColdOpenCommand.php` | `stories:repair-cold-open`. Loads one story, prints, and optionally stores. |
| `app/Ai/Adaptation/ColdOpenRepairContext.php` | Pulls protagonist, voice, diagnosis facts, first choice, and the source window. |
| `app/Ai/Agents/Adaptation/ColdOpenRepairAgent.php` | Sends Tom's contract as the system prompt, with that story's slots filled. |
| `resources/views/ai/agents/adaptation/cold-open-repair/system-prompt.blade.php` | Tom's contract. |
| `resources/views/ai/agents/adaptation/cold-open-repair/prompt.blade.php` | The pulled record (facts, source, previous opening). |
| `app/Ai/Adaptation/ColdOpenRepairValidator.php` | Rejects an empty opening, a marker, a missing name, a third-person start, an over-long opening, or a closing question. |
| `tests/Unit/ColdOpenRepairPromptTest.php` | A title's facts land in its own prompt, the system prompt names no story, the approved Peter Pan outcome still passes the checks, and a closing question fails. |
| `tests/Feature/RepairColdOpenCommandTest.php` | Dry run does not write. `--apply` stores only `cold_open`. A question is not stored. |

`EntryPointDiagnosisAgent` and `resources/views/ai/agents/adaptation/entry-point-diagnosis/system-prompt.blade.php` are untouched. New boils still use the old opening instructions until this repair is greenlit and that agent is switched on purpose.

## Laravel Cloud

In the environment's command box, Cloud runs Artisan for you. Do not prefix `php artisan` or `vendor/bin/sail`.

Read what would be sent, without calling the model:

```
stories:repair-cold-open peter-pan --dry-run
```

Print the full prompt as well:

```
stories:repair-cold-open peter-pan --dry-run --show-prompt
```

Generate a candidate and print it. This calls the model. It does not save.

```
stories:repair-cold-open peter-pan
```

Store a candidate that passes the checks:

```
stories:repair-cold-open peter-pan --apply
```

Another title is the same command with that story's slug. Session 1 is the default.
