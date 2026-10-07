<?php

declare(strict_types=1);

use App\Ai\Adaptation\BannedProseValidator;
use App\Ai\Adaptation\StoryRepairContext;
use App\Ai\Adaptation\StoryRepairPatchApplier;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\PlayableSessionCut;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function storyRepairSession(): SessionAdaptation
{
    $story = Story::factory()->create();
    $adaptation = StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);
    $session = SessionAdaptation::create([
        'story_adaptation_id' => $adaptation->id,
        'session_number' => 4,
        'session_status' => SessionAdaptationStatusEnum::COMPLETED,
        'boiler_status' => BoilerStatus::FINALIZED,
        'session_choice_design' => ['branching_choices' => [[
            'choice_id' => 'S4_C2',
            'moment_key' => 'S4_M02',
            'options' => [['label' => 'A', 'text' => 'You smash the cup.']],
        ]]],
        'choice_consequence_map' => ['branching_consequences' => [['choice_id' => 'S4_C2', 'moment_key' => 'S4_M02', 'paths' => []]]],
        'session_close_design' => ['closing_moment_key' => 'S4_M04', 'resolution_prose' => 'The ship sleeps.'],
        'boiler_final_cut' => ['moments' => [
            ['moment_key' => 'S4_M02', 'choice_ids' => ['S4_C2']],
            ['moment_key' => 'S4_M03', 'choice_ids' => []],
        ]],
    ]);
    $cut = PlayableSessionCut::create([
        'story_id' => $story->id,
        'session_adaptation_id' => $session->id,
        'session_number' => 4,
        'cut_key' => 'standard',
        'status' => 'finalized',
    ]);
    $cut->moments()->create([
        'moment_key' => 'S4_M02',
        'moment_order' => 1,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S4_M02', 'opening_image' => 'You are Hook, alone in the cabin.', 'choice_ids' => ['S4_C2']],
    ]);
    $cut->moments()->create([
        'moment_key' => 'S4_M03',
        'moment_order' => 2,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S4_M03', 'opening_image' => 'The cup waits on the table.', 'choice_ids' => []],
    ]);
    $cut->moments()->create([
        'moment_key' => 'S4_M04',
        'moment_order' => 3,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S4_M04', 'opening_image' => 'Dawn.', 'choice_ids' => ['S4_C9']],
    ]);

    return $session;
}

it('accepts text patches and rejects identifiers, unknown paths, and banned prose', function () {
    $session = storyRepairSession();
    $applier = app(StoryRepairPatchApplier::class);
    $context = app(StoryRepairContext::class);
    $voice = ['protagonist' => 'Wendy Darling', 'voice_profile' => []];

    $review = $applier->validate($session, [
        ['target' => 'scene_card', 'key' => 'S4_M02', 'path' => 'opening_image', 'new_value' => 'Hook sits alone in the cabin.', 'reason' => 'pov'],
        ['target' => 'scene_card', 'key' => 'S4_M02', 'path' => 'moment_key', 'new_value' => 'S4_M09', 'reason' => 'x'],
        ['target' => 'session_choice_design', 'key' => '', 'path' => 'branching_choices.0.options.0.label', 'new_value' => 'B', 'reason' => 'x'],
        ['target' => 'session_choice_design', 'key' => '', 'path' => 'branching_choices.0.options.0.text', 'new_value' => 'You knock the cup aside, as the tapestry rolls.', 'reason' => 'x'],
        ['target' => 'session_choice_design', 'key' => '', 'path' => 'branching_choices.0.missing', 'new_value' => 'New text.', 'reason' => 'x'],
        ['target' => 'scene_card', 'key' => 'S4_M99', 'path' => 'opening_image', 'new_value' => 'Text.', 'reason' => 'x'],
    ], [], $voice);

    expect($review['patches'])->toHaveCount(1)
        ->and($review['patches'][0]['old_value'])->toBe('You are Hook, alone in the cabin.')
        ->and($review['rejected'])->toHaveCount(5);

    $applier->apply($session, $review['patches'], []);

    expect($context->moments($session)->first()->scene_card['opening_image'])->toBe('Hook sits alone in the cabin.')
        ->and($context->moments($session)->first()->scene_card['choice_ids'])->toBe(['S4_C2']);
});

