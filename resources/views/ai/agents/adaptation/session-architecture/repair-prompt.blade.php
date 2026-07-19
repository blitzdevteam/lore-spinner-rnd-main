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
5. Every Draft `event_role_map` row marked `CHOICE` must have its `included_moment_key` represented by one of those four BRANCHING rows. Move the matching canonical branch slot to that moment; do not add a fifth slot.
6. Bind the release/reward to an existing Draft moment in source order. Use humor, absurdity, or wonder; if the source lacks one, apply the minimum canon-safe editorial intervention inside that existing moment.
7. Do not add, remove, split, merge, reorder, or replace Draft moments.
8. Return `boiler_budget_alignment.aligned=true` only when the corrected architecture genuinely fits.
