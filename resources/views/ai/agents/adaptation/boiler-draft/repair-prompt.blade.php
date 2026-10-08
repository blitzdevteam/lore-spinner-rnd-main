{!! $originalPrompt !!}

---

CORRECTION REQUIRED: your previous Boiler Draft for the task above failed deterministic validation. Return a complete replacement, not a patch and not an explanation. Fix every reported error and keep every other field exactly as it was in your previous output.

VALIDATION ERROR:
{!! $validationError !!}

YOUR PREVIOUS INVALID BOILER DRAFT:
{!! json_encode($previousDraft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

Correct the reported error while preserving all valid editorial decisions that do not conflict with it.

Mandatory final checks:

1. `moment_drafts` contains exactly the approved target count.
2. Moment keys are contiguous exact identifiers: `S{session_number}_M01`, `S{session_number}_M02`, and so on.
3. Every moment has a non-empty `source_event_ids` integer array — never `[]`.
4. Each event ID occurs in `source_event_ids` for at most one moment.
5. Each event role's non-empty `included_moment_key` exactly matches the unique moment containing that event.
6. Every playable-promise arc value exactly copies a generated moment key.
7. `final_cash_out` exactly equals the final ordered moment key.
8. The first moment still contains the Entry Point start event.

If the error is missing `source_event_ids`, fill each empty moment from the events whose `event_role_map[].included_moment_key` equals that moment key. Do not leave any moment with an empty source list.

Return only the complete corrected structured output matching the schema.
