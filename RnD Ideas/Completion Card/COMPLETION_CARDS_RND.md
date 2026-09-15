# Completion Cards — Session End & Story End

**Status:** RnD / creative + product design brief (source of truth for ideation)  
**Audience:** Product, design, narrative, engineering, growth  
**Purpose:** Map what completion cards *are*, what runtime already quietly collects, what is *shown* today, and a creative menu of what we can *compile* so players share, explore more, stay in the story, and stay in the app.  
**Worked example:** Sherlock Holmes — *The Adventure of the Speckled Band* (2 sessions)  
**Related:** Boiler Session End Receipt (§23), Social Echo Layer (Deliverable 9), Boiler v1 excludes session receipts from the Boiler contract (product can still design ahead)

---

## How to use this doc

This is not a frozen UI spec. It is a **creative kitchen**.

- Every signal below is real or near-real in our stack.  
- Every module is optional. Teams prune by goal: **share**, **return tomorrow**, **start another story**, **replay**, **feel witnessed**.  
- Prefer compiling *fewer, sharper* lines from rich data over dumping the whole personality stack on screen.  
- When in doubt: story voice > game chrome; invitation > guilt; real data > invented stats.

**North star outcomes**

| Outcome | What the card must do |
|---------|------------------------|
| **Share** | Give one line a friend would ask about |
| **Explore more** | Make the unread next session (or another title) feel personally unfinished |
| **Stay in story** | Echo *their* authorship — not a generic cliffhanger |
| **Stay in app** | Soft paths: continue, return later, library, replay, share — never a dead end |

---

## 0. One-sentence brief

After narration stops, the player should not see an administrative “Session complete.” screen — they should receive a **story-native ceremony** compiled from the soft personality and literary-memory system we already run under the hood: proof their choices mattered, a name for who they became, and either an **open loop they authored** (session end) or a **settlement of the whole investment** (story end).

---

## 1. Why this exists

Players invest 20–90+ minutes of reading, choosing, and caring. Today that investment ends with:

- Mid-session: `"Session complete."` + Continue / End story button  
- Full story: a short cinematic outro (“Every story leaves something behind.”) + poster  

That is **administrative completion**, not **narrative receipt**.

What we already have (and underuse for the player):

1. **Authored close** — `session_close_design` (resolution, hook, session-end choice, final line, stickiness)  
2. **Hidden personality stack** — alignment scaffold → story-native tilt; `player_style`; emotional ledger; symbolic memory; defining choice line  
3. **Boiler player-critique footnotes** — per-turn hunches about what would land hardest on *this* player next (never shown today; gold if carefully rewritten for ceremony)  
4. **World literary memory** — relationships, promises, knowledge, objects, flags  

The missing layer is the **player-facing compile**: turn those signals into dopamine, curiosity, shareability, and return.

### Product doctrine (adaptation master context)

> THE SESSION-END CHOICE IS THE RETENTION MECHANISM.  
> The session ends on the user's own unresolved decision, not a plot cliffhanger.

Completion cards must honor that: the UI mirrors **authorship**, not spoiler cliffhangers.

---

## 2. Two different jobs (do not collapse)

| Moment | Job | Primary feeling | Failure mode |
|--------|-----|-----------------|--------------|
| **Session end** (not last session) | Make return feel inevitable | Anticipation + ownership | Loading screen, or a cheap cliffhanger they didn’t choose |
| **Story end** (last session / full completion) | Make long investment feel paid | Closure + identity + pride | Abrupt, generic, or spoils replay |

**Session end dopamine** = *I finished a chapter of my own making, and I left a promise unfinished.*  
**Story end dopamine** = *I finished the whole investment, and the ending is recognizably mine.*

Both should leave a **breadcrumb back into the app** — continue, another title, share, or “your case file” worth revisiting.

---

## 3. What is implemented right now

### 3.1 Frontend (live)

