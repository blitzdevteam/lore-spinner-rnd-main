SOURCE TEXT UPLOADED: {!! $title !!}, {!! $author !!}, {!! $year !!}, {!! $format !!}

CRITICAL: This phase receives the FULL ORIGINAL source, NOT the trimmed version produced by the IP Trimming Agent. Voice extraction requires the complete range of the author's writing.

PHASE 1 IP AUDIT (for context only, not source material for extraction):
{!! json_encode($ipAudit, JSON_PRETTY_PRINT) !!}

FORMAT DETECTION:
{!! json_encode($formatDetection, JSON_PRETTY_PRINT) !!}

---

FULL ORIGINAL SOURCE TEXT:

{!! $sourceText !!}
