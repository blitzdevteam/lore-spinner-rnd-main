<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

/**
 * Pipeline Upgrade V2 — Deliverable 6: Phase 8 Editorial Verification.
 *
 * Expanded from 10 questions to 23 across three sections:
 *   A. Design Audit (Q1-Q10) — preserved from V1.
 *   B. Voice Audit (Q11-Q16) — NEW. Includes the 14-Point Voice Lock audit.
 *   C. StoryGuard + State Compliance (Q17-Q23) — NEW.
 *
 * Production status thresholds (verbatim from Deliverable 6):
 *   - 23/23 PASS  -> GREEN LIGHT
 *   - 20-22/23    -> AMBER (fix flagged items, re-run failed questions only)
 *   - <20         -> RED (return to flagged phases for full re-run)
 *
 * Per implementation note in #5 docs: a RED verdict triggers one auto-retry of the
 * EditorialVerificationJob; the reconciliation job handles that retry orchestration.
 */
#[Model('gpt-5.4')]
#[Temperature(0.3)]
#[Timeout(240)]
class EditorialVerificationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.editorial-verification.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $questionResultSchema = $schema->object([
            'verdict' => $schema->string()->required()->title('Verdict')->description('PASS or REVISE.'),
            'detail' => $schema->string()->required()->title('Detail')->description('Supporting detail, evidence, or revision instruction.'),
        ])->required()->withoutAdditionalProperties();

        $auditPointResultSchema = $schema->object([
            'point_number' => $schema->number()->required()->title('Point Number')->description('1-14.'),
            'point_name' => $schema->string()->required()->title('Point Name'),
            'verdict' => $schema->string()->required()->title('Verdict')->description('PASS or FLAG.'),
            'location' => $schema->string()->required()->title('Location')->description('Where in the prose the flag appears, or empty string if PASS.'),
        ])->required()->withoutAdditionalProperties();

        return [
            'section_a_design_audit' => $schema->object([
                'q1_entry_point' => (clone $questionResultSchema)->title('Q1 — Entry Point'),
                'q2_emotional_promise' => (clone $questionResultSchema)->title('Q2 — Emotional Promise'),
                'q3_first_choice_timing' => (clone $questionResultSchema)->title('Q3 — First Choice Timing'),
                'q4_consequential_choices' => (clone $questionResultSchema)->title('Q4 — Consequential Choices'),
                'q5_stakes_within_60_seconds' => (clone $questionResultSchema)->title('Q5 — Stakes Within 60 Seconds'),
                'q6_early_decision_visible_later' => (clone $questionResultSchema)->title('Q6 — Early Decision Visible Later'),
                'q7_breath_beat' => (clone $questionResultSchema)->title('Q7 — Breath Beat'),
                'q8_moral_gray_area' => (clone $questionResultSchema)->title('Q8 — Moral Gray Area'),
                'q9_exit_emotional_state' => (clone $questionResultSchema)->title('Q9 — Exit Emotional State'),
                'q10_talkability' => (clone $questionResultSchema)->title('Q10 — Talkability'),
            ])->required()->withoutAdditionalProperties()->title('Section A — Design Audit'),

            'section_b_voice_audit' => $schema->object([
                'q11_hard_ban_scan' => (clone $questionResultSchema)->title('Q11 — Hard Ban Scan'),
                'q12_trailing_simile_scan' => (clone $questionResultSchema)->title('Q12 — Trailing Simile Scan'),
                'q13_sentence_mold_scan' => (clone $questionResultSchema)->title('Q13 — Sentence Mold Scan'),
                'q14_voice_authenticity' => (clone $questionResultSchema)->title('Q14 — Voice Authenticity'),
                'q15_fourteen_point_audit' => (clone $questionResultSchema)->title('Q15 — 14-Point Audit'),
                'q16_diction_fingerprint' => (clone $questionResultSchema)->title('Q16 — Diction Fingerprint'),
                'fourteen_point_audit_results' => $schema->array()->required()->title('14-Point Audit Results')->description('Exactly 14 entries.')->items($auditPointResultSchema),
            ])->required()->withoutAdditionalProperties()->title('Section B — Voice Audit'),

            'section_c_storyguard_and_state' => $schema->object([
                'q17_canon_integrity' => (clone $questionResultSchema)->title('Q17 — Canon Integrity'),
                'q18_character_truth' => (clone $questionResultSchema)->title('Q18 — Character Truth'),
                'q19_world_reactivity' => (clone $questionResultSchema)->title('Q19 — World Reactivity'),
                'q20_persistent_state_consistency' => (clone $questionResultSchema)->title('Q20 — Persistent State Consistency'),
                'q21_world_noticed_signals' => (clone $questionResultSchema)->title('Q21 — World Noticed Signals'),
                'q22_social_echo_defining_lines' => (clone $questionResultSchema)->title('Q22 — Social Echo Defining Lines'),
                'q23_alignment_balance' => (clone $questionResultSchema)->title('Q23 — Alignment Balance'),
            ])->required()->withoutAdditionalProperties()->title('Section C — StoryGuard & State Compliance'),

            'boiler_audit' => $schema->object([
                'event_coverage' => (clone $questionResultSchema)->title('Boiler — Event Coverage'),
                'canon_reintroduction' => (clone $questionResultSchema)->title('Boiler — Canon Reintroduction'),
                'moment_alignment' => (clone $questionResultSchema)->title('Boiler — Moment Alignment'),
                'choice_bindings' => (clone $questionResultSchema)->title('Boiler — Choice Bindings'),
                'consequence_and_close_bindings' => (clone $questionResultSchema)->title('Boiler — Consequence and Close Bindings'),
                'playable_room_quality' => (clone $questionResultSchema)->title('Boiler — Playable Room Quality'),
                'pacing_and_budget_agreement' => (clone $questionResultSchema)->title('Boiler — Pacing and Budget Agreement'),
                'playable_promise_arc' => (clone $questionResultSchema)->title('Boiler — Playable Promise Arc'),
                'semantic_exit_conditions' => (clone $questionResultSchema)->title('Boiler — Semantic Exit Conditions'),
                'experience_balance' => (clone $questionResultSchema)->title('Boiler — Immersion, Consequence, Momentum'),
                'production_status' => $schema->string()->required()->title('Boiler Production Status')
                    ->description('PASS only when every Boiler audit item passes; otherwise REVISE.'),
                'revision_instructions' => $schema->array()->required()->title('Boiler Revision Instructions')
                    ->items($schema->string()->required()),
            ])->withoutAdditionalProperties()->title('Boiler Audit'),

            'final_verdict' => $schema->object([
                'total_passing' => $schema->number()->required()->title('Total Passing')->description('Count of PASS verdicts out of 23.'),
                'production_status' => $schema->string()->required()->title('Production Status')->description('GREEN (23/23), AMBER (20-22/23), or RED (below 20).'),
                'revision_instructions' => $schema->array()->required()->title('Revision Instructions')->description('One entry per REVISE verdict. Empty array if all PASS.')->items(
                    $schema->object([
                        'question_number' => $schema->string()->required()->title('Question Number')->description('e.g. "Q17".'),
                        'phase' => $schema->string()->required()->title('Phase')->description('Phase to return to.'),
                        'instruction' => $schema->string()->required()->title('Instruction'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('Final Verdict'),
        ];
    }
}
