SOURCE TEXT UPLOADED: {!! $title !!}, {!! $format !!}

@if(!empty($storySpine))
IP TRIMMING — STORY SPINE (for context):
{!! json_encode($storySpine, JSON_PRETTY_PRINT) !!}

@endif
@if(!empty($worldRules))
IP TRIMMING — WORLD RULES (for StoryGuard Canon Extraction):
{!! json_encode($worldRules, JSON_PRETTY_PRINT) !!}

@endif
OPENING SECTION (pages 1-30):
{!! $openingPages !!}

MIDPOINT SECTION:
{!! $midpointPages !!}

CLOSING SECTION (final 20 pages):
{!! $closingPages !!}