it('moves a choice to its scene everywhere it is bound, and reverts cleanly', function () {
    $session = storyRepairSession();
    $applier = app(StoryRepairPatchApplier::class);
    $context = app(StoryRepairContext::class);
    $voice = ['protagonist' => 'Wendy Darling', 'voice_profile' => []];

    $review = $applier->validate($session, [], [
        ['choice_id' => 'S4_C2', 'from_moment_key' => 'S4_M02', 'to_moment_key' => 'S4_M03', 'reason' => 'The cup is on S4_M03.'],
        ['choice_id' => 'S4_C9', 'from_moment_key' => 'S4_M04', 'to_moment_key' => 'S4_M03', 'reason' => 'x'],
        ['choice_id' => 'S4_C7', 'from_moment_key' => 'S4_M02', 'to_moment_key' => 'S4_M03', 'reason' => 'x'],
    ], $voice);

    expect($review['rebinds'])->toHaveCount(1)
        ->and($review['rejected'])->toHaveCount(2);

    $backup = $applier->apply($session, [], $review['rebinds']);
    $session->refresh();
    $cards = $context->moments($session)->keyBy('moment_key');

    expect($cards['S4_M02']->scene_card['choice_ids'])->toBe([])
        ->and($cards['S4_M03']->scene_card['choice_ids'])->toBe(['S4_C2'])
        ->and($session->session_choice_design['branching_choices'][0]['moment_key'])->toBe('S4_M03')
        ->and($session->choice_consequence_map['branching_consequences'][0]['moment_key'])->toBe('S4_M03')
        ->and($session->boiler_final_cut['moments'][1]['choice_ids'])->toBe(['S4_C2']);

    $applier->revert($session, $backup);
    $session->refresh();

    expect($context->moments($session)->keyBy('moment_key')['S4_M02']->scene_card['choice_ids'])->toBe(['S4_C2'])
        ->and($session->session_choice_design['branching_choices'][0]['moment_key'])->toBe('S4_M02');
});

it('rejects a reviewed patch whose field changed before it was applied', function () {
    $session = storyRepairSession();
    $review = app(StoryRepairPatchApplier::class)->validate($session, [[
        'target' => 'session_close_design', 'key' => '', 'path' => 'resolution_prose',
        'old_value' => 'Something else was reviewed.', 'new_value' => 'The ship sleeps at last.', 'reason' => 'x',
    ]], [], ['protagonist' => 'Wendy Darling', 'voice_profile' => []]);

    expect($review['patches'])->toBe([])
        ->and($review['rejected'][0]['reason'])->toContain('changed since it was reviewed');
});

it('converts banned punctuation in a patch instead of rejecting it', function () {
    $review = app(StoryRepairPatchApplier::class)->validate(storyRepairSession(), [[
        'target' => 'session_close_design', 'key' => '', 'path' => 'resolution_prose',
        'new_value' => 'The ship sleeps; the crew does not.', 'reason' => 'x',
    ]], [], ['protagonist' => 'Wendy Darling', 'voice_profile' => []]);

    expect($review['patches'][0]['new_value'])->not->toContain(';');
});

