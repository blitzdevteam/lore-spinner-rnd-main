<?php

declare(strict_types=1);

use App\Ai\Adaptation\BoilerDraftCanonicalizer;
use App\Ai\Adaptation\BoilerDraftValidator;
use App\Ai\Adaptation\BoilerFinalCutCanonicalizer;
use App\Ai\Adaptation\BoilerFinalCutValidator;
use App\Ai\Adaptation\ChoiceDesignCanonicalizer;
use App\Ai\Adaptation\ConsequenceMapCanonicalizer;
use App\Ai\Adaptation\ConsequenceMapValidator;
use App\Ai\Adaptation\SessionArchitectureCanonicalizer;
use App\Ai\Adaptation\SessionArchitectureValidator;
use App\Jobs\Adaptation\ChoiceDesignJob;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
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
        'session_close_design' => [
            'closing_moment_key' => 'S1_M01',
            'hook_transition' => 'The resolved pressure turns toward the final authored choice.',
            'next_bridge_guidance' => 'Hand off from the resolution into the session-end decision.',
        ],
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

it('fills empty moment source_event_ids from event_role_map bindings', function () {
    $draft = boilerTestDraft();
    $draft['moment_drafts'][0]['source_event_ids'] = [];

    $canonicalized = (new BoilerDraftCanonicalizer())->canonicalize($draft);

    expect($canonicalized['moment_drafts'][0]['source_event_ids'])->toBe([101]);

    (new BoilerDraftValidator())->validate(
        $canonicalized,
        boilerTestSession(),
        boilerTestStoryMap(),
        boilerTestEvents(),
    );
});

it('rejects empty source_event_ids when role map cannot recover them', function () {
    $draft = boilerTestDraft();
    $draft['moment_drafts'][0]['source_event_ids'] = [];
    $draft['moment_drafts'][0]['anchor_event_id'] = null;
    $draft['event_role_map'][0]['role'] = 'ECHO';
    $draft['event_role_map'][0]['included_moment_key'] = '';
    $draft['event_role_map'][0]['reintroduction_note'] = 'The shard returns through dialogue later.';

    $canonicalized = (new BoilerDraftCanonicalizer())->canonicalize($draft);

    expect($canonicalized['moment_drafts'][0]['source_event_ids'] ?? [])->toBe([]);

    (new BoilerDraftValidator())->validate(
        $canonicalized,
        boilerTestSession(),
        boilerTestStoryMap(),
        boilerTestEvents(),
    );
})->throws(RuntimeException::class, 'Boiler moment S1_M01 requires source_event_ids.');

it('fills empty source_event_ids from the moment anchor when roles do not bind', function () {
    $draft = boilerTestDraft();
    $draft['moment_drafts'][0]['source_event_ids'] = [];
    $draft['event_role_map'][0]['included_moment_key'] = '';
    $draft['event_role_map'][0]['role'] = 'ECHO';
    $draft['event_role_map'][0]['reintroduction_note'] = 'Returns later.';

    // Anchor alone recovers the ID; role map deliberately unbound.
    $canonicalized = (new BoilerDraftCanonicalizer())->canonicalize($draft);

    expect($canonicalized['moment_drafts'][0]['source_event_ids'])->toBe([101]);
});

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

it('overlays Finalize references from their authoritative upstream artifacts', function () {
    $session = boilerTestSession();
    $session->boiler_draft = boilerTestDraft();
    $invalidCut = [
        'boiler_version' => 'wrong',
        'session_number' => 99,
        'cut_key' => 'invented',
        'playable_promise' => 'Changed promise',
        'moments' => [[
            'moment_key' => 'S1_M99',
            'moment_order' => 99,
            'source_event_ids' => [999],
            'anchor_event_id' => 999,
            'choice_ids' => ['S1_C99'],
            'next_moment_key' => 'S1_M99',
        ]],
        'event_role_map' => [],
    ];

    $canonical = (new BoilerFinalCutCanonicalizer())->canonicalize($invalidCut, $session);

    expect($canonical)
        ->boiler_version->toBe('1.0')
        ->session_number->toBe(1)
        ->cut_key->toBe('standard')
        ->playable_promise->toBe(boilerTestDraft()['playable_promise'])
        ->and($canonical['moments'][0])
        ->moment_key->toBe('S1_M01')
        ->moment_order->toBe(1)
        ->source_event_ids->toBe([101])
        ->anchor_event_id->toBe(101)
        ->choice_ids->toBe(['S1_C1'])
        ->next_moment_key->toBe('')
        ->next_bridge->toBe('The resolved pressure turns toward the final authored choice.')
        ->and($canonical['event_role_map'][0])
        ->primary_role->toBe('PLAY')
        ->included_moment_key->toBe('S1_M01');
});

