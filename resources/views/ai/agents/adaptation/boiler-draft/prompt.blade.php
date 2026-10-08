BOILER DRAFT INPUT

STORY:
{!! json_encode($context['story'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

SESSION NUMBER:
{!! $context['session_number'] !!}

@include('ai.agents.adaptation._protagonist-rule', ['protagonist' => $context['protagonist']])
Write runtime_goal, live_pressure and treatment for an interlude card in third person, and give its player_desire and player_affordances to {!! $context['protagonist'] !!} where they actually are during it.

SESSION ALLOCATION:
{!! json_encode($context['session_allocation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

STORY-WIDE BOILING PROFILE:
{!! json_encode($context['boiling_profile'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

AUTHORITATIVE GUIDANCE FOR THIS SESSION:
{!! json_encode($context['session_guidance'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

ENTRY POINT DIAGNOSIS:
{!! json_encode($context['entry_point_diagnosis'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

FORMAT DETECTION:
{!! json_encode($context['format_detection'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

PLAYER ROLE, AGENCY, KNOWLEDGE, AND CANON CONSTRAINTS:
{!! json_encode($context['player_role_and_agency_constraints'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

SESSION BRANCH OPPORTUNITIES:
{!! json_encode($context['branch_opportunities'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

IP TRIMMING CONVERSION NOTES:
{!! json_encode($context['trimming_notes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

CONTROLLED VOICE PROFILE:
{!! json_encode($context['voice_profile'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

ASSIGNED SESSION EVENTS:
{!! json_encode($context['events'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

Critique every event, adopt the supplied target minutes, choose an exact moment count inside the approved range, and compile the complete Boiler Draft contract.

Before returning, perform these referential checks:

1. No event ID occurs in more than one `moment_drafts[].source_event_ids` list.
2. Every non-empty `event_role_map[].included_moment_key` equals the unique moment containing that event.
3. Every `playable_promise_arc` value is copied exactly from the generated `moment_drafts[].moment_key` values.
4. `playable_promise_arc.final_cash_out` equals the final ordered moment key.
5. Moments follow source order, and no moment includes an event marked `before_entry_point`.
