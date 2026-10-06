STORY REPAIR REVIEW
Title: {!! $story_title !!}
Slug: {!! $story_slug !!}
Session: {!! $session_number !!} of {!! $total_sessions !!}
Protagonist (the player): {!! $protagonist !!}

STORED MATERIAL FOR THIS SESSION (patch targets: scene_card keyed by moment key, session_choice_design, choice_consequence_map, session_close_design, entry_point_diagnosis):
{!! json_encode($material, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

@if($previous_session !== null)
HOW SESSION {!! $previous_session['session_number'] !!} IS AUTHORED TO END (this session must begin from it; not a patch target):
{!! json_encode($previous_session['session_close_design'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

@endif
@if($next_session !== null && $next_session['cold_open'] !== '')
HOW SESSION {!! $next_session['session_number'] !!} OPENS (this session's close must hand off to it; not a patch target):
{!! $next_session['cold_open'] !!}

@endif
SOURCE EVENTS FOR THIS SESSION (canon; the material must agree with it):
@forelse($source_events as $event)
Event {!! $event['position'] !!}: {!! $event['title'] !!}
{!! $event['content'] !!}

@empty
No source events were available. Judge canon from the material and the neighbouring sessions only.
@endforelse

Review all of the material above. Return every finding, and a patch for every finding that replacing text can fix.
