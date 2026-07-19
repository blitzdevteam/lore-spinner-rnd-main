<?php

declare(strict_types=1);

use App\Ai\Adaptation\BoilerDraftValidator;
use App\Ai\Adaptation\BoilerFinalCutValidator;
use App\Models\Event;
use App\Models\SessionAdaptation;
use Illuminate\Database\Eloquent\Collection;

function boilerTestSession(): SessionAdaptation
{
    return (new SessionAdaptation())->forceFill([
        'session_number' => 1,
        'entry_point_diagnosis' => ['start_event_id' => 101],
        'session_architecture' => ['boiler_budget_alignment' => ['aligned' => true]],
        'session_choice_design' => [
            'branching_choices' => [[
                'choice_id' => 'S1_C1',
                'moment_key' => 'S1_M01',
                'options' => [
                    ['label' => 'A'],
                    ['label' => 'B'],
                    ['label' => 'C'],
                ],
            ]],
            'emotional_choices' => [],
        ],
        'session_close_design' => ['closing_moment_key' => 'S1_M01'],
    ]);
}

function boilerTestEvents(): Collection
{
    return new Collection([
        (new Event())->forceFill(['id' => 101, 'session_number' => 1]),
    ]);
}

function boilerTestDraft(): array
{
    return [
        'boiler_version' => '1.0',
        'session_number' => 1,
        'playable_promise' => 'Choose whether the shard deserves trust.',
        'pacing_guidance_used' => [
            'compaction_level' => 'balanced',
            'target_playtime_minutes' => 20,
            'recommended_moment_count_min' => 1,
            'recommended_moment_count_max' => 1,
        ],
        'session_budget_decision' => [
            'target_playtime_minutes' => 20,
            'target_moment_count' => 1,
            'budget_reasoning' => 'One concentrated room preserves the pressure.',
            'must_compress_event_ids' => [],
            'must_breathe_event_ids' => [101],
        ],
        'playable_promise_arc' => [
            'first_proof_moment' => 'S1_M01',
            'midpoint_intensification' => 'S1_M01',
            'final_cash_out' => 'S1_M01',
        ],
        'event_role_map' => [[
            'event_id' => 101,
            'role' => 'PLAY',
            'why' => 'The object creates immediate commitment pressure.',
            'included_moment_key' => 'S1_M01',
            'reintroduction_note' => '',
        ]],
        'moment_drafts' => [[
            'moment_key' => 'S1_M01',
            'moment_order' => 1,
            'source_event_ids' => [101],
            'anchor_event_id' => 101,
            'treatment' => 'PLAY',
            'runtime_goal' => 'Force a meaningful commitment about the shard.',
            'player_desire' => 'Understand the residue.',
            'live_pressure' => 'Ilya is waiting for an answer.',
            'player_affordances' => ['Inspect', 'Question', 'Refuse'],
            'canon_must_keep' => ['The shard remains unresolved.'],
            'allowed_variance' => ['The player may conceal or question it.'],
            'forbidden_expansions' => ['The larger conflict cannot resolve here.'],
            'source_extracts' => ['White light. Chrome ribs.'],
            'exit_condition_draft' => 'The room ends once the player meaningfully commits toward the shard or changes the pressure enough to make that commitment obsolete.',
        ]],
        'cut_material' => [],
        'coverage_check' => [
            'every_event_has_one_role' => true,
            'all_required_canon_routed' => true,
            'first_agency_is_early' => true,
        ],
    ];
}

function boilerTestStoryMap(): array
{
    return [
        'boiling_profile' => [
            'per_session_guidance' => [[
                'session_number' => 1,
                'target_playtime_minutes' => 20,
                'recommended_moment_count_min' => 1,
                'recommended_moment_count_max' => 1,
                'must_compress_event_ids' => [],
                'must_breathe_event_ids' => [101],
            ]],
        ],
    ];
}

it('accepts a structurally valid Draft with an outcome-based semantic exit', function () {
    $session = boilerTestSession();
    $session->boiler_draft = boilerTestDraft();

    (new BoilerDraftValidator())->validate(
        boilerTestDraft(),
        $session,
        boilerTestStoryMap(),
        boilerTestEvents(),
    );

    expect(true)->toBeTrue();
});

