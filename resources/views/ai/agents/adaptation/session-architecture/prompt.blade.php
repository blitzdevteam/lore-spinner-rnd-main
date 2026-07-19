@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Section 1: Voice DNA (beat placement and posture shift design must serve this author\'s rhythm and signature techniques)',
])

STORY SESSION MAP:
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

PHASE 3: ENTRY POINT DIAGNOSIS:
{{ json_encode($entryPointDiagnosis, JSON_PRETTY_PRINT) }}

@if(!empty($boilerDraft))
VALIDATED BOILER DRAFT:
{{ json_encode($boilerDraft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

Bind every beat-map row to one existing `moment_key`. Preserve exactly four BRANCHING rows, and ensure every Draft event-role row marked `CHOICE` has its `included_moment_key` represented by one of them. Distribute the existing content and interaction budgets inside the Draft's approved target playtime and exact moment count. Do not split, merge, add, remove, or replace moments. Return `boiler_budget_alignment.aligned=true` only when the architecture fits that authority; otherwise report the conflict without inventing an override.

@endif
SESSION NUMBER: {{ $sessionNumber }}

SOURCE PAGES FOR THIS SESSION:
{{ $sessionSourcePages }}
