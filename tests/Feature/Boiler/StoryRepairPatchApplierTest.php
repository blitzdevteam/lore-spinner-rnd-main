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
            'options' => [['label' => 'A', 'text' => 'You smash the cup.']],
        ]]],
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

    return $session;
}

it('accepts text patches and rejects identifiers, unknown paths, and banned prose', function () {
    $session = storyRepairSession();
    $applier = app(StoryRepairPatchApplier::class);
    $context = app(StoryRepairContext::class);

    $review = $applier->validate($session, [
        ['target' => 'scene_card', 'key' => 'S4_M02', 'path' => 'opening_image', 'new_value' => 'Hook sits alone in the cabin.', 'reason' => 'pov'],
        ['target' => 'scene_card', 'key' => 'S4_M02', 'path' => 'moment_key', 'new_value' => 'S4_M09', 'reason' => 'x'],
        ['target' => 'session_choice_design', 'key' => '', 'path' => 'branching_choices.0.options.0.label', 'new_value' => 'B', 'reason' => 'x'],
        ['target' => 'session_choice_design', 'key' => '', 'path' => 'branching_choices.0.options.0.text', 'new_value' => 'You knock the cup aside; it rolls.', 'reason' => 'x'],
        ['target' => 'session_choice_design', 'key' => '', 'path' => 'branching_choices.0.missing', 'new_value' => 'New text.', 'reason' => 'x'],
        ['target' => 'scene_card', 'key' => 'S4_M99', 'path' => 'opening_image', 'new_value' => 'Text.', 'reason' => 'x'],
    ], [], $context);

    expect($review['accepted'])->toHaveCount(1)
        ->and($review['accepted'][0]['old_value'])->toBe('You are Hook, alone in the cabin.')
        ->and($review['rejected'])->toHaveCount(5);

    $applier->apply($session, $review['accepted'], $context);

    expect($context->moments($session)->first()->scene_card['opening_image'])->toBe('Hook sits alone in the cabin.')
        ->and($context->moments($session)->first()->scene_card['choice_ids'])->toBe(['S4_C2']);
});
