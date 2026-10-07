@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Sections 1+2: Voice DNA + Master Rule 1 bans (resolution_prose must embody this profile)',
])

PHASE 5: BRANCHING CHOICE #4 | SESSION-END HOOK (this choice is already fully designed; execute it exactly, do not redesign it):
{{ json_encode($branchingChoice3Design, JSON_PRETTY_PRINT) }}

PHASE 6: CHOICE #4 CONSEQUENCE MAP:
{{ json_encode($choice3ConsequenceMap, JSON_PRETTY_PRINT) }}

THIS SESSION'S PRIMARY GOAL: {{ $sessionPrimaryGoal }}

SESSION NUMBER: {{ $sessionNumber }}

@include('ai.agents.adaptation._protagonist-rule', ['protagonist' => $protagonist])
@if(! empty($nextSessionOpening))
HOW SESSION {{ $nextSessionOpening['session_number'] }} OPENS (its authored cold open; every next_session_opens line must land here):
{!! $nextSessionOpening['cold_open'] !!}

@elseif(! empty($isFinalSession))
THIS IS THE FINAL SESSION. The close lands on the source's own ending, never on an invented continuation.

@endif

@if(!empty($boilerDraft))
VALIDATED BOILER DRAFT:
{{ json_encode($boilerDraft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

Bind this existing close to the final ordered draft moment. Return its `closing_moment_key`, explain the close trigger's relationship to that moment, and provide concise `next_bridge_guidance` for Finalize. Preserve the existing resolution prose, hook transition, and A/B/C session-end choice.

@endif
SESSION EVENT LIST (select ONE story_position for session_close_trigger_event_position):
@foreach($sessionEvents as $ev)
- story_position={{ $ev['story_position'] }} | title="{{ $ev['title'] }}" | objectives="{{ $ev['objectives'] }}"
@endforeach

SOURCE PAGES FOR RESOLUTION MOMENT:
{{ $resolutionSourcePages }}
