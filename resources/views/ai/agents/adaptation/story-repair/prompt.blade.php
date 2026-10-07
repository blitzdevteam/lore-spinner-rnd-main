STORY REPAIR REVIEW
Title: {!! $story_title !!}
Slug: {!! $story_slug !!}
Session: {!! $session_number !!} of {!! $total_sessions !!}
Protagonist (the player): {!! $protagonist !!}

STORED MATERIAL FOR THIS SESSION (patch targets: scene_card keyed by moment key, session_choice_design, choice_consequence_map, session_close_design, entry_point_diagnosis):
{!! json_encode($material, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

@if($entry_decisions !== [])
WHERE THIS SESSION'S ENTRY STARTS IN THE SOURCE:
{!! json_encode($entry_decisions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

@endif
@if($skip_targets !== [])
NOT UNDER REVIEW THIS RUN (leave them unchanged and propose no patches or rebinds for them): {!! implode(', ', $skip_targets) !!}

@endif
@if($previous_session !== null)
HOW SESSION {!! $previous_session['session_number'] !!} IS AUTHORED TO END (this session must begin from it; patch target previous_session_close):
{!! json_encode($previous_session['session_close_design'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

@endif
@if($next_session !== null && $next_session['entry'] !== [])
HOW SESSION {!! $next_session['session_number'] !!} OPENS (this session's close must hand off to it; patch target next_session_entry):
{!! json_encode($next_session['entry'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

@endif
@if(! empty($reviewer_notes))
KNOWN DEFECTS REPORTED BY PLAYTESTERS (verify each against the material and the source; fix every one that is real, even where the neighbouring material agrees with it, and say in a finding what you did about each):
@foreach($reviewer_notes as $note)
- {!! $note !!}
@endforeach

@endif
SOURCE EVENTS FOR THIS SESSION (canon; the material must agree with it):
@forelse($source_events as $event)
Event {!! $event['position'] !!}: {!! $event['title'] !!}
{!! $event['content'] !!}

@empty
No source events were available. Judge canon from the material and the neighbouring sessions only.
@endforelse

Review all of the material above. Return every finding, a reorder if the cards are out of story order, a rebind for every choice on the wrong card, and a patch for every finding that replacing text can fix.