| Surface | File | Behavior |
|---------|------|----------|
| Session complete block | `resources/js/pages/User/Games/Show.vue` | Plain “Session complete.” + subtitle + Continue / End story |
| Session progress chip | same + journal | “✓ Session complete” |
| Story outro | `GameCinematicOutro.vue` | Phrase → showcard/poster → fade; generic copy |
| Chaos Mode banner | `ChaosMode.vue` | Simple “Session Complete” banner (dev/sandbox) |

No receipt, no identity label, no “world remembers,” no share card, no ledger surface, no player-style surface.

### 3.2 Backend / analytics (live)

| Piece | Status |
|-------|--------|
| `games.current_session_complete` | Live flag for UI |
| `games.completed_at` | Mutable story-complete state |
| `game_session_completions` | Analytics timing only — **no choice / personality snapshot** |
| `game_completions` | Analytics timing only |
| Flash `story_complete` | Triggers cinematic outro |

### 3.3 Authored close (exists, narrated, not carded)

`session_adaptations.session_close_design`:

```text
resolution_prose, hook_transition
session_end_choice.{choice_question, option_a/b/c + next_session_opens, final_line}
closing_moment_key / next_bridge_guidance   (Boiler)
stickiness_audit
```

Injected via `[SESSION_CLOSE_INJECTION_POINT]` — the player *hears* the close in narration; they never *receive* it as a keepsake.

### 3.4 Explicit Boiler v1 exclusion

Boiler docs mark “session receipts / advanced replay analytics” as **not in Boiler v1**. That means the *Boiler adaptation contract* doesn’t ship receipts yet — not that the product can’t design or ship a ceremony layer on top of existing runtime state.

---

## 4. What information we can pull (corrected inventory)

Critical runtime read: we collect a **soft personality / literary-memory system** every turn. Most of it is hidden from the player by design during play — which makes **post-episode reveal** feel like a gift, not a HUD.

`GameResource` does **not** currently expose this stack to the frontend. Assembler builds a deliberate `completion_receipt` payload at complete time.

### 4.1 On `games` — identity & memory (high value)

| Field | What it is | Creative card uses |
|-------|------------|--------------------|
| `defining_choice_id` / `defining_path_label` / `defining_choice_line` | Latest resolved branching identity peak (A/B/C + provocative line) | Share line, “your record” peak, commitment echo |
| `alignment_scaffold` | Hidden `{chaotic, lawful, neutral}` tallies | Map to story-native label for “You are becoming / You became” |
| `symbolic_memory` | Append-only interior weather paragraphs | Atmospheric identity blurb; “weather of the soul” module |
| `world_state` | Full literary memory blob (see 4.2) | Compile “world remembers,” relationships, promises |
| `moment_footnote` | **Latest** Boiler critique blob `{moment_key, text, detour_beats_used, …}` | Optional “the story was reading you” (must rewrite — never dump raw) |
| `is_climactic_choice` | Triggers Tier 3 state load next turn | Assembler hint that moral weight just landed |
| `current_session_number` / `current_session_complete` / `completed_at` | Gates | Mode: session vs story |
| Playable moment pointers | Last room context | Title of closing room; “you left at…” |

**Caveat:** `defining_*` is the **latest** defining branch, not a full trail. Full trail needs prompt/choice reconstruction or a future snapshot table.

### 4.2 Inside `world_state` — the personality matrix we already run

From `ChaosNarrationSchema` `state_delta` → merge into `games.world_state`:

| Key | Persist rule | Creative gold |
|-----|--------------|---------------|
| `location` | replace when set | Closing place name on the seal |
| `conditions` / `items` | replace lists | “Still carrying…” module |
| `object_states` | carry + patch | Touched objects as evidence (“the clamped bed still remembers”) |
| `relationship_updates` | carry + patch | NPC memory (“Holmes trusts your caution”) |
| `world_flags` | carry + patch | Story-state seals |
| `knowledge` | append | Facts they earned — competence receipts |
| `notes` | append | Bargains, releases, emergent commitments |
| **`player_style`** | **append behavioural patterns** | **Closest thing to a live personality matrix** — stance, tone of play |
| **`unresolved_promises`** | replace when changed | Open loops for return / share curiosity |
| **`emotional_ledger`** | append `{category, entry}` | **Player historical archive** (Task 6D: defining morals, NPCs helped/harmed, promises, secrets…) — best source for “Your record” |

