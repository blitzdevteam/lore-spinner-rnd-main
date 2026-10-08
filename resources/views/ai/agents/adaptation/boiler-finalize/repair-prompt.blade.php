{!! $originalPrompt !!}

---

CORRECTION REQUIRED: your previous final cut for the task above failed deterministic validation. Return a complete replacement, not a patch and not an explanation. Fix every reported error and keep every other field exactly as it was in your previous output.

VALIDATION ERROR:
{!! $validationError !!}

YOUR PREVIOUS INVALID FINAL CUT:
{!! json_encode($previousCut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}

Correct the reported error while preserving valid authored details. Upstream-owned version, session, cut key, playable promise, moment identity/order, source and anchor events, choice bindings, next-moment references, and event roles are deterministically overlaid by PHP.

Return exactly the Draft's moment count. Every card still requires a title, opening image, runtime goal, player desire, live pressure, affordances, canon rules, source extracts, positive word and turn budgets, semantic exit condition, and natural next bridge.

Return only the complete corrected structured output matching the schema.
