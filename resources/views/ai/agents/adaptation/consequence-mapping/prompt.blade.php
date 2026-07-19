@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Sections 1+2: Voice DNA + Master Rule 1 bans (narrative_execution instructions must be written in the author\'s voice, not generic game prose)',
])

PHASE 5 CHOICE DESIGNS: ALL FOUR BRANCHING CHOICES:
{{ json_encode($branchingChoices, JSON_PRETTY_PRINT) }}

PHASE 5 EMOTIONAL CHOICES:
{{ json_encode($emotionalChoices, JSON_PRETTY_PRINT) }}

@if(!empty($boilerDraft))
VALIDATED BOILER DRAFT:
{{ json_encode($boilerDraft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

For every branching and emotional consequence, preserve the existing paths and add the choice's immediate `moment_key`, an optional later same-session `echo_moment_key`, and one concrete `moment_narrator_hook`. Do not create another branch topology.

@endif
PHASE 2 PERSISTENT STATE SCHEMA (named NPCs, world flags, archive categories you must reference verbatim):
{{ json_encode($persistentStateSchema, JSON_PRETTY_PRINT) }}

PHASE 2 WORLD REACTIVITY RULES (use these reactivity categories + timing rules in your consequence map):
{{ json_encode($worldReactivityRules, JSON_PRETTY_PRINT) }}

STORY SESSION MAP (cross-session payoff plan):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

PROTAGONIST CORE TRAIT: {{ $protagonistCoreTrait }}

SESSION NUMBER: {{ $sessionNumber }}