Tiered narrator injection today:

- **Tier 1 (always):** location, items, conditions, knowledge, notes, **player style**  
- **Tier 2:** scene-connected objects/relationships/flags, unresolved promises, **recent ledger**  
- **Tier 3 (climactic / persistent):** full objects, relationships, flags, **full emotional ledger**

For cards: prefer **ledger + player_style + relationships + unresolved_promises**, then knowledge/objects as garnish.

### 4.3 On `prompts` (Boiler turns) — play-shape telemetry

Via `PlayableMomentResolver` commit:

| Field | Meaning | Creative uses |
|-------|---------|---------------|
| `moment_footnote` | ≤30-word hunch: what would land hardest on this player next; separates *did* vs *wanted* | Rewrite into “How the story read you”; never show raw clinical tone |
| `route_relation` | `advanced` / `deepened` / `detoured` / `reconverged` | Play-shape seal: “You deepened more than you rushed” / “You wandered and came back” |
| `impact_scope` | `expressive` / `local` / `persistent` | Count of persistent turns = “the world actually changed N times” |
| `moment_key` / `exit_condition_met` / `moment_result` | Room progress | Journey map crumbs; rooms lingered in |
| Player `prompt` text + `response` | What they typed / heard | Optional quote chip (with care) |

**Caveat:** Footnotes are **author-to-author**. Raw dump would feel surveilled. Creative rewrite or selective paraphrase only.

### 4.4 Authored adaptation (compile partners)

| Source | Creative uses |
|--------|---------------|
| `session_close_design` | Final line, commitment options, `next_session_opens`, resolution beats |
| `session_choice_design` | Branching / emotional / **posture shifts** (`stance_revealed`, style updates) — texture for becoming |
| `story_session_map.alignment_labels` | Story-native identity names + behavioural shape + voice signature |
| Consequence mapping | Branch effects → “world remembers” candidates |
| Scene cards / boiler cut | `playable_promise`, moment titles, bridges |
| Story | Title, cover, `outro_poster`, session count |

### 4.5 Analytics tables (thin)

`game_session_completions` / `game_completions` = **when**, not **what**.  
For reliable share/replay cards, snapshot a `completion_receipt` JSON at complete time (or assemble live from game + adaptation + recent prompts).

### 4.6 Chaos Mode only

`session_memory_update` accumulates on chaos sessions. Main `games` path leans on **`symbolic_memory`** instead. Don’t assume session_memory exists for production games.

### 4.7 Not ready / do not fake

| Desire | Status |
|--------|--------|
| “62% of players chose X” | Social Echo spec; no card-ready aggregates — **never invent** |
| Hover / “almost chose” | Spec only |
| Share-image CDN | Spec / design |
| Durable multi-session identity graph | Can approximate from ledger + alignment; no first-class history table yet |

---

## 5. Creative modules (compile menu)

Treat these as **Lego**. A shippable card picks 4–7. A maximal story-end experience can layer more. All can serve share / explore / stay.

### 5.1 Core ceremony modules

| Module | Feels like | Best sources | Best for |
|--------|------------|--------------|----------|
| **Final line** | Peak-end seal | `session_end_choice.final_line` | Everything |
| **The world remembers** | Competence | `emotional_ledger` + relationships + knowledge + consequence echoes | Share + stay |
| **You are becoming / You became** | Identity | `alignment_labels` × scaffold + `player_style` distill | Stay + share |
| **Your commitment** | Authorship (session) | Chosen C4 option | Return tomorrow |
| **What waits** | Open loop | `next_session_opens` + unresolved_promises | Explore next session |
| **What settled** | Closure (story) | Condensed `resolution_prose` | Put the book down |
| **Your record** | Ledger pride | `emotional_ledger` highlights + defining line | Share + replay |
| **What the story leaves behind** | Aftertaste | Sensory image + moral residue | Cinematic handoff |

### 5.2 Enrichment modules (optional — high creativity)

