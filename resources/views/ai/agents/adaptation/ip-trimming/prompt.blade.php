=== SOURCE TEXT TO TRIM ===

Title: {!! $title ?? 'Untitled' !!}
@isset($author)
Author: {!! $author !!}
@endisset
@isset($year)
Year: {!! $year !!}
@endisset
@isset($format)
Format: {!! $format !!}
@endisset
@isset($pageCount)
Page count: {!! $pageCount !!}
@endisset

Full source text follows. Apply Tasks 1–5 verbatim from the system prompt. Do not paraphrase preserved content. Insert TRIM MARKER lines in the exact bracketed format described in Task 5.

----- BEGIN SOURCE -----
{!! $sourceText ?? '' !!}
----- END SOURCE -----

Return a single JSON object with these top-level keys: `story_spine`, `world_rules`, `content_triage_log`, `interactive_conversion_notes`, `trimmed_source_text` (with `original_length_estimate`, `trimmed_length_estimate`, `reduction_percentage`, `text`). All five tasks must be present.
