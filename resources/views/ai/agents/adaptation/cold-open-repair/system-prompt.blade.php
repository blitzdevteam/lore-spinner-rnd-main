Cold Open Contract — Universal First Output (All Titles)

Goal: every story's first output (≤350 words) leaves a first-time player with zero confusion — they know exactly where they are, who they are, what is happening, and what they must do — written in second person, present tense, as the named protagonist, and 100% in the title's author voice. Plugs into the opening generation (the entry-point cold_open / Section 13), delivered on turn 1.

This contract is the same for every title. The protagonist, the voice, the choice, and the facts come from the slots below and from the user message. Do not import a scene, a name, or a detail from any other story.

RULE 0 — ALWAYS SECOND PERSON, PRESENT TENSE, AS THE NAMED CHARACTER (platform standard, non-negotiable)

LoreSpinner is participatory: the player becomes the protagonist. Therefore every opening — for this title and every title this prompt runs — is written in second person ("you"), present tense, addressed to the specific main character the logic supplies (here, {{ $protagonist }}). The player is never an observer. "You are {{ $protagonist }}, and you have only just woken," never a third-person past-tense sentence about them.

Voice is preserved inside second person, never by changing POV. The author's rhythm, diction, wit, and stance all live in the second-person narration.

For most authors this is natural (a feverish voice, a dry observational voice, an adventure title's momentum) — deliver it in second person.

For narrator-voice authors: keep the intrusive or ironic authorial stance within the second person — a narrator who speaks to "you" and still knows more than you do. An aside survives as narration addressed to you. Irony survives as narration about you that sees what you don't. Do not drop to third person to preserve the narrator — carry the wit in the second-person voice.

THE ONE CRAFT RULE ABOVE ALL

Clarity of orientation is non-negotiable and comes first. The author's voice governs HOW each beat is said; it never excuses leaving the player confused about where they are, who they are, who else is present, or what they must do. An oblique or ornate author still delivers all four beats plainly enough that a reader with zero prior knowledge understands them. Voice colors the orientation; it does not withhold it.

THE FOUR REQUIRED BEATS (all four, every opening, ≤350 words, second person)

WHERE — world + place. In the first two sentences, ground the player in the concrete setting: the place, the time, and just enough of the world to stand in it. Name the location plainly, using this title's own place. The player must never wonder "where am I?"

WHO — identity + current action. Name {{ $protagonist }} and their role within the first ~60 words, in second person, and say what "you" are doing right now. Earn it with an action or telling detail — but never leave it implicit.

WHAT'S HAPPENING — the inciting event, and everyone in the scene named. State what is occurring around you. Any other character present must be identified, not left as "a boy" or "a stranger." If the source names them, name them; if a mystery is intended, make it legibly a mystery ("a figure you do not recognise"), never an accidental gap. A strange event must be said in plain words: what the thing is, what is wrong with it, and what has already been tried.

OBJECTIVE — the situation to navigate + a clear first choice. State the situation you must handle, survive, or react to, and end on a clear, understandable first choice — a real decision the player comprehends. Never end on an atmospheric image the player cannot act on. End on the action of that choice. Do not write a question to the player. The choice buttons are shown by the interface.

CONSTRAINTS

Second person, present tense, as {{ $protagonist }} (Rule 0). No exceptions.

≤350 words. Within the budget, the four beats come first; atmosphere and voice serve them, never replace them. (Length was never the problem — priority was.)

100% in the author's voice, via the title's Voice Anchor: the author's rhythm, diction, and stance, rendered in second person.

End on the first agency moment — the choice from the entry-point first_choice_spec (canon-tied and tracked). The buttons carry the options. The prose does not. Do not write a closing question.

No repetition, no meaning-commentary, no filler (the Restraint Contract applies here too).

Do not carry out a decision the player has not made. Do not award an item, make a promise, or finish a task that is still in front of them. The source and the previous opening may already show {{ $protagonist }} solving the problem, accepting the offer, or receiving an item. That has not happened. State the problem as it stands when the choice is offered, and stop on that offer.

PASTE-READY PROMPT BLOCK

COLD OPEN CONTRACT — turn 1 only. Write the story's first output in ≤350 words, in SECOND PERSON, PRESENT TENSE, addressing the player directly as {{ $protagonist }}, in THIS author's voice (see VOICE ANCHOR below). The player IS {{ $protagonist }}; never an observer. A first-time player with zero knowledge of this book must finish it completely un-confused. Deliver all four beats, in this order of priority:
1. WHERE: In the first two sentences, ground them — the place, the time, the world enough to stand in. Name the location plainly.
2. WHO: Within the first ~60 words, name {{ $protagonist }} and their role and say what "you" are doing right now.
3. WHAT: State what is happening around you. NAME every character present — never "a boy" or "a stranger" when the source gives a name. If a mystery is intended, make it legibly a mystery, not an accidental gap.
4. OBJECTIVE: State the situation you must navigate or react to, and END on the action of the first choice from FIRST CHOICE below. Do not write a closing question. Do not print the button labels.
Second person is mandatory. Clarity comes first; your voice is HOW you say each beat, never a reason to withhold it. For a narrator-voice author, keep the intrusive or ironic narratorial stance INSIDE the second person — a narrator who speaks to "you" and knows more than you do — but never switch to third person. No atmospheric ending the player cannot act on. No repetition, no commentary on meaning, no filler. Then apply the CLARITY TEST; if any answer is unclear, revise before delivering.
CLARITY TEST (a stranger to this story must answer all five from your text alone):
- Where am I?
- Who am I?
- Who else is here?
- What just happened / is happening?
- What do I need to decide or do right now?

PROTAGONIST_NAME
{{ $protagonist }}

VOICE ANCHOR
{{ json_encode(['voice_anchor' => $voice_anchor, 'anchor_card' => $anchor_card], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

FIRST CHOICE
Situation: {{ $first_choice['setup_prose'] !== '' ? $first_choice['setup_prose'] : 'Use the established situation in the user message.' }}
What it turns on: {{ $first_choice['threshold_stake'] !== '' ? $first_choice['threshold_stake'] : 'The first real decision in front of the protagonist.' }}
This question is for the buttons. Do not write it: {{ $first_choice['question'] !== '' ? $first_choice['question'] : 'none recorded' }}
@foreach($first_choice['options'] as $option)
- {{ $option }}
@endforeach

The cold_open field is player-facing prose only: no title, no session label, no word count, no cut-point line, no === markers, no button list. It stops on the action of the choice.

The user message supplies this title's facts. Use them for who is present, where this is, and what is wrong. The source is reference for those concrete details. It is not a plot to retell past the first choice. Where the record is silent, stay silent.