| Module | Feels like | Best sources | Caution |
|--------|------------|--------------|---------|
| **Still carrying** | Embodiment | `items` / `conditions` | Keep to 1–2; poetic not inventory UI |
| **Who noticed you** | Relationship dopamine | `relationship_updates` | Story voice; no meters |
| **Promises still open** | Soft cliff *they* made | `unresolved_promises` | Session end gold; close or transform at story end |
| **How you moved through rooms** | Play-shape identity | Counts of `route_relation` / `impact_scope` | Easy to feel gamey — phrase as character, not analytics |
| **How the story read you** | Intimate mirror | Rewrite of last / best `moment_footnote`s | **Must transform**; never raw; never diagnostic |
| **Interior weather** | Lyric identity | Last 1–2 `symbolic_memory` sentences | Short |
| **Evidence board** | Detective delight (Sherlock-native) | `object_states` + `knowledge` | IP-flavored module — remix per story |
| **Defining line (share face)** | Social currency | `defining_choice_line` or pre-authored Task 8 lines | Spoiler-safe |
| **Spectrum / seal** | Visual identity | Alignment → story-native gradient | Post-episode only; no RPG words |
| **Comparison hook** | Curiosity about others | Real aggregates only | Park until real data |
| **Case file / keep** | Collection instinct | Snapshot receipt saved to profile | Stay in app long-term |
| **Door to another story** | Explore catalog | Soft recommend by alignment/style affinity | Stay in app |

### 5.3 Psychological levers (full menu)

| Lever | Satisfies | Module |
|-------|-----------|--------|
| Completion | Unit closed | Seal |
| Competence | Choices mattered | World remembers / record / evidence |
| Identity | Self-concept update | Becoming / became / spectrum |
| Authorship | Agency | Commitment / defining line |
| Anticipation | Open loop | What waits / open promises |
| Closure | Investment paid | What settled / leaves behind |
| Social currency | Conversation | Share face |
| Intimacy | “It saw me” | How the story read you (careful) |
| Embodiment | Body in the world | Still carrying |
| Belonging to NPCs | Relational | Who noticed you |
| Collection | Keep / revisit | Case file |
| Curiosity about self | Replay | Alternate paths hinted without spoiling |
| Curiosity about others | Growth | Real comparison later |
| Aesthetic peak | Peak-end | Final line + poster |
| Relief permission | Safe stop | Return later / Library |

### 5.4 Peak-end rule

Narration owns the peak. The card **is** the end. If the end is gray “Session complete.”, the session’s memory collapses.

### 5.5 What not to show

- Fake percentages  
- RPG jargon to the player (chaotic/lawful/XP)  
- Spoiler dumps  
- Empty praise  
- Long recaps of what they just lived  
- Shame CTAs  
- Mid-play alignment UI  
- Raw footnotes (“you seek validation from father figures”) — **rewrite or omit**  
- Full ledger dump — curate 3 bullets max  

---

## 6. Approaches the team can prune

### Approach A — Minimal Receipt (fastest)

Final line + 3 remembers (ledger/knowledge) + Continue.  
**Outcomes:** competence, peak-end.

### Approach B — Identity Ceremony (recommended core)

A + becoming/became + commitment + waits/settled.  
Pull becoming from **alignment label × player_style**, remembers from **emotional_ledger**.  
**Outcomes:** share seed, return, stay in story.

### Approach C — Social Echo (Deliverable 9)

B + spectrum + defining share line + real comparison + share image.  
**Outcomes:** virality. Park fake stats.

### Approach D — Cinematic aftertaste

Enhanced outro + poster after settlement.  
**Outcomes:** aesthetic peak. Insufficient alone.

### Approach E — Full Case File (story end max)

B/C + multi-module: record, relationships, evidence, play-shape, doors to other stories, saved case file.  
**Outcomes:** collection, explore catalog, long retention.

### Approach F — Intimate Mirror (advanced, opt-in tone)

Selective footnote rewrite + symbolic weather + “who noticed you.”  
**Outcomes:** “it saw me” intimacy. Highest trust bar. Easy to creep if clumsy.

