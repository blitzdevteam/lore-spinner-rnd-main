@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 2: Story Session Map'])

=== PHASE 2: STORY SESSION MAP ===

You have the full structural extraction of the source IP. Your job is to convert these canonical chunks into a story-wide session roadmap before any single session is designed in detail.

TASK 1: SESSION COUNT AND ALLOCATION

Using the estimated session count from Format Detection as a starting point, confirm or revise the session count based on the actual event structure. Then allocate events to sessions.

Rules:
* Each session covers a natural dramatic arc (rising tension, climax/choice, resolution)
* Session boundaries should fall at natural breaks: chapter boundaries, location shifts, or time jumps
* Most sessions should contain 5–20 events. A session may exceed 20 only when preserving a coherent opening arc or natural dramatic unit requires it. Do not cut off the authored opening context merely to satisfy the event-count target.
* Session 1 must preserve the story's playable opening context. If Session 1 covers the source beginning, its `event_range` should include the opening beats that establish the first playable situation. Do not skip the authored opening merely to chase a louder or more spectacular later beat.
* "Highest-energy material" means the strongest playable pressure within the correct opening arc — not automatically the loudest later event.
* A later Session 1 start is allowed only when the adaptation is intentionally beginning in medias res, or when the source opening is genuinely non-playable context: credits, pure lore dump, inert throat-clearing, or material with no embodied scene, pressure, threshold, object/question/problem, or meaningful first choice.
* If `prefer_literal_opening` is true (see user prompt STORY ENTRY OVERRIDE), Session 1 must include the literal source opening beats in its `event_range` unless those beats were removed by IP trimming or are not present in the extracted events.

TASK 2: ARC PROGRESSION

For each session, name the primary dramatic question, the emotional register shift from the previous session, and the key transition moment.

TASK 3: MAJOR BRANCH OPPORTUNITIES

Identify the 2-3 strongest branching choice opportunities per session. Reference specific event positions. For each, name the event position and title, what dimension it would track, and whether it has natural downstream payoff in a later session.

TASK 4: CROSS-SESSION PAYOFF PLAN

Map the highest-value branching choices from early sessions to their payoff moments in later sessions.

TASK 5: BRANCH DIMENSION DEFINITIONS (STORY-LEVEL)

Using the branch opportunities identified in Task 3, define the core branch dimensions of the story. A branch dimension is a reusable narrative axis that multiple choices across sessions may reference and evolve.

Rules:
* Each dimension is a tension or axis, not a single trait (e.g. trust_vs_caution, not just trust)
* A story should have 3-6 core dimensions
* Each dimension must connect to at least one branch opportunity from Task 3
* Dimensions defined here are canonical — Phase 5 must reference them when designing branching choices

{{-- Tasks 6-9 below are verbatim from Deliverable 2 (Phase 2 — Story Session Map Phase: Persistent World State Schema).
     Only mechanical adaptations: removed the "[PASTE PHASE 1 OUTPUT]" / "[PASTE FORMAT DETECTION OUTPUT]" placeholders
     (those values are already passed via the master-context include and the prompt.blade.php). --}}

TASK 6: PERSISTENT STATE SCHEMA EXTRACTION

The Lorespinner runtime will track and update this state through every player turn. Each piece of state must be specific, measurable (qualitatively or quantitatively), and narratively relevant to this IP.

6A — OBJECTS / ARTIFACTS / INVENTORY ITEMS

Identify EVERY object that:
* Has narrative significance (mentioned more than once OR central to a scene)
* Could change ownership during play
* Could be modified, broken, used up, or transformed
* Carries symbolic weight in the story

