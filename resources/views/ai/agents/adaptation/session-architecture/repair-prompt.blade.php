Your previous Boiler-enabled Session Architecture failed deterministic validation.

VALIDATION ERROR:
{{ $validationError }}

PREVIOUS INVALID ARCHITECTURE:
{{ json_encode($previousArchitecture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

ORIGINAL TASK AND AUTHORITIES:
{{ $originalPrompt }}

Return the complete corrected structured output. Preserve valid source analysis, Draft moment keys, exact moment count, and all required interaction slots.

Correction requirements:

1. Every `beat_map[].time_range` uses exact `MM:SS-MM:SS` format.
2. Exactly one row has `beat_type` equal to `BREATH`.
3. The BREATH row fits wholly inside `10:00-13:00`.
4. Exactly four rows use a `BRANCHING` choice slot.
5. The fourth / SESSION_END_HOOK BRANCHING slot binds to the final ordered Draft moment.
6. Draft CHOICE roles are candidate pressures. Select only the strongest three non-final moments for IDENTITY, METHODOLOGY, and MORAL_WEIGHT; do not create one branch per CHOICE-role event.
7. Bind the release/reward to an existing Draft moment in source order. Use humor, absurdity, or wonder; if the source lacks one, apply the minimum canon-safe editorial intervention inside that existing moment.
8. Do not add, remove, split, merge, reorder, or replace Draft moments.
9. Return `boiler_budget_alignment.aligned=true` only when the corrected architecture genuinely fits.