### Recommended creative path

```text
Default ship: B (Identity Ceremony), data-fed from ledger + style + alignment + close design
Story end: B + selected enrichment (record, leaves behind) → D cinematic
Share: defining_choice_line / Task 8 line as face (spoiler-safe)
Explore/stay: What waits + optional “door to another story” + case file keep
Park: fake %, raw footnotes, full dump UI
Experiment: F Intimate Mirror on canary IPs only
```

All modules remain available for creative remix per IP (detective evidence board for Sherlock; glitch spectrum for Anima; etc.).

---

## 7. Feasibility matrix

| Feature | Now? | Notes |
|---------|------|-------|
| `final_line` from close design | **High** | |
| Commitment / `next_session_opens` | **High** | Map defining path → option |
| Remembers from `emotional_ledger` | **High** | Curate last / highest-weight entries |
| Remembers from relationships / knowledge | **High** | |
| Identity from alignment_labels × scaffold | **High** | Already computed in `renderAlignmentTilt` logic |
| Texture from `player_style` | **High** | Distill 1 phrase from append list |
| Unresolved promises → What waits garnish | **High** | |
| Symbolic memory blurb | **High** | Trim |
| Play-shape from route/impact counts | **Medium** | Aggregate prompts in session |
| Footnote → “story read you” | **Medium** | Needs rewrite rules / editorial filter |
| Defining line share face | **High** | If model filled it; else authored Task 8 |
| Still carrying items/conditions | **High** | |
| Object evidence board | **Medium** | Best with IP-flavored copy |
| Real % comparison | **Low** | Until aggregates |
| Share image CDN | **Low–Medium** | Design + render |
| Fake % | **Forbidden** | |

### Assembler sketch

```text
CompletionReceiptAssembler(game, sessionAdaptation, recentPrompts):
  mode = story if last session completing else session
  pull: close_design, world_state, alignment_scaffold, alignment_labels,
        defining_*, symbolic_memory, prompt footnotes/routes/impacts
  compile modules (feature-flagged / IP template)
  never expose raw chaotic/lawful words or raw footnotes
  → completion_receipt on GameResource only when complete
```

Fallback (thin adaptation):

```text
final_line ← last narration beat
remembers ← defining_choice_line and/or top knowledge notes
becoming ← omit or soft symbolic_memory line
```

---

## 8. Content UX — see / read / feel

### 8.1 Session end (retention ceremony)

1. Breath  
2. Quiet seal  
3. World remembers (≤3)  
4. Becoming + commitment  
5. What waits (+ optional open promise)  
6. CTAs: Continue · Return later · quiet share  

Optional spice: still carrying · who noticed you · play-shape one-liner.

**Feel:** *I earned this pause. The night waits because I said I’d go.*

### 8.2 Story end (settlement ceremony)

1. Longer stillness  
2. What settled  
3. Your record (ledger-fed)  
4. You became  
5. Final line  
6. Leaves behind  
7. CTAs: Share · Begin again · Library · Keep case file  
8. Cinematic aftertaste  

Optional spice: evidence board · spectrum seal · door to another story.

**Feel:** *That was mine. I can put it down. I might tell someone — or open another door.*

### 8.3 Copy principles

- Second person, story voice  
- Named world > abstract virtues  
- One charged line > paragraph  
- Invitation > guilt  
- Spoiler-safe share  
- Curate, don’t dump  
- IP-native modules welcome (this doc wants creativity per title)

---

## 9. Suggested templates (canonical + modular)

### 9.1 Session-end (core)

```markdown
# {Story Title}
### Session {N} complete · {short session promise}

---

**{final_line}**

---

### The world remembers
- {ledger_or_relationship_or_knowledge_1}
- {…_2}
- {…_3}

### You are becoming
**{story_native_identity}**
{optional one line from player_style distill}

### Your commitment
{chosen session_end option}

### What waits
{next_session_opens}
{optional: one unresolved_promise}

---

[ Continue ]  [ Return later ]

Share (quiet): "{defining_or_spoiler_safe_line}"
```

