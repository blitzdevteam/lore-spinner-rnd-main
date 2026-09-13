STORY TITLE: {{ $title }}

@if(!empty($playableProtagonist))
CANONICAL PLAYABLE PROTAGONIST (BINDING): {{ $playableProtagonist }}. Use this as playable_character_name (uppercase).
@endif

@if(!empty($scriptTruncated))
NOTE: The script below is truncated to the opening section for token limits. Extract world_rules and tone from this excerpt only.
@endif

STORY SCRIPT{{ !empty($scriptTruncated) ? ' (EXCERPT)' : '' }}:
{{ $script }}