it('rejects protected breathing-room material cut without reintroduction', function () {
    $draft = boilerTestDraft();
    $draft['event_role_map'][0]['role'] = 'CUT';
    $draft['event_role_map'][0]['included_moment_key'] = '';

    (new BoilerDraftValidator())->validate(
        $draft,
        boilerTestSession(),
        boilerTestStoryMap(),
        boilerTestEvents(),
    );
})->throws(RuntimeException::class);

it('rejects an event assigned to more than one moment', function () {
    $draft = boilerTestDraft();
    $draft['session_budget_decision']['target_moment_count'] = 2;
    $draft['pacing_guidance_used']['recommended_moment_count_min'] = 2;
    $draft['pacing_guidance_used']['recommended_moment_count_max'] = 2;
    $draft['moment_drafts'][] = array_replace($draft['moment_drafts'][0], [
        'moment_key' => 'S1_M02',
        'moment_order' => 2,
    ]);
    $draft['playable_promise_arc']['final_cash_out'] = 'S1_M02';
    $storyMap = boilerTestStoryMap();
    $storyMap['boiling_profile']['per_session_guidance'][0]['recommended_moment_count_min'] = 2;
    $storyMap['boiling_profile']['per_session_guidance'][0]['recommended_moment_count_max'] = 2;

    (new BoilerDraftValidator())->validate(
        $draft,
        boilerTestSession(),
        $storyMap,
        boilerTestEvents(),
    );
})->throws(RuntimeException::class, 'Boiler event 101 is assigned to more than one moment.');

it('rejects playable promise arc shorthand instead of an exact drafted key', function () {
    $draft = boilerTestDraft();
    $draft['playable_promise_arc']['first_proof_moment'] = 'M01';

    (new BoilerDraftValidator())->validate(
        $draft,
        boilerTestSession(),
        boilerTestStoryMap(),
        boilerTestEvents(),
    );
})->throws(
    RuntimeException::class,
    'Boiler Draft playable_promise_arc.first_proof_moment must resolve to a drafted moment.',
);

it('requires relationally sequential next moment evidence in final cards', function () {
    $session = boilerTestSession();
    $session->boiler_draft = boilerTestDraft();
    $finalCut = [
        'boiler_version' => '1.0',
        'session_number' => 1,
        'cut_key' => 'standard',
        'playable_promise' => 'Choose whether the shard deserves trust.',
        'moments' => [[
            'moment_key' => 'S1_M01',
            'moment_order' => 1,
            'title' => 'The Shard',
            'source_event_ids' => [101],
            'anchor_event_id' => 101,
            'treatment' => 'PLAY',
            'runtime_goal' => 'Force a meaningful commitment about the shard.',
            'player_desire' => 'Understand the residue.',
            'live_pressure' => 'Ilya is waiting.',
            'opening_image' => 'White light across chrome ribs.',
            'player_affordances' => ['Inspect'],
            'canon_must_keep' => ['The shard remains unresolved.'],
            'allowed_variance' => ['Conceal or question it.'],
            'forbidden_expansions' => ['Do not resolve the larger conflict.'],
            'source_extracts' => ['White light. Chrome ribs.'],
            'choice_ids' => ['S1_C1'],
            'word_budget' => ['target_per_turn' => 120],
            'turn_budget' => ['target_turns' => 2, 'gravity_after' => 3],
            'exit_condition' => 'The room ends once the player meaningfully commits toward the shard or changes the pressure enough to make that commitment obsolete.',
            'next_moment_key' => 'S1_M99',
            'next_bridge' => 'Carry the commitment into the next pressure.',
        ]],
        'event_role_map' => [[
            'event_id' => 101,
            'primary_role' => 'PLAY',
            'included_moment_key' => 'S1_M01',
            'why' => 'Immediate commitment pressure.',
            'reintroduction_note' => '',
        ]],
    ];

    (new BoilerFinalCutValidator())->validate($finalCut, $session, boilerTestEvents());
})->throws(RuntimeException::class);
