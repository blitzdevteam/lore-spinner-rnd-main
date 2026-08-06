SOURCE IP: {{ $title }}, {{ $author }}, {{ $year }}, {{ $format }}

FORMAT DETECTION:
{{ json_encode($formatDetection, JSON_PRETTY_PRINT) }}

PHASE 1 AUDIT (scorecard: constitutional input):
{{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

---

You are synthesizing the complete Voice DNA Profile from per-chapter observation fragments. Each chapter was analysed independently. Produce ONE unified profile matching Deliverable 1A (NOVEL) or 1B (SCREENPLAY) based on detected_format.

Total chapters processed: {{ $totalChapters }}

PER-CHAPTER VOICE OBSERVATION FRAGMENTS (in chapter order):

@foreach($voiceFragments as $i => $fragment)
--- CHAPTER {{ $i + 1 }} (Chapter ID: {{ $fragment['chapter_id'] ?? '?' }}) ---
{{ json_encode($fragment, JSON_PRETTY_PRINT) }}

@endforeach

---

SYNTHESIS INSTRUCTIONS:

1. Set profile_type to NOVELIST or SCREENWRITER based on format detection.

2. SIGNATURE WRITING TECHNIQUES (8-12): merge across chapters; prefer techniques appearing in multiple chapters; include frequency.

3. SENTENCE-LEVEL PATTERNS / ACTION LINE METRICS: synthesize coherent metrics from all fragments.

4. DICTION FINGERPRINT: merge vocabulary clusters and distinctive quotes.

5. NOVELIST ONLY (1A v2): narrator_perspective, paragraph_architecture, dialogue_tag_patterns. Also synthesize documented_narrator_techniques across all chapter fragments into a consolidated carve-out list — any technique listed by any chapter is permitted for this IP and must be carved out of master_rule_1_hard_bans and anchor_card.

6. SCREENWRITER ONLY (1B v3): action_line_metrics, screenplay_structure_metrics, emotional_vocabulary_hierarchy.

   Then populate the following 1B v3 fields using the Chunk Metric Aggregation Contract:

   **1C AGGREGATION STEPS (required, do not skip):**

   Step 1: SUM raw counts across all chapter fragments:
   - Sum all metric_counts.action_line_count, metric_counts.action_line_word_count, etc.
   - Sum all metric_counts.punctuation_counts.* fields
   - Sum all metric_counts.line_length_bucket_counts.* fields (1_3w, 4_5w, 6_8w, 9_12w, 13_18w, 19_25w, 26_plus_w)
   - Sum all metric_counts.opener_type_counts.* fields
   - Sum all metric_counts.word_length_bucket_counts.* fields
   - Sum all metric_counts.rhythm_transition_matrix_counts.*.* (all 16 cells)
   - Add inter-chapter boundary transitions: for each consecutive pair of chapters, add the transition from chapter[N].metric_counts.last_action_line_bucket → chapter[N+1].metric_counts.first_action_line_bucket to the corresponding matrix cell.
   - Concatenate dialogue_speech_lengths_by_character[].speech_lengths_w per character across all chunks.

   Step 2: DERIVE percentages and enforcement specs from summed totals:
   - Percentages: (summed count / summed denominator) × 100
   - Never average percentages across chunks.
   - Zero-occurrence ABSOLUTE bans: only if summed count is 0 across ALL chunks AND denominator covers full source.
   - Dialogue AVG/P90/P95/MAX: compute from combined speech_lengths_w array per character.

   Step 3: Produce 1B v3 enforcement fields:

   M. `author_voice_dna_profile.numerical_enforcement_layer`:
   - punctuation enforcement (period_density_per_100w, comma_density_per_100w, semicolons, exclamation_marks_narration, em_dashes, question_marks_narration, question_marks_dialogue, ellipses_narration, ellipses_dialogue, period_to_comma_ratio): target/floor/ceiling/confidence/sample_size each
   - rhythm enforcement (sentence_length_1_3w through sentence_length_26_plus_w, fragment_rate, verb_first_percentage, ing_opening_percentage, rhythm_change_frequency): target/floor/ceiling/confidence/sample_size each
   - dialogue_ceilings_per_character — AVG/P90/P95/MAX per character from combined speech_lengths_w
   - opener_distribution — target/floor/ceiling/confidence per opener type
   - word_length — average_chars + bucket percentages

   N. `author_voice_dna_profile.rhythm_transition_architecture`:
   - transition_matrix: 4×4 grid with probabilities (%) derived from summed rhythm_transition_matrix_counts (ultra_short/short/medium/long axes)
   - rhythm_change_frequency: % consecutive action lines that change bucket
   - max_consecutive_same_category: observed max
   - signature_moves: 2-3 characteristic transitions with evidence
   - anti_patterns: transitions writer never or rarely makes

   O. `author_voice_dna_profile.beat_architecture_protocol`:
   - beat_frequency: beat_count / action_line_count × 100
   - beat_vocabulary: status_beats, action_beats, transition_beats, emphasis_beats (from beat_candidates)
   - beat_placement: where beats appear from placement_context evidence
   - beat_density_by_context: cluster pattern across scene types

   P. `author_voice_dna_profile.scene_transition_compression_protocol`:
   - closing_line_avg_length: scene_closing_word_count / scene_closing_line_count
   - closing_line_type_distribution: percentages from scene_closing_type_counts.*
   - closing_line_examples: from scene_closing_samples (8-10 examples)
   - transition_guidance: how runtime narrator should end scenes

   `author_voice_dna_profile.screenplay_to_prose_protocol`:
   - element_rules[]: { screenplay_element, prose_translation_rule } | 7 element rows (action line, scene heading, CUT TO/transition, parenthetical, character cue, ALL CAPS, (beat))
   - quantitative_translation_mappings[]: { screenplay_metric, source_value, prose_target, drift_ceiling, rationale } | minimum 6 entries (fragment rate, period density, comma density, avg line length, -ing openings, max speech length per character)

   TOP-LEVEL `voice_decay_prevention_protocol` (NOT inside author_voice_dna_profile: LEGACY FIELD, preserved if present, not required for V2.3 profiles):
   - re_anchoring_trigger: word-count trigger (e.g., "Every 300-400 words of generated prose")
   - passage_level_enforcement_checks[]: deterministic checks before delivering any passage
   - drift_detection_metrics[]: metrics to track across consecutive passages

   **VERIFICATION GATE: INTERNAL SELF-CHECK ONLY.**
   Before finalizing, internally verify: does the profile produce clearly author-specific prose vs. generic prose? Does the Numerical Enforcement Layer have at least 3 ABSOLUTE bans? Is the 4×4 rhythm transition matrix complete?
   Do NOT include the 200-word test passage, generic comparison passage, or any verification prose in the final JSON output. Return structured JSON only.

7. DIALOGUE FINGERPRINT PER MAJOR CHARACTER: merge cross-chapter observations; require 3+ distinguishing markers per character.

8. EMOTIONAL RANGE MAP: strongest quote per register across all chapters.

9. COLLOCATION FINGERPRINT: deduplicate and expand to 15-20 pairs from chapter collocation_candidates.

10. NEGATIVE SPACE MAP: deduplicate to minimum 5 entries from chapter negative_space_candidates.

11. SHOW/EXPLAIN RATIO and COMPARATIVE EXCLUSION (2-3 neighbors): synthesize from full fragment set.

12. MASTER RULE 1: universal_bans_acknowledged true; 6-10 IP-specific bans from ban candidates.

13. BUILD-TIME QA PROTOCOL (1B v3 Task 6 / 1A v2 Task 3: BOTH formats):
    For SCREENWRITER: Produce quantitative_checks[] (8+ items: fragment/sentence-length distribution, ABSOLUTE punctuation bans, longest-speech ceiling, signature-technique frequency drift, decay test procedure) and judgment_checks[] (blind attribution, comparative exclusion, character differentiation swap test) and decay_test_procedure (how to compare first/last 200 words of a 600+ word continuous sample).
    For NOVELIST: Produce exactly 14 audit points per the 1A v2 14-point list (points 7 Paragraph Architecture and 11 Narrator Compliance must be novelist-specific), plus decay_test_procedure. Map the 14 points to quantitative_checks[] and judgment_checks[] as appropriate.
    This field does NOT ship to runtime. It is a pre-launch QA gate only.

14. TOP-LEVEL `voice_anchor[]` (BOTH formats: 1B v3 Task 3 / 1A v2 Task 4 | ★ RUNTIME-CRITICAL):
    Synthesize from voice_anchor_candidates across all chapter fragments. Select and refine 6–8 exemplars spanning the required modes (screenwriter: cold tension/forward pressure, physical action, quiet/aftermath, environmental establishing, dialogue-bearing, emotional weight without naming emotion; novelist: atmosphere/establishing, rising tension, quiet/reflective beat, dialogue-bearing, action/event, author's most characteristic emotional register). Each exemplar: {mode, source, techniques, prose (90–150 words, second-person present-tense)}. NOVELIST: convert POV+tense only — near-verbatim author words; SCREENWRITER: translate from screenplay form per 1B v3. These exemplars are loaded VERBATIM into the runtime narrator prompt and are the LAST voice material cut under token pressure. Obey every ban (minus novelist carve-outs). The set must pass the self-validation gate from the system prompt.
    Each exemplar MUST include an optional `session_relevance` field: `"session_1_primary"` | `"session_2_primary"` | ... | `"all_sessions"` | `"later_session"`. Tag early-session exemplars (those drawn from the IP's opening chapters or that establish baseline voice) as `"session_1_primary"`. Tag exemplars from the IP's mid-to-late sections that presuppose reader familiarity as `"later_session"`. All others get `"all_sessions"`. The set MUST include at least one `"session_1_primary"` or `"all_sessions"` exemplar — never a set composed entirely of `"later_session"` entries.

15. TOP-LEVEL `anchor_card[]` (BOTH formats: 1B v3 Task 4 / 1A v2 Task 5 | ★ RUNTIME-CRITICAL):
    Synthesize from anchor_card_candidates across all chapter fragments. Produce 8–12 binary/local commands from ABSOLUTE/HIGH-confidence patterns confirmed across the corpus. NOVELIST: honor documented narrator technique carve-outs — the card states the author's actual pattern, not a blanket ban. Each must be BOTH discretely checkable AND supported by aggregated evidence (not chapter-level guesses). Phrase as direct actions, not statistics.
    Ordering rule: if the aggregated evidence confirms em-dashes are ABSOLUTE-zero or near-zero for this IP, the first item in `anchor_card[]` MUST be the punctuation-ban command (search and delete em-dash characters and double-hyphens). This guarantees it is the first check applied at runtime.

16. TOP-LEVEL `runtime_self_check[]` (BOTH formats: 1B v3 Task 5 / 1A v2 Task 6 | ★ RUNTIME-CRITICAL):
    Synthesize from self_check_candidates. Produce a tight ordered sequence of 8–10 discrete/local check steps (use the 10-step template from the system prompt's Task 5/6, populated with this IP's specifics from anchor_card). No rate computations.
    Required steps (in order): (1) punctuation search; (2) cognitive lead-in search; (3) banned-phrase/mold search; (4) AI phrase-tell search ("something in," "the kind of," "couldn't quite," "there was a quality to," "it occurred to [pronoun]"); (5) IP-specific substitute search; (6) opener-repetition scan; (7+) ceiling/dialogue/exemplar/ending checks from the template. Ending check: end on a live image, gesture, or status condition — never a closing question to the player (runtime AGENCY HANDOFF carries agency via suggestions).
    NOVELIST: step 1 must handle em-dashes per the author's documented pattern (not a blanket delete); step 2 and step 4 must respect documented carve-outs.
    The narrator runs this silently before delivering each passage.

Return the complete voice profile matching the full required schema.
