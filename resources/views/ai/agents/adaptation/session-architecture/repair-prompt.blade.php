Your previous Boiler-enabled Session Architecture failed deterministic validation.

VALIDATION ERROR:
{{ $validationError }}

PREVIOUS INVALID ARCHITECTURE:
{{ json_encode($previousArchitecture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

ORIGINAL TASK AND AUTHORITIES:
{{ $originalPrompt }}

Return the complete corrected structured output. Preserve valid source analysis, Draft moment keys, exact moment count, and all required interaction slots.

Correction requirements:

1. Every `beat_map` row must reference a valid `moment_key` from the Boiler Draft.
2. Exactly four rows use a `BRANCHING` choice slot.
3. The fourth / SESSION_END_HOOK BRANCHING slot binds to the final ordered Draft moment.
4. Draft CHOICE roles are candidate pressures. Select only the strongest three non-final moments for IDENTITY, METHODOLOGY, and MORAL_WEIGHT; do not create one branch per CHOICE-role event.
4a. When the Draft has fewer than four moments, BRANCHING rows share moments. Report `aligned=true` in that case — a short session is not a budget conflict.
5. Do not add, remove, split, merge, reorder, or replace Draft moments.
6. Return `boiler_budget_alignment.aligned=true` only when the corrected architecture genuinely fits.
