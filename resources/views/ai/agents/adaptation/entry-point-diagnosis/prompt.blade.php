@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Sections 1+2+3: Voice DNA + Master Rule 1 bans + Voice Anchor exemplars + Anchor Card (the cold open you write must match the Voice Anchor rhythm, diction, and compression; obey all bans and the Anchor Card)',
])

PHASE 1 AUDIT: {!! json_encode($ipAudit, JSON_PRETTY_PRINT) !!}

STORY SESSION MAP (this session's allocation):
{!! json_encode($storySessionMap, JSON_PRETTY_PRINT) !!}

PROTAGONIST: {!! $protagonist !!}
FORMAT: {!! $format !!}

@include('ai.agents.adaptation._protagonist-rule', ['protagonist' => $protagonist])
@if(! empty($mustBreatheEventPositions))
MUST-BREATHE EVENTS IN THIS SESSION (the Story Session Map requires these to be played; never start after one of them): {!! implode(', ', $mustBreatheEventPositions) !!}
@endif

STORY ENTRY OVERRIDE:
prefer_literal_opening: {!! $preferLiteralOpening ? 'true' : 'false' !!}

If prefer_literal_opening is true, and this is Session 1, start at the earliest available session event. That is the literal source opening, for a novel or a screenplay, unless that event is genuinely non-playable. Do not move forward to a louder later beat.

SESSION NUMBER: {!! $sessionNumber !!}

@if(! empty($previousSessionEnding))
WHERE THE PREVIOUS SESSION ENDS (its last source events, in order; the player arrives at this session straight from here):
@foreach($previousSessionEnding as $ev)
- Event {!! $ev['story_position'] !!}: {!! $ev['title'] !!}
{!! $ev['content'] !!}

@endforeach
SESSION CONTINUITY (hard rules for session {!! $sessionNumber !!}):
- This session continues the story. Choose the start event and write the cold open so the player arrives from exactly where the previous session's last event leaves {!! $protagonist !!}.
- You may skip connective events between that ending and your start event. Any skipped event that changes where {!! $protagonist !!} is, who is with them, what they hold or know, or what has been done to them (a capture, a separation, a rescue, a journey, a death) must be stated plainly in the cold open, or the start event must come before it. A player must never discover such a change as already done.
- Do not start later than the first event a must-breathe beat needs. Every event from your start event onward will be played as scene cards in order.

@endif

EVENT NUMBERING CONVENTION:
Events below use the story-global ordinal (same numbering as the Story Session Map's `event_range`).
The `start_event_position` you return MUST be one of the story-global Event numbers shown below.
Do NOT use per-chapter positions.

EVENTS IN THIS SESSION (use these positions to identify your cut point):
@foreach($sessionEvents as $ev)
- Event {!! $ev['story_position'] !!} (Chapter {!! $ev['chapter_position'] !!}, local pos {!! $ev['position'] !!}): {!! $ev['title'] !!}@if(!empty($ev['objectives'])) | {!! $ev['objectives'] !!}@endif

@endforeach

SOURCE PAGES FOR THIS SESSION:
{!! $sessionSourcePages !!}