it('patches both sides of a session boundary in one review and reverts both', function () {
    $session = storyRepairSession();
    $previous = SessionAdaptation::create([
        'story_adaptation_id' => $session->story_adaptation_id,
        'session_number' => 3,
        'session_status' => SessionAdaptationStatusEnum::COMPLETED,
        'session_close_design' => ['session_end_choice' => ['next_session_opens' => ['The great gun has scattered you.']]],
    ]);
    $next = SessionAdaptation::create([
        'story_adaptation_id' => $session->story_adaptation_id,
        'session_number' => 5,
        'session_status' => SessionAdaptationStatusEnum::COMPLETED,
        'entry_point_diagnosis' => [
            'cold_open' => 'You are Wendy Darling, on the deck.',
            'format_specific_cut' => ['must_reintroduce' => ['The gun scattered the children.']],
            'start_event_position' => 80,
        ],
    ]);
    $applier = app(StoryRepairPatchApplier::class);

    $review = $applier->validate($session, [
        ['target' => 'previous_session_close', 'key' => '', 'path' => 'session_end_choice.next_session_opens.0', 'new_value' => 'The island comes into sight below you.', 'reason' => 'x'],
        ['target' => 'next_session_entry', 'key' => '', 'path' => 'format_specific_cut.must_reintroduce.0', 'new_value' => 'The children reached the island together.', 'reason' => 'x'],
        ['target' => 'next_session_entry', 'key' => '', 'path' => 'start_event_position', 'new_value' => '81', 'reason' => 'x'],
    ], [], ['protagonist' => 'Wendy Darling', 'voice_profile' => []]);

    expect($review['patches'])->toHaveCount(2)
        ->and($review['rejected'])->toHaveCount(1);

    $backup = $applier->apply($session, $review['patches'], []);

    expect($previous->fresh()->session_close_design['session_end_choice']['next_session_opens'][0])->toBe('The island comes into sight below you.')
        ->and($next->fresh()->entry_point_diagnosis['format_specific_cut']['must_reintroduce'][0])->toBe('The children reached the island together.')
        ->and($next->fresh()->entry_point_diagnosis['start_event_position'])->toBe(80);

    $applier->revert($session, $backup);

    expect($previous->fresh()->session_close_design['session_end_choice']['next_session_opens'][0])->toBe('The great gun has scattered you.')
        ->and($next->fresh()->entry_point_diagnosis['format_specific_cut']['must_reintroduce'][0])->toBe('The gun scattered the children.');
});

it('moves a must_reintroduce fact off the cold open only to a valid marker and card', function () {
    $session = storyRepairSession();
    $session->update(['entry_point_diagnosis' => [
        'cold_open' => 'You are Wendy Darling, alone in the dark.',
        'format_specific_cut' => ['must_reintroduce' => [['fact' => 'Long Tom scattered the children.', 'marker' => 'COLD_OPEN', 'target_moment_key' => 'S4_M02']]],
    ]]);
    $voice = ['protagonist' => 'Wendy Darling', 'voice_profile' => []];
    $patch = fn (string $leaf, string $value): array => ['target' => 'entry_point_diagnosis', 'key' => '', 'path' => "format_specific_cut.must_reintroduce.0.{$leaf}", 'new_value' => $value, 'reason' => 'x'];

    $review = app(StoryRepairPatchApplier::class)->validate($session, [
        $patch('marker', 'LATER'),
        $patch('target_moment_key', 'S4_M03'),
    ], [], $voice);
    $invalid = app(StoryRepairPatchApplier::class)->validate($session, [
        $patch('marker', 'SOMETIME'),
        $patch('target_moment_key', 'S4_M99'),
    ], [], $voice);

    expect($review['patches'])->toHaveCount(2)
        ->and($invalid['patches'])->toBe([])
        ->and($invalid['rejected'])->toHaveCount(2);
});

it('capitalizes the sentence a converted semicolon starts', function () {
    expect(BannedProseValidator::neutralizeBannedPunctuation('He pauses; by morning he is gone.'))->toBe('He pauses. By morning he is gone.');
});