it('demotes a Draft CHOICE role when its moment carries only an emotional choice', function () {
    $session = boilerTestSession();
    $draft = boilerTestDraft();
    $draft['event_role_map'][0]['role'] = 'CHOICE';
    $session->boiler_draft = $draft;

    // The moment owns an emotional choice but none of the four canonical branches,
    // so the finalized role must read PRESSURE rather than CHOICE.
    $session->session_choice_design = [
        'branching_choices' => [],
        'emotional_choices' => [[
            'choice_id' => 'S1_E1',
            'moment_key' => 'S1_M01',
        ]],
    ];

    $canonical = (new BoilerFinalCutCanonicalizer())->canonicalize(
        ['moments' => [], 'event_role_map' => []],
        $session,
    );

    expect($canonical['event_role_map'][0])
        ->primary_role->toBe('PRESSURE')
        ->included_moment_key->toBe('S1_M01');
});

it('keeps the CHOICE role when the moment owns a canonical branching choice', function () {
    $session = boilerTestSession();
    $draft = boilerTestDraft();
    $draft['event_role_map'][0]['role'] = 'CHOICE';
    $session->boiler_draft = $draft;

    $canonical = (new BoilerFinalCutCanonicalizer())->canonicalize(
        ['moments' => [], 'event_role_map' => []],
        $session,
    );

    expect($canonical['event_role_map'][0]->primary_role ?? $canonical['event_role_map'][0]['primary_role'])
        ->toBe('CHOICE');
});

it('fits four canonical branching slots into a short bridge session', function () {
    // A two-event bridge session yields two moments, so one branch per moment is
    // impossible. Slots must share moments instead of failing the budget.
    $draft = boilerTestDraft();
    $draft['moment_drafts'][] = array_replace($draft['moment_drafts'][0], [
        'moment_key' => 'S1_M02',
        'moment_order' => 2,
        'source_event_ids' => [102],
        'anchor_event_id' => 102,
        'runtime_goal' => 'Carry the launch into the next arc.',
    ]);

    $architecture = [
        // The model hedged: it reported a conflict even though the beats fit.
        'boiler_budget_alignment' => ['aligned' => false, 'conflict' => 'Cannot place three non-final branches.'],
        'beat_map' => [
            ['time_range' => '00:00-03:00', 'moment' => 'Open', 'beat_type' => 'SETUP', 'choice_slot' => 'BRANCHING', 'dramatic_function' => 'Identity', 'moment_key' => 'S1_M01'],
            ['time_range' => '03:00-05:00', 'moment' => 'Press', 'beat_type' => 'ESCALATION', 'choice_slot' => 'none', 'dramatic_function' => 'Methodology', 'moment_key' => 'S1_M01'],
            ['time_range' => '05:00-07:00', 'moment' => 'Turn', 'beat_type' => 'ESCALATION', 'choice_slot' => 'none', 'dramatic_function' => 'Moral weight', 'moment_key' => 'S1_M02'],
            ['time_range' => '07:00-09:00', 'moment' => 'Close', 'beat_type' => 'RESOLUTION', 'choice_slot' => 'BRANCHING', 'dramatic_function' => 'Session-end hook', 'moment_key' => 'S1_M02'],
        ],
    ];

    $canonical = (new SessionArchitectureCanonicalizer())->canonicalize($architecture, $draft);
    (new SessionArchitectureValidator())->validate($canonical, $draft);

    $branching = collect($canonical['beat_map'])
        ->filter(fn (array $beat): bool => $beat['choice_slot'] === 'BRANCHING');

    expect($branching)->toHaveCount(4)
        ->and($canonical['boiler_budget_alignment']['aligned'])->toBeTrue()
        ->and($canonical['boiler_budget_alignment']['conflict'])->toBe('')
        ->and($branching->pluck('moment_key')->contains('S1_M02'))->toBeTrue();
});