For each, return: name, type, initial state, possible state changes (3-5 examples), tracked attributes (qualitative — e.g. "condition", "ownership", "knowledge of its existence"; NOT numeric scores), persistence requirement (does this carry across all sessions, just within a session, or single-scene only?), reactivity hooks (situations in which NPCs would notice or react to this object's state).

6B — NPC RELATIONSHIP STATES

Identify EVERY named NPC who could meaningfully react differently based on player history.

For each, return:
- Name
- Initial disposition toward player (one sentence describing baseline trust, suspicion, neutrality, or hostility)
- Tracked relationship attributes (qualitative descriptors, NOT numeric scores):
  - Trust level: LOW / MEDIUM / HIGH (with one sentence describing what raises and lowers it)
  - Specific knowledge of player actions (list which player actions this NPC would know about and how they learned)
  - Personal stakes (what does this NPC want from or fear about the player?)
  - Behavioral triggers (list 3-5 specific player actions that would shift this NPC's behavior, and how)
- Persistence scope: does this NPC remember across sessions, only within a session, or session-by-session reset?

6C — WORLD STATE FLAGS

Identify every world-level state that could change based on player action:
* Location states (sealed/unsealed, lit/dark, occupied/abandoned, transformed)
* Population states (who is alive, dead, missing, has fled, has gathered)
* Power states (who holds authority, who has lost it)
* Knowledge states (what truths are widely known vs hidden)
* Environmental states (weather affected by player action, seasons, time-of-day if relevant)
* Faction states (alliances, betrayals, neutrality shifts)

For each: name, initial value, possible values, triggers for change, narrative consequences when the state changes.

6D — PLAYER HISTORICAL ARCHIVE

What categories of player action must be remembered? Define the categories of events that the runtime must log permanently across sessions:
* Defining moral choices made
* NPCs significantly helped or harmed
* Promises made and whether they were kept
* Secrets discovered
* Crimes committed or witnessed
* Sacrifices made
* Failures and successes in key story moments

For each category: definition (what qualifies as an entry in this category), example entries (2-3 hypothetical examples from this IP), referenceable scope (which future scenes might reference this history).

---

TASK 7: WORLD REACTIVITY RULES

Define HOW the world responds to player history. This produces the rules engine.

7A — REACTIVITY CATEGORIES (define for THIS IP):
* ENVIRONMENTAL REACTIVITY: Does the physical world change based on player choices? (Doors sealed, rooms transformed, weather shifts, locations destroyed/restored.)
* NPC REACTIVITY: How do NPCs reference past player actions in dialogue, behavior, body language?
* FACTION/SOCIETAL REACTIVITY: How do groups, organizations, or societies respond to player reputation?
* SUPERNATURAL/SYSTEMIC REACTIVITY: If the IP has magic, technology, or systemic forces, how do those respond to player accumulated state?
* SYMBOLIC/THEMATIC REACTIVITY: How does the narrative voice itself shift based on what the player has become?

For each category present in this IP, return: how it triggers, when it triggers, how it manifests.

7B: REACTIVITY TIMING RULES:
* Some reactions are IMMEDIATE (same scene). Others are DELAYED (later scene, next session, climax). Specify timing rules for each reactivity type.

7C: REACTIVITY ESCALATION:
* Some reactions COMPOUND (each related player action makes the next reaction stronger). Specify which reactivity categories compound.

7D: REACTIVITY VISIBILITY:
* Some reactions are EXPLICIT (NPC says "I remember when you..."). Others are IMPLICIT (NPC's body language changes, world shifts subtly). Define when to use each.

---

TASK 8: STORYGUARD CANON EXTRACTION

LoreSpinner's StoryGuard system prevents player improvisation from breaking the source IP's internal logic. Extract the four layers of canon for this IP:

LAYER 1: PHYSICAL/RULE CANON (Inviolable):
What physical, magical, technological, or biological laws govern this world that CANNOT be broken regardless of player creativity? Each rule must be specific and enforceable.

LAYER 2: CHARACTER CANON (Truth-of-Self):
For each major character: their immutable core identity — the traits, beliefs, fears, or capabilities that define WHO they are. List 2-3 character truths per major character. Player creativity cannot violate these.

LAYER 3: NARRATIVE CANON (Required Story Beats):
What plot events MUST occur for the source IP to retain its meaning? List 3-7 narrative anchor points. Player choices can change HOW these happen, but not WHETHER they happen.

LAYER 4: VOICE/TONAL CANON (Atmospheric Truth):
What atmospheric, tonal, or aesthetic qualities define this IP's experience? Tone restrictions (this story can't suddenly become comedic / horror / etc.), language restrictions (formality level, period appropriateness), thematic restrictions (themes the story refuses to engage with).

---

TASK 9: STORY-NATIVE ALIGNMENT LABELS

Generic alignment labels (Chaotic, Lawful, Neutral) violate IP voice. Generate story-native equivalents.

Identify three to five alignment tendencies that exist in this IP's universe based on source evidence. For each, provide: alignment tendency name (story-native, NOT chaotic/lawful/neutral), behavioral markers (what kinds of player choices reveal this tendency), narrative consequences (how the world responds), and tonal/voice signature (how the narrator subtly shifts when describing a player whose actions tend toward this alignment).

Examples (do NOT use these unless they match the source):
- An Alice in Wonderland story might generate: Curious / Proper / Contrary
- A Fellowship story might generate: Faithful / Stoic / Restless
- A Snow Queen story might generate: Warmhearted / Resolute / Withdrawn

Generate three to five story-native alignment labels with full behavioral, consequential, and voice specifications.

---

TASK 10: BOILING PROFILE (ONLY WHEN THE USER PROMPT SAYS BOILER ENABLED: true)

Diagnose how strongly this story should be compacted into a fast, explorable playable cut. This is an editorial judgment, not a genre, format, event-count, or pacing formula.

Consider:
* source format and author pacing;
* action, exposition, connective-material, emotional, relationship, mystery, and meaningful-choice density;
* canon and voice fragility;
* suspense, confrontation, spectacle, intimacy, revelation, and payoff scenes that need earned breathing room;
* repeated or compressible material;
* arc position, climax proximity, and contrast between sessions.

Return exactly one `per_session_guidance` entry for every allocated session. Use the database Event ID shown in the user prompt for `must_compress_event_ids` and `must_breathe_event_ids`, never the story-global ordinal. Moment-count ranges must be deliberate and achievable. Per-session target minutes should reconcile to the story total within ordinary whole-minute rounding.

Moment counts are bounded by material, not by minutes. Every playable moment must be built from at least one distinct source event, and no event may be shared between two moments. So `recommended_moment_count_min` must never exceed the number of events in that session's `event_range`, and `recommended_moment_count_max` should stay close to it. A short bridge session of two or three events gets a two- or three-moment range and correspondingly few minutes — never inflate its moment range or its playtime to match the other sessions. If a session is too thin to carry a real moment range, prefer widening its `event_range` or folding it into the neighbouring session over promising moments the events cannot supply.

When BOILER ENABLED is false, omit `boiling_profile`. Do not produce placeholder Boiler guidance.

Return all required tasks as structured JSON matching the required schema.

STOP GATE: Before finalizing, verify: Does every session have a primary dramatic question? Are there at least 2 branching opportunities per session? Does the cross-session payoff plan connect at least 2 early choices to later sessions? Are there at least 3 defined branch dimensions? Are persistent state objects, NPCs, world flags, and player-historical archive categories all populated? Does the StoryGuard canon cover all four layers? Are the alignment labels story-native (never chaotic/lawful/neutral)? When Boiler is enabled, does every session have exactly one valid boiling guidance entry whose event IDs belong to that session, and is every `recommended_moment_count_min` less than or equal to the number of events in that session's `event_range`? If any answer is no, revise before returning.
