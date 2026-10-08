COLD OPEN REPAIR — contract {!! \App\Ai\Adaptation\ColdOpenRepairContext::CONTRACT_VERSION !!}
Title: {!! $story_title !!}
Slug: {!! $story_slug !!}
Session: {!! $session_number !!}

PROTAGONIST (the player is this person): {!! $protagonist !!}

COMPLETE CURRENT BOILER ENTRY-POINT DIAGNOSIS:
This is the title-specific Boiler output Tom's universal contract repairs. Preserve its
entry-point intent and use its own first_choice_spec. The existing cold_open is input
to repair, not approved prose.
{!! json_encode($current_entry_point_diagnosis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

@if($session_number > 1 && $previous_session_close !== [])
HOW SESSION {!! $session_number - 1 !!} IS AUTHORED TO END (the player arrives here from it):
{!! json_encode($previous_session_close, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
Begin where this close leaves {!! $protagonist !!}. Nothing in the cold open may contradict it:
place, people present, objects held or lost, what is already known. Do not retell it.

@endif
SOURCE AROUND THE BOILER ENTRY AND HANDOFF:
Use this to keep names and events canonical. Do not invent unsupported facts.
@forelse($source_events as $event)
Event {!! $event['position'] !!}: {!! $event['title'] !!}
{!! $event['content'] !!}

@empty
No source excerpt was available. Stay inside the complete Boiler diagnosis above.
@endforelse

Return the revised player-facing cold_open only. Apply the universal system contract and
match its worked example's clarity, orientation, voice, and stopping point. The one agreed
change is that the cold_open omits the final question because the interface supplies the
authored choices.