it('reports a real Architecture conflict instead of forcing alignment', function () {
    // Too few beats to ever host four branches. PHP must not paper over that, even
    // though the model claimed the architecture aligned.
    $draft = boilerTestDraft();
    $architecture = [
        'boiler_budget_alignment' => ['aligned' => true, 'conflict' => ''],
        'beat_map' => [
            ['time_range' => '00:00-03:00', 'moment' => 'Open', 'beat_type' => 'SETUP', 'choice_slot' => 'BRANCHING', 'dramatic_function' => 'Identity', 'moment_key' => 'S1_M01'],
        ],
    ];

    $canonical = (new SessionArchitectureCanonicalizer())->canonicalize($architecture, $draft);

    expect($canonical['boiler_budget_alignment']['aligned'])->toBeFalse()
        ->and($canonical['boiler_budget_alignment']['conflict'])
        ->toContain('BRANCHING slots instead of 4');

    expect(fn () => (new SessionArchitectureValidator())->validate($canonical, $draft))
        ->toThrow(RuntimeException::class);
});

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

it('does not manufacture extra Architecture branches for every Draft CHOICE candidate', function () {
    $draft = boilerTestDraft();
    $draft['moment_drafts'][] = array_replace($draft['moment_drafts'][0], [
        'moment_key' => 'S1_M02',
        'moment_order' => 2,
        'source_event_ids' => [102],
        'anchor_event_id' => 102,
    ]);
    $draft['moment_drafts'][] = array_replace($draft['moment_drafts'][0], [
        'moment_key' => 'S1_M03',
        'moment_order' => 3,
        'source_event_ids' => [103],
        'anchor_event_id' => 103,
    ]);
    $draft['event_role_map'][] = [
        'event_id' => 102,
        'role' => 'CHOICE',
        'why' => 'A load-bearing stealth versus confrontation decision.',
        'included_moment_key' => 'S1_M02',
        'reintroduction_note' => '',
    ];
    $architecture = [
        'boiler_budget_alignment' => ['aligned' => true, 'conflict' => ''],
        'beat_map' => [
            ['time_range' => '00:00-03:00', 'beat_type' => 'SETUP', 'choice_slot' => 'BRANCHING', 'moment_key' => 'S1_M01'],
            ['time_range' => '03:00-07:00', 'beat_type' => 'ESCALATION', 'choice_slot' => 'BRANCHING', 'moment_key' => 'S1_M01'],
            ['time_range' => '10:00-12:30', 'beat_type' => 'BREATH', 'choice_slot' => 'BRANCHING', 'moment_key' => 'S1_M01'],
            ['time_range' => '17:00-20:00', 'beat_type' => 'RESOLUTION', 'choice_slot' => 'BRANCHING', 'moment_key' => 'S1_M03'],
        ],
    ];

    (new SessionArchitectureValidator())->validate($architecture, $draft);

    expect(true)->toBeTrue();
});

