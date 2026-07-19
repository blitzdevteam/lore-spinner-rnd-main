BOILER DRAFT CORRECTION

Your previous structured Boiler Draft failed deterministic validation. Return a complete replacement Draft, not a patch and not an explanation.

VALIDATION ERROR:
{{ $validationError }}

PREVIOUS INVALID DRAFT:
{{ json_encode($previousDraft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

AUTHORITATIVE INPUT:
{{ $originalPrompt }}

Correct the reported error while preserving all valid editorial decisions that do not conflict with it.

Mandatory final checks:

1. `moment_drafts` contains exactly the approved target count.
2. Moment keys are contiguous exact identifiers: `S{session_number}_M01`, `S{session_number}_M02`, and so on.
3. Each event ID occurs in `source_event_ids` for at most one moment.
4. Each event role's non-empty `included_moment_key` exactly matches the unique moment containing that event.
5. Every playable-promise arc value exactly copies a generated moment key.
6. `final_cash_out` exactly equals the final ordered moment key.
7. The first moment still contains the Entry Point start event.

Return only the complete corrected structured output matching the schema.
