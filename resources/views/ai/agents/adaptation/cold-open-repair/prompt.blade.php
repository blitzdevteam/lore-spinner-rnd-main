COLD OPEN REPAIR — contract {{ \App\Ai\Adaptation\ColdOpenRepairContext::CONTRACT_VERSION }}
Title: {{ $story_title }}
Slug: {{ $story_slug }}
Session: {{ $session_number }}

PROTAGONIST (the player is this person): {{ $protagonist }}

VOICE ANCHOR (rhythm, diction, and stance — write in this hand, in second person):
{{ json_encode($voice_anchor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

ANCHOR CARD:
{{ json_encode($anchor_card, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

ESTABLISHED NOW (state these plainly; they are already true when the opening begins):
{{ $established !== '' ? $established : 'None recorded.' }}

WITHHOLD (do not put these in the opening):
{{ $deferred !== '' ? $deferred : 'None recorded.' }}

FACTS THE OPENING MUST SAY IN PLAIN WORDS:
@forelse($cold_open_facts as $fact)
- {{ $fact }}
@empty
None recorded beyond the established situation.
@endforelse

PENDING DECISION (dramatise this situation and stop. Do not speak a question. Do not print these options. The buttons already show them.):
Situation: {{ $first_choice['setup_prose'] !== '' ? $first_choice['setup_prose'] : 'Use the established situation.' }}
What it turns on: {{ $first_choice['threshold_stake'] !== '' ? $first_choice['threshold_stake'] : 'The first real decision in front of the protagonist.' }}
Do not write this question or any other question to the player: {{ $first_choice['question'] !== '' ? $first_choice['question'] : 'none recorded' }}
@foreach($first_choice['options'] as $option)
- {{ $option }}
@endforeach

PREVIOUS OPENING (this is the defective opening you are replacing. If it already shows {{ $protagonist }} solving the problem, accepting, refusing, or receiving something, that action has not happened. Do not repeat it. Do not copy its vagueness.):
{{ $current_cold_open !== '' ? $current_cold_open : 'None stored.' }}

SOURCE (names, place, objects, and what is wrong. Not a plot to retell. If a later passage shows {{ $protagonist }} already solving the problem or taking the offer, leave that undone. Do not invent a fact the source and the diagnosis do not support. Stop when the pending decision is offered.):
@forelse($source_events as $event)
Event {{ $event['position'] }}: {{ $event['title'] }}
{{ $event['content'] }}

@empty
No source excerpt was available. Stay inside the diagnosis above.
@endforelse