it('anchors the final Architecture branch without relocating other slots from candidate roles', function () {
    $draft = boilerTestDraft();
    $draft['moment_drafts'][] = array_replace($draft['moment_drafts'][0], [
        'moment_key' => 'S1_M02',
        'moment_order' => 2,
        'source_event_ids' => [102],
        'anchor_event_id' => 102,
        'runtime_goal' => 'Choose stealth or direct confrontation.',
    ]);
    $draft['moment_drafts'][] = array_replace($draft['moment_drafts'][0], [
        'moment_key' => 'S1_M03',
        'moment_order' => 3,
        'source_event_ids' => [103],
        'anchor_event_id' => 103,
        'runtime_goal' => 'Commit to what happens next.',
    ]);
    $draft['event_role_map'][] = [
        'event_id' => 102,
        'role' => 'CHOICE',
        'why' => 'A load-bearing stealth versus confrontation methodology decision.',
        'included_moment_key' => 'S1_M02',
        'reintroduction_note' => '',
    ];
    $architecture = [
        'boiler_budget_alignment' => ['aligned' => true, 'conflict' => ''],
        'beat_map' => [
            ['time_range' => '00:00-03:00', 'moment' => 'Identity', 'beat_type' => 'SETUP', 'choice_slot' => 'BRANCHING', 'dramatic_function' => 'Identity and trust', 'moment_key' => 'S1_M01'],
            ['time_range' => '03:00-07:00', 'moment' => 'Approach', 'beat_type' => 'ESCALATION', 'choice_slot' => 'BRANCHING', 'dramatic_function' => 'Methodology: stealth or confrontation', 'moment_key' => 'S1_M01'],
            ['time_range' => '10:00-12:30', 'moment' => 'Moral', 'beat_type' => 'BREATH', 'choice_slot' => 'BRANCHING', 'dramatic_function' => 'Moral pressure', 'moment_key' => 'S1_M01'],
            ['time_range' => '17:00-20:00', 'moment' => 'Commitment', 'beat_type' => 'RESOLUTION', 'choice_slot' => 'BRANCHING', 'dramatic_function' => 'Future commitment', 'moment_key' => 'S1_M01'],
        ],
    ];

    $canonical = (new SessionArchitectureCanonicalizer())->canonicalize($architecture, $draft);
    (new SessionArchitectureValidator())->validate($canonical, $draft);

    expect($canonical['beat_map'][1])
        ->moment_key->toBe('S1_M01')
        ->moment->toBe('Approach')
        ->and($canonical['beat_map'][3]['moment_key'])->toBe('S1_M03');
});

it('canonicalizes Phase 6 identity, dimension, moment, and path bindings from Phase 5', function () {
    $choiceDesign = [
        'branching_choices' => [[
            'choice_id' => 'S1_C1',
            'category' => 'IDENTITY',
            'moment_key' => 'S1_M01',
            'what_this_choice_tracks' => 'Truth versus protection',
            'options' => [
                ['label' => 'A', 'alignment' => 'chaotic', 'defining_line' => 'Expose it.'],
                ['label' => 'B', 'alignment' => 'lawful', 'defining_line' => 'Report it.'],
                ['label' => 'C', 'alignment' => 'neutral', 'defining_line' => 'Hold it.'],
            ],
        ]],
        'emotional_choices' => [],
    ];
    $generated = [
        'branching_consequences' => [[
            'choice_id' => 'S1_C1',
            'moment_key' => 'S1_M99',
            'tracked_dimension' => 'Changed dimension',
            'is_session_end_hook' => false,
            'moment_narrator_hook' => 'Surface the chosen account during the debrief.',
            'echo_moment_key' => '',
            'paths' => [
                ['label' => 'C', 'alignment' => 'LAWFUL', 'defining_line_captured' => 'Wrong C'],
                ['label' => 'A', 'alignment' => 'NEUTRAL', 'defining_line_captured' => 'Wrong A'],
                ['label' => 'B', 'alignment' => 'CHAOTIC', 'defining_line_captured' => 'Wrong B'],
            ],
        ]],
        'emotional_consequences' => [],
        'freeform_guidelines' => [
            ['choice_id' => 'S1_C1', 'path_label' => 'A'],
            ['choice_id' => 'S1_C1', 'path_label' => 'B'],
            ['choice_id' => 'S1_C1', 'path_label' => 'C'],
        ],
    ];

    $canonical = (new ConsequenceMapCanonicalizer())->canonicalize($generated, $choiceDesign);
    (new ConsequenceMapValidator())->validate($canonical, $choiceDesign, boilerTestDraft());

    expect($canonical['branching_consequences'][0])
        ->moment_key->toBe('S1_M01')
        ->tracked_dimension->toBe('Truth versus protection')
        ->is_session_end_hook->toBeTrue()
        ->and($canonical['branching_consequences'][0]['paths'])
        ->sequence(
            fn ($path) => $path->label->toBe('A')->alignment->toBe('CHAOTIC')->defining_line_captured->toBe('Expose it.'),
            fn ($path) => $path->label->toBe('B')->alignment->toBe('LAWFUL')->defining_line_captured->toBe('Report it.'),
            fn ($path) => $path->label->toBe('C')->alignment->toBe('NEUTRAL')->defining_line_captured->toBe('Hold it.'),
        );
});

