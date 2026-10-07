@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 7: Session Close and Retention Hook'])

=== PHASE 7: SESSION CLOSE AND RETENTION HOOK ===

The session close is the highest-stakes prose in the entire session. It must do two things simultaneously: honor the payoff the user earned AND make returning feel mandatory.

TASK 0: SELECT THE TRIGGER EVENT (EXIT POINT)

This is the exit-side counterpart to start_event_position (Phase 3). Runtime fires the session close when the player arrives at this exact event — so pick a real, playable arrival point, not an abstract beat.

Rules:
1. Select from the provided SESSION EVENT LIST below. Return the chosen event's `story_position` (1-based story-global ordinal).
2. The trigger event is the moment the player is IN when resolution_prose is narrated and the session-end choice appears.
3. It is usually the last event that naturally contains the Branching Choice #4 (session-end hook) moment, OR the event immediately before a hard emotional landing where the session must end.
4. Do NOT pick the very first event of the next session. The close fires inside the current session's last beat.
5. Do NOT describe the event abstractly — select by story_position integer from the list.

TASK 1: THE RESOLUTION BEAT PROSE

Rules:
1. The payoff must be real and unambiguous. Do not hedge it.
2. Sensory specificity: ground the arrival in one physical detail the user will remember.
3. The seed for the next session must be planted lightly, as an image or a pull, never as a question to the player.
4. Word count: 120-200 words.
5. Present tense. Second person wherever the protagonist is present. If the final card is a scene the protagonist is not in, tell that part as a third-person interlude, then turn to the protagonist where they actually are before the session-end choice.
6. Stop before the session-end choice. Never narrate what the choice's outcomes will narrate (a departure, a blow, an arrival), or the player lives it twice.

TASK 2: THE SESSION-END HOOK

Transition rules:
1. One beat of rest — one sentence honoring the arrival.
2. Then: a new detail, direction, or presence. The session does not rest long.
3. The choice question must arrive naturally — inevitable, not imposed.

Write the transition prose that arrives at the session-end choice. Then deliver Branching Choice #4 EXACTLY as designed in Phase 5 above — the same choice question and the same three options, reworded only to fit the moment's voice, not reimagined. Do not write a new choice. Do not substitute a different dramatic question. Phase 5 already designed this hook; Phase 7 executes it. Include a final line: the session's last words before close, an in-world image in the author's voice. Never a question, never an instruction to the player, never a mention of choosing or of the game.

Every option's `next_session_opens` must describe how the next session actually opens (its authored cold open is given in the user prompt) as coloured by that option. Never describe a scene the next session's opening skips or contradicts. In the final session, there is no next session: each `next_session_opens` describes the story's own final image as that option colours it.

The transition and the final line never ask the player anything, never comment on what the moment means, and never address the player as a player.

TASK 3: THE STICKINESS AUDIT

CHECK 1: PAYOFF TEST: Did the user get what they were working toward? (YES / PARTIALLY / NO)
CHECK 2: RETURN DRIVER TEST: Is the user waiting to find out what they chose to do? (THEY CHOSE / THEY WATCH — must be THEY CHOSE)
CHECK 3: OVERNIGHT TEST: Will the user think about their choice before they return? (YES / NO)

BOILER BINDING RULE:
When the user prompt contains a VALIDATED BOILER DRAFT, bind this existing close to the final ordered draft moment. Return `closing_moment_key`, explain how the selected trigger and existing close complete that room in `closing_moment_relationship`, and give Finalize concise `next_bridge_guidance`. Do not create another moment, alter the approved moment count, or redesign the canonical close. When no Boiler Draft is supplied, omit Boiler-only fields.

Return all four tasks as structured JSON matching the required schema (session_close_trigger_event_position, resolution_prose, hook_transition, session_end_choice, stickiness_audit).

STOP GATE: If any stickiness audit check fails, revise before returning.