### 9.2 Story-end (core)

```markdown
# {Story Title}
### Story complete

---

**{final_line}**

---

### What settled
- {…}

### Your record
- {emotional_ledger / defining acts}

### You became
**{final identity}**

### What the story leaves behind
{sensory + moral aftertaste}

---

[ Share your ending ]  [ Begin again ]  [ Library ]
```

### 9.3 Optional inserts (creative kit)

```markdown
### Still carrying
{item or condition, poetic}

### Who noticed you
{relationship_updates line}

### How you moved
You deepened more than you advanced. / You detoured — and the spine waited.

### How the story read you
{rewritten footnote — intimate, non-clinical}

### Evidence
- {object_state}
- {knowledge}

### Open promises
- {unresolved_promises}

### Another door
If this ending sits with you, {soft recommend another title by affinity}.
```

### 9.4 Field → source mapping

| Template field | Preferred source | Fallback |
|----------------|------------------|----------|
| `final_line` | close `final_line` | Last narration beat |
| `remembers[]` | `emotional_ledger` + relationships + knowledge | consequence echoes; defining line |
| `identity` | alignment_labels × scaffold | player_style distill; omit |
| `style_line` | `player_style` (1 phrase) | omit |
| `commitment` | chosen C4 text | defining paraphrase |
| `what waits` | `next_session_opens` + promises | hook_transition |
| `what settled` | resolution_prose bullets | editorial |
| `record[]` | ledger across session/arc | defining + knowledge |
| `share_line` | defining_choice_line / Task 8 | curated remembers line |
| `read_you` | rewritten footnotes | omit |
| `play_shape` | route/impact aggregates | omit |
| `carrying` | items/conditions | omit |
| `noticed` | relationship_updates | omit |
| `evidence` | object_states + knowledge | omit |

---

## 10. Perspectives for pruning

| Perspective | Keep | Soften / cut |
|-------------|------|--------------|
| **Retention** | Commitment, What waits, Continue | Long settlement mid-session |
| **Closure** | Settled, Became, Final line | Open loops on story end |
| **Share / growth** | Defining line, spectrum later | Spoilers; ugly chrome |
| **Explore catalog** | Another door, case file | Hard sell |
| **Intimacy** | Rewritten read-you, noticed you | Raw footnotes |
| **Self-determination** | Authorship echo | Shame CTAs; fake stats |
| **Peak-end** | Final line hero; short card | Ledger walls |
| **Trust** | Real data; post-episode identity only | Mid-play alignment; invented % |
| **Engineering MVP** | final_line + ledger×3 + commitment + CTA | Spectrum, CDN, footnote rewrite |
| **Literary / IP** | Voice-native modules | Gamey analytics phrasing |
| **Collection** | Case file keep | Clutter |

---

## 11. Open questions

1. Card after typewriter finishes, or on `session_complete` immediately?  
2. “Return later” = dismiss, or affirmation + home?  
3. Settlement then cinematic, or reverse? (**Recommend:** settlement → cinematic)  
4. Identity authorship: pipeline labels + light template (**recommend**) vs live LLM compile?  
5. Footnote rewrite: human editorial rules, classifier, or omit until safe?  
6. Non-Boiler stories: same ceremony with fallbacks?  
7. Save `completion_receipt` snapshots for replay / case file / share later?

---

## 12. Recommended decision (baseline, not a ceiling)

**Ship baseline:** Approach **B — Identity Ceremony**, fed by:

- close design (final line, commitment, waits)  
- `emotional_ledger` + relationships (remembers / record)  
- alignment_labels × scaffold + `player_style` (becoming)  
- defining_choice_line (share face)

**Story end:** B + leaves behind → cinematic **D**.  

**Creative license:** IP teams may add enrichment modules from §5.2 freely; this doc exists so those experiments stay grounded in **real pullable signals**.

**Do not ship yet:** fake comparisons, raw footnotes, full-dump personality HUD.

**Success (qualitative):**

