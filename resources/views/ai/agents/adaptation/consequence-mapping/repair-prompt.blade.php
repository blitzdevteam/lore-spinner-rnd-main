{!! $originalPrompt !!}

---

CORRECTION REQUIRED: your previous Consequence Map for the task above failed deterministic validation. Return a complete replacement, not a patch and not an explanation. Fix every reported error and keep every other field exactly as it was in your previous output.

VALIDATION ERROR:
{!! $validationError !!}

YOUR PREVIOUS INVALID CONSEQUENCE MAP:
{!! json_encode($previousConsequenceMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

Return the complete corrected structured output. Phase 5 Choice Design is the sole authority for choice identity and branch topology.

Correction requirements:

1. Preserve exactly every Phase 5 branching and emotional `choice_id`.
2. Copy each choice's `moment_key` and `what_this_choice_tracks` exactly.
3. Preserve exactly the authored A/B/C labels, alignments, and branching defining lines.
4. Return one consequence per authored choice and one freeform guideline per branching choice/path pair.
5. Keep every optional `echo_moment_key` inside the validated Draft.
6. Supply a concrete `moment_narrator_hook` for every consequence.
7. Do not create, remove, rename, reorder, or reinterpret the canonical choice topology.