it('swaps a flag only for one the session already uses', function () {
    $session = storyRepairSession();
    $session->update(['session_choice_design' => ['branching_choices' => [[
        'choice_id' => 'S4_C2',
        'moment_key' => 'S4_M02',
        'options' => [
            ['label' => 'A', 'text' => 'You settle to mothering.', 'environmental_flags' => ['MOTHER_RELUCTANT']],
            ['label' => 'B', 'text' => 'You hesitate.', 'environmental_flags' => ['MOTHER_EMBRACED']],
        ],
    ]]]]);
    $voice = ['protagonist' => 'Wendy Darling', 'voice_profile' => []];
    $path = 'branching_choices.0.options.0.environmental_flags.0';

    $known = app(StoryRepairPatchApplier::class)->validate($session, [['target' => 'session_choice_design', 'key' => '', 'path' => $path, 'new_value' => 'MOTHER_EMBRACED', 'reason' => 'x']], [], $voice);
    $invented = app(StoryRepairPatchApplier::class)->validate($session, [['target' => 'session_choice_design', 'key' => '', 'path' => $path, 'new_value' => 'MOTHER_INVENTED', 'reason' => 'x']], [], $voice);

    expect($known['patches'])->toHaveCount(1)
        ->and($invented['patches'])->toBe([])
        ->and($invented['rejected'][0]['reason'])->toContain('not a flag');
});

it('reorders cards only with every bridge and the cold open rewritten, and reverts the order', function () {
    $session = storyRepairSession();
    $session->update(['entry_point_diagnosis' => ['cold_open' => 'You are Wendy Darling, alone in the dark.']]);
    $context = app(StoryRepairContext::class);
    foreach ($context->moments($session) as $moment) {
        $moment->update(['scene_card' => [...$moment->scene_card, 'next_bridge' => "Out of {$moment->moment_key}."]]);
    }
    $applier = app(StoryRepairPatchApplier::class);
    $voice = ['protagonist' => 'Wendy Darling', 'voice_profile' => []];
    $order = ['S4_M03', 'S4_M02', 'S4_M04'];
    $bridges = [
        ['target' => 'scene_card', 'key' => 'S4_M03', 'path' => 'next_bridge', 'new_value' => 'Into the cabin.', 'reason' => 'x'],
        ['target' => 'scene_card', 'key' => 'S4_M02', 'path' => 'next_bridge', 'new_value' => 'Into the dawn.', 'reason' => 'x'],
    ];
    $coldOpen = ['target' => 'entry_point_diagnosis', 'key' => '', 'path' => 'cold_open', 'new_value' => 'You are Wendy Darling, and the cup waits on the table.', 'reason' => 'x'];

    $withoutBridges = $applier->validate($session, [$coldOpen], [], $voice, [], $order);
    $withoutColdOpen = $applier->validate($session, $bridges, [], $voice, [], $order);
    $missingCard = $applier->validate($session, [...$bridges, $coldOpen], [], $voice, [], ['S4_M03', 'S4_M04']);
    $closingMoved = $applier->validate($session, [...$bridges, $coldOpen], [], $voice, [], ['S4_M04', 'S4_M03', 'S4_M02']);
    $valid = $applier->validate($session, [...$bridges, $coldOpen], [], $voice, [], $order);

    expect($withoutBridges['reorder'])->toBe([])
        ->and($withoutColdOpen['reorder'])->toBe([])
        ->and($missingCard['reorder'])->toBe([])
        ->and($closingMoved['reorder'])->toBe([])
        ->and($valid['reorder'])->toBe($order);

    $backup = $applier->apply($session, $valid['patches'], [], $valid['reorder']);
    $session->refresh();
    $moments = $context->moments($session);

    expect($moments->pluck('moment_key')->all())->toBe($order)
        ->and($moments->pluck('moment_order')->all())->toBe([1, 2, 3])
        ->and($moments[0]->scene_card['next_moment_key'])->toBe('S4_M02')
        ->and($moments[1]->scene_card['next_moment_key'])->toBe('S4_M04')
        ->and($moments[2]->scene_card)->not->toHaveKey('next_moment_key')
        ->and(collect($session->boiler_final_cut['moments'])->pluck('moment_key')->all())->toBe(['S4_M03', 'S4_M02'])
        ->and($session->entry_point_diagnosis['cold_open'])->toContain('the cup waits');

    $applier->revert($session, $backup);

    expect($context->moments($session->fresh())->pluck('moment_key')->all())->toBe(['S4_M02', 'S4_M03', 'S4_M04']);
});
