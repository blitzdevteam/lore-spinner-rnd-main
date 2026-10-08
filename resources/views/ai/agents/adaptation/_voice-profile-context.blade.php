{{--
    Voice Lock constitutional context block.
    Include in any adaptation phase prompt that generates authored prose or
    voice-dependent design.

    Required variable:
        $voiceProfile   — array, pre-sliced by VoiceProfilePromptSlice

    Optional variable:
        $voiceProfileLabel — string describing which sections are included,
                             e.g. "Sections 1+2: Voice DNA + Master Rule 1 bans"
--}}
=== VOICE PROFILE (VOICE LOCK — CONSTITUTIONAL LAW) ===

This profile overrides all generic style defaults, StoryGuard tonal guesses, and phase-level improvisation.
Voice Lock wins every conflict. Generic AI storytelling loses.
Any prose you write that violates this profile is rejected — not "close enough."

@if(!empty($voiceProfileLabel))
{!! $voiceProfileLabel !!}
@endif

{!! json_encode($voiceProfile, JSON_PRETTY_PRINT) !!}
