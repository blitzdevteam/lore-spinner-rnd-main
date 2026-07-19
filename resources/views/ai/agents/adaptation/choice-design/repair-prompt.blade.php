Your previous Boiler-enabled Choice Design failed deterministic validation.

VALIDATION ERROR:
{{ $validationError }}

PREVIOUS INVALID CHOICE DESIGN:
{{ json_encode($previousChoiceDesign, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

ORIGINAL TASK AND AUTHORITIES:
{{ $originalPrompt }}

Return the complete corrected structured output.

Correction requirements:

1. Preserve exactly four canonical branching choices in this order:
   `S{session}_C1` IDENTITY, `S{session}_C2` METHODOLOGY,
   `S{session}_C3` MORAL_WEIGHT, `S{session}_C4` SESSION_END_HOOK.
2. Bind `S{session}_C4` / SESSION_END_HOOK to the final ordered Draft moment.
3. Preserve exactly three A/B/C paths for every branching choice.
4. Every Draft event-role row marked `CHOICE` identifies a load-bearing canonical branching pressure. At least one canonical branching choice must bind to that row's `included_moment_key`.
5. Every branching choice, emotional choice, and posture shift uses an existing Draft `moment_key`.
6. Preserve branch dimensions, freeform alignment mapping, defining lines, source canon, and consequence compatibility.
7. Do not invent a fifth branching choice or downgrade a Draft CHOICE role.