it('allows Draft CHOICE candidates that Architecture did not select as canonical branches', function () {
    $draft = boilerTestDraft();
    $draft['event_role_map'][0]['role'] = 'CHOICE';
    $draft['moment_drafts'][] = array_replace($draft['moment_drafts'][0], [
        'moment_key' => 'S1_M02',
        'moment_order' => 2,
        'source_event_ids' => [102],
        'anchor_event_id' => 102,
    ]);
    $choiceDesign = [
        'branching_choices' => [[
            'choice_id' => 'S1_C4',
            'category' => 'SESSION_END_HOOK',
            'moment_key' => 'S1_M02',
        ]],
        'emotional_choices' => [],
        'posture_shifts' => [],
    ];
    $method = new ReflectionMethod(ChoiceDesignJob::class, 'validateBoilerBindings');

    $method->invoke(new ChoiceDesignJob(new Story, 1), $choiceDesign, $draft);

    expect(true)->toBeTrue();
});

it('does not infer canonical choice placement from unstructured Draft role prose', function () {
    $draft = boilerTestDraft();
    $draft['event_role_map'][0]['role'] = 'CHOICE';
    $draft['event_role_map'][0]['why'] = 'A load-bearing stealth versus confrontation methodology decision.';
    $choiceDesign = [
        'branching_choices' => [
            [
                'choice_id' => 'S1_C1',
                'category' => 'IDENTITY',
                'moment_key' => 'S1_M02',
                'what_this_choice_tracks' => 'Trust versus suspicion',
                'source_moment' => 'Read the room',
                'choice_question' => 'Whom do you trust?',
            ],
            [
                'choice_id' => 'S1_C2',
                'category' => 'METHODOLOGY',
                'moment_key' => 'S1_M03',
                'what_this_choice_tracks' => 'Stealth versus confrontation',
                'source_moment' => 'Choose an approach',
                'choice_question' => 'Do you hide or confront?',
            ],
        ],
    ];

    $canonical = (new ChoiceDesignCanonicalizer())->canonicalize($choiceDesign, $draft);

    expect($canonical['branching_choices'][0]['moment_key'])->toBe('S1_M02')
        ->and($canonical['branching_choices'][1]['moment_key'])->toBe('S1_M03');
});

it('always binds the canonical session-end choice to the final Draft moment', function () {
    $draft = boilerTestDraft();
    $draft['moment_drafts'][] = array_replace($draft['moment_drafts'][0], [
        'moment_key' => 'S1_M02',
        'moment_order' => 2,
        'source_event_ids' => [102],
        'anchor_event_id' => 102,
    ]);
    $choiceDesign = [
        'branching_choices' => [
            ['choice_id' => 'S1_C1', 'category' => 'IDENTITY', 'moment_key' => 'S1_M01'],
            ['choice_id' => 'S1_C2', 'category' => 'METHODOLOGY', 'moment_key' => 'S1_M01'],
            ['choice_id' => 'S1_C3', 'category' => 'MORAL_WEIGHT', 'moment_key' => 'S1_M01'],
            ['choice_id' => 'S1_C4', 'category' => 'SESSION_END_HOOK', 'moment_key' => 'S1_M01'],
        ],
    ];

    $canonical = (new ChoiceDesignCanonicalizer())->canonicalize($choiceDesign, $draft);

    expect($canonical['branching_choices'][3]['moment_key'])->toBe('S1_M02');
});
