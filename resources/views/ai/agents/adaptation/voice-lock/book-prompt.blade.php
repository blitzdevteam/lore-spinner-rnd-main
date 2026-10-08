SOURCE IP: {!! $title !!}, {!! $author !!}, {!! $year !!}, {!! $format !!}

FORMAT DETECTION:
{!! json_encode($formatDetection, JSON_PRETTY_PRINT) !!}

PHASE 1 AUDIT (scorecard: constitutional input):
{!! json_encode($ipAudit, JSON_PRETTY_PRINT) !!}

---

You are building the complete Voice DNA Profile (Deliverable 1A, NOVEL) from the WHOLE BOOK below, read in one pass. Every chapter is here, in order. Judge patterns, frequencies and exemplars against the entire book, not a part of it.

MEASURED PROSE STATISTICS (exact counts computed over all {!! $chapterCount !!} chapters; dialogue is text inside quotation marks, narration is everything else). These are facts, not estimates. Use them for every quantitative field (average sentence length, sentence-length distribution, said percentage, punctuation frequencies, dialogue share) and for zero-occurrence bans: a mark at 0 per 1000 words in narration is absent from the author's narration.
{!! json_encode($metrics, JSON_PRETTY_PRINT) !!}

SYNTHESIS REQUIREMENTS:

1. Set profile_type to NOVELIST.

2. SIGNATURE WRITING TECHNIQUES (8-12): the techniques that recur across the book, each with its frequency and quotes from different parts of the book.

3. SENTENCE-LEVEL PATTERNS: state the measured figures and describe the patterns they reflect.

4. DICTION FINGERPRINT: vocabulary clusters and distinctive quotes from across the book.

5. NARRATOR (1A v2): narrator_perspective, paragraph_architecture, dialogue_tag_patterns (said_percentage from the measured statistics). Also list documented_narrator_techniques: every technique the author actually uses that a ban would otherwise forbid (cognitive verbs, free indirect discourse, prolepsis, direct address). Each listed technique is carved out of master_rule_1_hard_bans and anchor_card.

6. DIALOGUE FINGERPRINT PER MAJOR CHARACTER: every major character of the whole book, with 3+ distinguishing markers each and quotes from their own lines.

7. EMOTIONAL RANGE MAP: the strongest quote per register from anywhere in the book.

8. COLLOCATION FINGERPRINT: 15-20 characteristic word pairs.

9. NEGATIVE SPACE MAP: minimum 5 genre-default techniques this author never uses.

10. SHOW/EXPLAIN RATIO and COMPARATIVE EXCLUSION (2-3 stylistic neighbours).

11. MASTER RULE 1: universal_bans_acknowledged true; 6-10 IP-specific bans grounded in the book and the measured statistics.

12. BUILD-TIME QA PROTOCOL: exactly 14 audit points per the 1A v2 14-point list (points 7 Paragraph Architecture and 11 Narrator Compliance novelist-specific), mapped to quantitative_checks[] and judgment_checks[], plus decay_test_procedure. This field does not ship to runtime.

13. TOP-LEVEL `voice_anchor[]` (★ RUNTIME-CRITICAL): 6-8 exemplars chosen from across the whole book, spanning atmosphere/establishing, rising tension, quiet/reflective beat, dialogue-bearing, action/event, and the author's most characteristic emotional register. Each exemplar: {mode, source, techniques, prose (90-150 words, second-person present-tense), session_relevance}. Convert POV and tense only: the author's own words, near-verbatim. These are loaded verbatim into the runtime narrator prompt. Obey every ban (minus documented carve-outs).
    In every exemplar, "you" is the protagonist. Never convert a passage so that "you" becomes a different character; pick another passage instead.
    `session_relevance` is its own JSON property, never appended to the prose: `"session_1_primary"` for exemplars from the opening chapters or that establish baseline voice, `"later_session"` for mid-to-late passages that presuppose familiarity, `"all_sessions"` otherwise. Include at least one `"session_1_primary"` or `"all_sessions"` exemplar.

14. TOP-LEVEL `anchor_card[]` (★ RUNTIME-CRITICAL): 8-12 binary, locally checkable commands supported by the whole book and the measured statistics. Honour documented narrator carve-outs: state the author's actual pattern, not a blanket ban. Phrase as direct actions, not statistics. If narration em dashes measure at or near zero, the first item is the punctuation-ban command (search and delete em-dash characters and double hyphens).

15. TOP-LEVEL `runtime_self_check[]` (★ RUNTIME-CRITICAL): an ordered sequence of 8-10 discrete check steps, in this order: (1) punctuation search, handling em dashes per the author's measured pattern; (2) cognitive lead-in search, respecting carve-outs; (3) banned-phrase and mold search; (4) AI phrase-tell search ("something in," "the kind of," "couldn't quite," "there was a quality to," "it occurred to [pronoun]"), respecting carve-outs; (5) IP-specific substitute search; (6) opener-repetition scan; (7+) ceiling, dialogue, exemplar and ending checks. Ending check: end on a live image, gesture, or status condition, never a closing question to the player. No rate computations.

Return the complete voice profile matching the full required schema.

---

THE COMPLETE BOOK ({!! $chapterCount !!} chapters, in order):

@foreach($chapters as $chapter)
===== CHAPTER {!! $chapter['position'] !!}: {!! $chapter['title'] !!} =====
{!! $chapter['content'] !!}

@endforeach