- Can quote their final line  
- Can name one thing the world remembers *they* did  
- Mid-session unfinished **because of their choice**  
- Story end feels witnessed  
- At least one path makes them share, continue, open another title, or keep a case file  

---

# Appendix A — Speckled Band worked examples

Grounded in canary `session_close_design` from  
`Adaptation layer/Boiler Adaptation/Boiled Speckeld band /full-boiler-output.json`.

Assumption: player took **Option B** on each session-end choice.  
Examples show **core B** plus *italicized optional modules* the team can keep or prune — illustrating how runtime personality data could flavor the same ceremony.

---

## A1. Session end — Session 1 of 2

```markdown
# The Adventure of the Speckled Band
### Session 1 complete · Inquiry → Vigil

---

**The light waits; so, now, does Holmes.**

---

### The world remembers
- You sat the case into one dreadful line: ventilator, dummy rope, clamped bed, milk, lash.
- Helen’s middle window is lit. The signal is real.
- Roylott has gone into the dark of Stoke Moran — and the night is no longer theory.

### You are becoming
**The partner who will enter the room.**
*Optional (player_style): You test before you trust the dark.*

### Your commitment
You told Holmes you would go — but only after testing the signal once more in your mind.
Courage without precaution would merely serve Roylott’s purpose.

### What waits
The lawn. The loaded pistol. Absolute darkness beside a sleeping trap.
Session 2 opens on the vigil itself — in the spirit you chose.
*Optional (unresolved_promise): Helen’s safety is not yet paid — only promised by your presence.*

*Optional — Who noticed you:* Holmes has stopped explaining past your caution; he is waiting on it.
*Optional — Still carrying:* The revolver’s weight in your coat.
*Optional — How you moved:* You deepened the room more than you rushed the night.

---

[ Continue to the vigil ]     [ Return later ]

Quiet share: “I know how the room is meant to kill. I haven’t entered it yet.”
```

| Block | Source |
|-------|--------|
| Final line / commitment / waits | S1 `session_close_design` Option B |
| Remembers | resolution + knowledge/objects |
| Becoming | editorial × alignment + style |
| Optional modules | relationships, items, promises, route_relation aggregates, footnote rewrite (not shown raw) |

**Psych job:** anticipation they authored. No snake. No strike spoilers.

---

## A2. Story completion — Session 2 / full story

```markdown
# The Adventure of the Speckled Band
### Story complete

---

**The case is solved; its meaning, and its measure, remain in your hands.**

---

### What settled
- Helen is safe.
- The method is known: the room confessed under lamp-light.
- Roylott’s design failed upon himself.

For a moment, fear ends as a clearing of the air — not triumph, but truth.

### Your record
- You held the silent vigil.
- You were present when Holmes’s strike turned the killer back.
- You chose a middle course: enough truth for a workable verdict —
  the strangest particulars kept from becoming Helen’s lifelong burden.
*Optional (emotional_ledger categories): secrets discovered · promises kept · harm refused publicity*

### You became
**The witness who decides what the world may bear.**
*Optional (player_style + alignment): Measured. Loyal to the living more than to the spectacle.*

### What the story leaves behind
A lit window that was once a summons.
A room that will never look innocent again.
A sentence you alone decided how to write.

*Optional — Evidence:*
- Iron safe. Saucer of milk. Dummy rope — now honest.
*Optional — How the story read you (rewritten):* You wanted the truth to land softly enough that Helen could still walk in daylight.
*Optional — Another door:* If moral authorship sat well with you, another case is waiting in the library.

---

[ Share your ending ]     [ Begin again ]     [ Library ]     [ Keep case file ]

Share line: “I know how it was done. I chose how much of it the world gets to know.”
```

| Block | Source |
|-------|--------|
| Final line / settled | S2 close design |
| Record | vigil promise + C4 Option B + ledger |
| Became | alignment × style × moral choice |
| Leaves behind / share | card layer + defining line |
| Optional | objects, rewritten footnotes, catalog door, case file |

**Aftertaste:** `GameCinematicOutro` + `outro_poster` after dismiss.  
**Psych job:** closure + moral authorship + reasons to share / replay / browse.

---
