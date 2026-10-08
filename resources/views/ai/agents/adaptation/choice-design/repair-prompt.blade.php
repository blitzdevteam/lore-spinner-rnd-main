{!! $originalPrompt !!}

---

CORRECTION REQUIRED: your previous Choice Design for the task above failed deterministic validation. Return a complete replacement, not a patch and not an explanation. Fix every reported error and keep every other field exactly as it was in your previous output.

VALIDATION ERROR:
{!! $validationError !!}

YOUR PREVIOUS INVALID CHOICE DESIGN:
{!! json_encode($previousChoiceDesign, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

Return the complete corrected structured output.

Correction requirements:

1. Preserve exactly four canonical branching choices in this order:
   `S{session}_C1` IDENTITY, `S{session}_C2` METHODOLOGY,
   `S{session}_C3` MORAL_WEIGHT, `S{session}_C4` SESSION_END_HOOK.
2. Bind `S{session}_C4` / SESSION_END_HOOK to the final ordered Draft moment.
3. Preserve exactly three A/B/C paths for every branching choice.
4. Draft CHOICE-role rows are candidate pressures. Preserve the three non-final placements selected by Architecture; do not create one canonical branch per Draft CHOICE role.
5. Every branching choice, emotional choice, and posture shift uses an existing Draft `moment_key`.
6. Preserve branch dimensions, freeform alignment mapping, defining lines, source canon, and consequence compatibility.
7. Do not invent a fifth branching choice.
