<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use App\Ai\Agents\Adaptation\Concerns\SetsOpenAiReasoningEffort;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

/**
 * Pipeline Upgrade V2.3 — Entry Point Diagnosis (Deliverable 10).
 *
 * Produces six structured outputs:
 *   Task 1: entry_point (chosen source moment, rubric scores, adjustment, cut point)
 *   Task 2: protagonist_introduction (earned identity approach)
 *   Task 3: situation_stakes_world (economy — established + deferred)
 *   Task 4: first_choice_spec ★ consumed by ChoiceDesignJob / D4 Task 1
 *   Task 5: cold_open prose (→ D8 Section 13 via ChaosEngineService)
 *   Task 6: emotional_promise (→ ChoiceDesignJob / D4)
 *
 * Backward-compatible runtime keys preserved: editorial_diagnosis,
 * format_specific_cut, cold_open, emotional_promise, start_event_position.
 */
#[Model('gpt-6.1-sol')]
#[Timeout(180)]
final class EntryPointDiagnosisAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, SetsOpenAiReasoningEffort;

    private const string REASONING_EFFORT = 'medium';

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.entry-point-diagnosis.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            // --- Task 1: Entry Point (D10) ---
            'entry_point' => $schema->object([
                'chosen_moment' => $schema->string()->required()->title('Chosen Moment')
                    ->description('The source moment selected as the entry point.'),
                'rubric_scores' => $schema->string()->required()->title('Rubric Scores')
                    ->description('Body/tension/threshold/exposition-debt/core-stakes-proximity — one line each.'),
                'entry_point_adjustment' => $schema->string()->required()->title('Entry Point Adjustment')
                    ->description('"literal opening" or "moved forward to: ___ — and why."'),
                'cut_point' => $schema->string()->required()->title('Cut Point')
                    ->description('Where the cold open ends and hands off to Phase 4 SETUP beat.'),
            ])->required()->withoutAdditionalProperties()->title('Entry Point — Task 1'),

            // --- Task 2: Protagonist Introduction (D10) ---
            'protagonist_introduction' => $schema->object([
                'identity_reveal_approach' => $schema->string()->required()->title('Identity Reveal Approach')
                    ->description('Body-first beat, weight-moment for the name, reflex/instinct that conveys role, one loaded detail.'),
                'reveal_lines' => $schema->string()->required()->title('Reveal Lines')
                    ->description('The actual in-voice line(s) that name the protagonist at a moment of weight.'),
                'resume_dump_check' => $schema->string()->required()->title('Resume Dump Check')
                    ->description('Confirmation that no stacked-label exposition is used.'),
            ])->required()->withoutAdditionalProperties()->title('Protagonist Introduction — Task 2'),

            // --- Task 3: Situation, Stakes, World (D10) ---
            'situation_stakes_world' => $schema->object([
                'established' => $schema->string()->required()->title('Established')
                    ->description('Where/when, the pressing now, 1-2 active world rules, the stake — as present-tense pressure.'),
                'deferred' => $schema->string()->required()->title('Deferred')
                    ->description('What is intentionally withheld for later beats.'),
            ])->required()->withoutAdditionalProperties()->title('Situation Stakes World — Task 3'),

            // --- Task 4: First-Choice Spec ★ (D10 → consumed by D4 Task 1) ---
            'first_choice_spec' => $schema->object([
                'setup_prose' => $schema->string()->required()->title('Setup Prose')
                    ->description('2-3 sentences of cold-open prose immediately before the question — in the author\'s voice.'),
                'threshold_stake' => $schema->string()->required()->title('Threshold/Stake')
                    ->description('The core stake or threshold this choice turns on — tied to protagonist\'s central want/threat.'),
                'question' => $schema->string()->required()->title('Question')
                    ->description('The choice question in second person.'),
                'option_1' => $schema->string()->required()->title('Option 1')
                    ->description('One sentence — a genuine human value.'),
                'option_1_alignment' => $schema->string()->required()->title('Option 1 Alignment')
                    ->description('chaotic | lawful | neutral'),
                'option_1_tracks' => $schema->string()->required()->title('Option 1 Tracks')
                    ->description('Branch dimension from Phase 2 this option tracks.'),
                'option_1_value' => $schema->string()->required()->title('Option 1 Value')
                    ->description('The human value this option represents.'),
                'option_2' => $schema->string()->required()->title('Option 2')
                    ->description('One sentence — a genuine human value.'),
                'option_2_alignment' => $schema->string()->required()->title('Option 2 Alignment')
                    ->description('chaotic | lawful | neutral'),
                'option_2_tracks' => $schema->string()->required()->title('Option 2 Tracks')
                    ->description('Branch dimension from Phase 2 this option tracks.'),
                'option_2_value' => $schema->string()->required()->title('Option 2 Value')
                    ->description('The human value this option represents.'),
                'option_3_unexpected' => $schema->string()->required()->title('Option 3 (Unexpected)')
                    ->description('The third path nobody expects — one sentence.'),
                'option_3_alignment' => $schema->string()->required()->title('Option 3 Alignment')
                    ->description('chaotic | lawful | neutral'),
                'option_3_tracks' => $schema->string()->required()->title('Option 3 Tracks')
                    ->description('Branch dimension from Phase 2 this option tracks.'),
                'option_3_value' => $schema->string()->required()->title('Option 3 Value')
                    ->description('The human value this option represents.'),
                'not_a_tutorial' => $schema->string()->required()->title('Not a Tutorial')
                    ->description('One line: how this choice engages core stakes and defines identity — not a side encounter.'),
            ])->required()->withoutAdditionalProperties()->title('First-Choice Spec — Task 4 ★ consumed by D4 Task 1'),

            // --- Format-specific cut (moved before cold_open so must_reintroduce is in context when cold_open is generated) ---
            'format_specific_cut' => $schema
                ->object([
                    'cut_point' => $schema->string()->required()->title('Cut Point'),
                    'original_before_cut' => $schema->string()->required()->title('Original Before Cut'),
                    'cut_eliminates' => $schema->string()->required()->title('Cut Eliminates'),
                    'must_reintroduce' => $schema
                        ->array()
                        ->required()
                        ->title('Must Reintroduce')
                        ->description('List every essential fact the player needs to understand who they are and why this moment matters, but that was cut by starting at a later event. Each item is a structured object — not a flat text block. The structure lets the runtime track which facts have landed and inject only pending ones at the right moment.')
                        ->items(
                            $schema->object([
                                'fact' => $schema->string()->required()->title('Fact')
                                    ->description('The specific piece of information the player cannot engage without. Not "backstory" — the exact fact and why it matters.'),
                                'marker' => $schema->string()->required()->title('Marker')
                                    ->description('COLD_OPEN only if the cold open prose itself states this fact. LATER if it surfaces during the session, which includes every fact about an event a scene card stages. A COLD_OPEN fact is injected into the opening turn, so marking a card-staged event COLD_OPEN makes the narrator tell it twice.'),
                                'target_moment_key' => $schema->string()->required()->title('Target Moment Key')
                                    ->description('The moment key where this fact should ideally resurface (e.g. S1_M02). Use the earliest moment where the fact becomes dramatically relevant. Empty string if it has no specific target — the narrator weaves it whenever the opportunity arises.'),
                                'key_terms' => $schema
                                    ->array()
                                    ->required()
                                    ->title('Key Terms')
                                    ->description('2-4 distinctive words from the fact that the runtime uses to detect when the narrator has woven it in. These should be nouns, names, or specific terms — not function words.')
                                    ->items($schema->string()->required()),
                            ])->required()->withoutAdditionalProperties()->title('Reintroduction Item')
                        ),
                ])->required()->withoutAdditionalProperties()->title('Format-Specific Cut'),

            // --- Task 5: Cold Open prose (Task 5 / D8 Section 13) ---
            // IMPORTANT: This field is filled AFTER protagonist_introduction, first_choice_spec, and
            // format_specific_cut have already been committed. Treat this as composition, not fresh writing.
            // The material already exists above — place it, do not paraphrase it.
            'cold_open' => $schema
                ->string()
                ->required()
                ->title('Cold Open')
                ->description(
                    'Second-person present tense cold open prose, 120-180 words, in the author\'s voice. This is the player\'s first impression and it ships to them exactly as written here — it is performed verbatim at runtime, never re-written by the narrator.'."\n\n".
                    'PLAYER-FACING PROSE ONLY. No banner or fence lines (never "=== COLD OPEN: ... ===" or any "===" marker), no title, no session label, no cut-point line, no bracketed placeholders, no editorial notes. The field begins with the first word the player reads and ends on the live action the first choice answers. The choice question itself is shown to the player as buttons, so it never appears in this field. The cut point belongs in entry_point.cut_point.'."\n\n".
                    'REQUIRED FORMULAS (all four, no exceptions, every source format):'."\n".
                    '• BODY DROP-IN: the first clause contains you/your and a physical sensation. No date, year, season, clock time, weather, or place name before the player is in their body.'."\n".
                    '• IDENTITY IN NARRATION: the protagonist is named in the narrator\'s own voice addressed to the player ("You are [NAME]..." or an equivalent with the same weight), at a moment of pressure. A character speaking the name in dialogue does NOT satisfy this.'."\n".
                    '• SECOND-PERSON PRESENT TENSE throughout. No past-tense narration anywhere.'."\n".
                    '• ENDS ON THE FIRST CHOICE\'S ACTION: the final sentence is the live moment the first choice answers, never a question to the player and never a list of options. No stakes-summary after it.'."\n".
                    '• TELLS NOTHING A CARD STAGES: the cold open stops where the first scene card begins. It never narrates an event that a later card will play.'."\n\n".
                    'COMPOSITION — the following material is already designed. Place it; do not re-invent it, and do not paraphrase it into something weaker:'."\n".
                    '• protagonist_introduction.reveal_lines carries the identity beat into the prose at the weight-bearing moment.'."\n".
                    '• first_choice_spec.setup_prose forms the final 2-3 sentences of the cold open.'."\n".
                    '• first_choice_spec.question is NOT written into the cold open. It is shown with the choice.'."\n".
                    '• Any fact in format_specific_cut.must_reintroduce with marker "COLD_OPEN" must be woven into the prose as present-pressure or lived-in knowledge — never as backstory narration.'."\n\n".
                    'You MAY adjust connective wording where these pieces join, and you MUST do so when placing them side by side would repeat a sentence or an image. The cold open is one continuous piece of prose, not three pasted fragments. Before finalizing, re-read the draft for any sentence or clause that appears twice and merge it — a 120-180 word opening cannot afford to say anything a second time.'."\n\n".
                    'Sensory grounding within the first 50 words.'
                ),

            // --- Task 6: Emotional Promise (→ D4) ---
            'emotional_promise' => $schema
                ->string()
                ->required()
                ->title('Emotional Promise')
                ->description('One sentence: "The emotional promise of this cold open is: [NOUN]. A user arrives feeling [ADJECTIVE] and wanting to [VERB]."'),

            // --- Freeform input hint (→ chat-bar placeholder, session 1 first move only) ---
            'freeform_input_hint' => $schema
                ->string()
                ->required()
                ->title('Freeform Input Hint')
                ->description('Max 80 chars. A story-native, present-tense line that appears as the chat-bar placeholder when the player first faces the three choices. Invites a custom move beyond the buttons. Captures the spirit of the unexpected third option without quoting it verbatim. No UI language ("Type here", "Enter text"). No game-mechanical words. Must tempt, not instruct. Written in the world\'s tone.'),

            // --- Preserved runtime keys (backward compat) ---
            'editorial_diagnosis' => $schema
                ->string()
                ->required()
                ->title('Editorial Diagnosis')
                ->description('Full editorial diagnosis explaining what was cut and why (from entry point selection).'),

            'start_event_position' => $schema
                ->integer()
                ->required()
                ->title('Start Event Position')
                ->description('The integer event position number where this session should begin. Must be one of the story-global Event numbers in the events list.'),
        ];
    }
}
