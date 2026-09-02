@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Alignment context subset: diction, dialogue fingerprints, comparative exclusion, negative space (ground alignment voice_signature and NPC tonal registers in the real author voice)',
])

PHASE 1 AUDIT:
{{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

FORMAT DETECTION:
{{ $formatDetection }}

ESTIMATED SESSION COUNT FROM FORMAT DETECTION: {{ $estimatedSessionCount }}

BOILER ENABLED: {{ $boilerEnabled ? 'true' : 'false' }}

STORY ENTRY OVERRIDE:
prefer_literal_opening: {{ $preferLiteralOpening ? 'true' : 'false' }}

If prefer_literal_opening is true, Session 1 must preserve the literal source opening beats when Session 1 covers the beginning of the story. Do not allocate Session 1 in a way that excludes the authored opening and starts at a later louder beat.

@if(!empty($playableProtagonist))
CANONICAL PLAYABLE PROTAGONIST (BINDING): The player avatar for this IP is {{ $playableProtagonist }}. Design session openings, agency, and dramatic questions from that character's body. Do not treat a more famous companion as the player.
@endif

@if(!empty($ipTrimmingWorldRules))
IP TRIMMING: WORLD RULES (feed these into StoryGuard Canon Extraction: every rule here is source-confirmed):
{{ json_encode($ipTrimmingWorldRules, JSON_PRETTY_PRINT) }}

@endif
@if(!empty($ipTrimmingConversionNotes))
IP TRIMMING: INTERACTIVE CONVERSION NOTES (use these for session content allocation: each trimmed section has an explicit conversion instruction):
{{ json_encode($ipTrimmingConversionNotes, JSON_PRETTY_PRINT) }}

@endif

EXTRACTED CHAPTERS:
@foreach($chapters as $chapter)
Chapter {{ $chapter['position'] }}: {{ $chapter['title'] }}
@endforeach

EVENT NUMBERING CONVENTION:
Events below are numbered 1..{{ $totalEvents }} across the entire story (story-global ordinal).
All `event_range` values AND `event_position` values you return MUST reference the story-global Event number shown below (e.g. "1-8", "48-58").
Do NOT use per-chapter positions. Do NOT emit ranges that exceed {{ $totalEvents }}.

EXTRACTED EVENTS (story-global ordinal | chapter context):
@foreach($events as $event)
Event {{ $event['story_position'] }} of {{ $totalEvents }} (database ID {{ $event['id'] }}, Chapter {{ $event['chapter_position'] }}, local pos {{ $event['position'] }}): {{ $event['title'] }}
@if(!empty($event['objectives']))
  Objectives: {{ $event['objectives'] }}
@endif
@endforeach
