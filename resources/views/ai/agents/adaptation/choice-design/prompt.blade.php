@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Sections 1+2+Anchor Card: Voice DNA + Master Rule 1 bans + Anchor Card (all 115-125 word outcomes must embody this profile; Anchor Card binary rules apply to every authored line)',
])

FIRST-CHOICE SPEC (from Phase 3 / Deliverable 10):
@if(!empty($firstChoiceSpec))
{{ json_encode($firstChoiceSpec, JSON_PRETTY_PRINT) }}
@else
[Phase 3 has not been run for this IP: Task 1 operates in fallback mode but must still apply the stakes-tied / no-tutorial gate.]
@endif

PHASE 4 BEAT MAP:
{{ json_encode($beatMap, JSON_PRETTY_PRINT) }}

@if(!empty($boilerDraft))
VALIDATED BOILER DRAFT:
{{ json_encode($boilerDraft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

Bind every branching choice, emotional choice, and posture shift to one existing `moment_key`. Preserve the complete canonical four-choice A/B/C topology, all choice IDs, option counts, outcomes, defining lines, dimensions, and freeform mapping.

BINDING AND OUTCOME RULES (the runtime offers a choice only on its card and plays the cards in order):
- Bind each choice to the card where its narrative_setup has already happened and its question is still open. Never bind a choice to a card that plays before its setup, or after the scene it asks about.
- Every option's outcome must leave the canonical sequence reachable: an option may not destroy, remove, kill or resolve anything a later card or session still needs (an object, a person, a secret). Paths differ in cost and feeling, never in whether the next card's events can happen.
- An outcome stops before the next card's events. Never narrate the next card's beat inside an outcome, or the player lives it twice.
- Every environmental_flag and inventory entry describes the option it sits on, not a sibling option.

@endif
@include('ai.agents.adaptation._protagonist-rule', ['protagonist' => $protagonist])

STORY SESSION MAP (including branch dimensions):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

PROTAGONIST CORE TRAIT: {{ $protagonistCoreTrait }}

EMOTIONAL PROMISE: {{ $emotionalPromise }}

SESSION NUMBER: {{ $sessionNumber }}

SOURCE PAGES FOR CHOICE MOMENTS:
{{ $choiceMomentPages }}
