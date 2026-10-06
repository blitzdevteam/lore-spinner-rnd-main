<?php

declare(strict_types=1);

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
        ['target' => 'session_choice_design', 'key' => '', 'path' => 'branching_choices.0.options.0.text', 'new_value' => 'You knock the cup aside; it rolls.', 'reason' => 'x'],
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
