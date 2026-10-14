# Cold open repair

14 October 2026. How the repair works, what it reads, and what it writes.

The repair rewrites a story's stored cold opens with Tom's Cold Open Contract, held to the same style and ban rules as the optimized Boiler and the live narrator. One contract serves every title and every session. It does not change the entry-point pipeline agent (`EntryPointDiagnosisAgent`).

## Sessions

Each session's `entry_point_diagnosis.cold_open` is the prose the game delivers word for word at the start of that session. The repair rewrites that field only. It does not rebuild the cached runtime narrator prompt. Live delivery already injects `cold_open` from the diagnosis on the session-start turn.

- `--session=N` repairs one session. Session 1 is the default.
- `--all` repairs every session of the story, in order.

Session 1 is an introduction. Session 2 and later are a re-entry: the model also reads the previous session's authored close and must start where that close leaves the protagonist. Any change in the gap (a capture, a separation, a journey) is stated plainly. The narration stays in second person, present tense, as the protagonist, in every session.

## What the model is told

The system prompt is Tom's contract (`LoreSpinner_Cold_Open_Repair_Spec.md`, Appendix A), with the story's values filled into his slots:

| Tom's slot | Filled from |
|---|---|
| `PROTAGONIST_NAME` | Launch catalog, then the story session map, then the IP audit. |
| `VOICE_ANCHOR` | The full `story_adaptations.voice_profile`: Voice DNA, Master Rule 1 IP-specific bans, Voice Anchor, Anchor Card. |
| `FIRST_CHOICE_SPEC` | That session's `entry_point_diagnosis.first_choice_spec`. |

Tom's order of beats is kept: where, who, what is happening, the decision. It outranks the Boiler's body-first rule.

Added so the repair matches the optimized Boiler:

- **Style and bans.** The user message carries that session's runtime narrator rules (Sections 4, 14 and 18 of its runtime prompt), the same slice `stories:repair-session` uses. Master Rule 1 in them binds the cold open. The system prompt also lists the universal bans from `BannedProseValidator` and the runtime self-check. Section 14's turn-ending rules belong to gameplay turns, so the cold open still ends as Tom's contract says.
- **One sequence with the scene cards.** The user message carries the session's first finalized scene card. The cold open stops where that card begins and never narrates what it plays, so the player does not live it twice. This matches the latest Boiler play, where the opening ends as Wendy crosses the floor and the cards play Peter's name, the shadow, and the invitation.
- **Worked example.** Tom's Peter Pan screen stays as a craft example for every title, Peter Pan included. Its final question is removed. Its em dashes, the "not the way… but the way…" contrast, the "as though you ought to have known" gloss, and the repeated "quiet" are brought into line with the bans. Where it covers events the first card plays, the card rule wins.

There is no longer a Peter Pan verbatim bypass. Every session goes through the model and the same gates.

## How a run works

1. Resolve the story by slug or numeric id. It must have an adaptation.
2. Select the sessions: one with `--session`, every one with `--all`.
3. For each session, load the diagnosis, voice profile, runtime narrator rules, first scene card, previous session close, and source events.
4. Stop that session if the protagonist name, the voice anchor, or the situation is missing. Nothing is invented to fill a gap.
5. `--dry-run` prints what was loaded and does not call the model or write. `--show-prompt` with it prints both prompts.
6. Otherwise `ColdOpenRepairAgent` (`claude-opus-5-5`) writes a candidate.
7. Banned punctuation (em and en dashes, double hyphens, semicolons, ellipses) is rewritten into commas and full stops, the same pass the Boiler runs before storing.
8. The candidate is checked by `ColdOpenRepairValidator` (empty, `===` marker, over 350 words, no "you" at the start, protagonist not named in the first 80 words, closing question) and by the `BannedProseValidator` scan (banned words, phrase-tells, three or more "like a" similes).
9. A rejected candidate is sent back with the problems listed, up to three attempts. If it still fails, it is printed and not saved.
10. A passing candidate is printed and saved only with `--apply`. `--apply` writes `entry_point_diagnosis.cold_open` and nothing else.

Existing games are not reset. The next new start of that story reads the stored cold open.

## Structure

| Piece | Role |
|---|---|
| `app/Console/Commands/RepairColdOpenCommand.php` | `stories:repair-cold-open`. One session or `--all`, punctuation pass, ban scan, retries, optional store. |
| `app/Ai/Adaptation/ColdOpenRepairContext.php` | Builds the prompt data: protagonist, voice profile, narrator rules, universal bans, first scene card, diagnosis, previous close, source window. |
| `app/Ai/Adaptation/StoryRepairContext.php` | Reused for the runtime narrator rules and the finalized scene cards. |
| `app/Ai/Agents/Adaptation/ColdOpenRepairAgent.php` | Sends Tom's contract as the system prompt. Model: `claude-opus-5-5`. |
| `resources/views/ai/agents/adaptation/cold-open-repair/system-prompt.blade.php` | Tom's contract, later-session rules, the scene-card rule, style and bans, and the worked example. |
| `resources/views/ai/agents/adaptation/cold-open-repair/prompt.blade.php` | The diagnosis, previous close, first scene card, narrator rules, and source events. |
| `resources/views/ai/agents/adaptation/cold-open-repair/examples/peter-pan.blade.php` | Tom's worked example, ban-compliant. |
| `app/Ai/Adaptation/ColdOpenRepairValidator.php` | Structural checks, shared with the entry-point validator. |
| `app/Ai/Adaptation/BannedProseValidator.php` | Punctuation pass and ban scan, shared with the Boiler. |

## Laravel Cloud

The Cloud command box runs bash. Prefix Artisan. Do not use `vendor/bin/sail`.

Check what would be sent, for every session, without calling the model:

```
php artisan stories:repair-cold-open peter-pan --all --dry-run
```

Generate and print a candidate for every session. This calls the model once to three times per session. It does not save.

```
php artisan stories:repair-cold-open peter-pan --all
```

Store the candidates that pass:

```
php artisan stories:repair-cold-open peter-pan --all --apply
```

One session only: replace `--all` with `--session=2`. Another title: replace `peter-pan` with its slug.
