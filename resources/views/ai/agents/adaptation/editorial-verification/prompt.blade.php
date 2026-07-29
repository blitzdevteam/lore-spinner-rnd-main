COMPLETE SESSION DESIGN (Phases 3-7 outputs in order):
{{ json_encode($completeSessionDesign, JSON_PRETTY_PRINT) }}

@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'All three sections: Voice DNA + Master Rule 1 bans + 14-Point Audit Protocol (feed Q11–Q17)',
])

STORYGUARD CANON (Phase 2 Task 8: use for Q18-Q21):
{{ json_encode($storyGuardCanon, JSON_PRETTY_PRINT) }}

PERSISTENT STATE SCHEMA (Phase 2 Task 6: use for Q23):
{{ json_encode($persistentStateSchema, JSON_PRETTY_PRINT) }}

WORLD REACTIVITY RULES (Phase 2 Task 7: use for Q22):
{{ json_encode($worldReactivityRules, JSON_PRETTY_PRINT) }}

STORY SESSION MAP (for cross-session verification):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

@if($boilerEnabled)
BOILER DRAFT:
{{ json_encode($boilerDraft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

BOILER FINAL MANIFEST:
{{ json_encode($boilerFinalCut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

NORMALIZED EVENT ROLES:
{{ json_encode($boilerEventRoles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

CANONICAL FINAL PLAYABLE MOMENTS:
{{ json_encode($boilerMoments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

Run the complete Boiler audit. `production_status` is PASS only when every Boiler item passes.
Allowed roles: PLAY, PULSE, CHOICE, REACTION, MONTAGE, ECHO, CUT, and Finalize PRESSURE for unused Draft CHOICE. Never ban MONTAGE.

@endif
SESSION NUMBER: {{ $sessionNumber }}
