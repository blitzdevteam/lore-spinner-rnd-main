<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Game;
use App\Models\PlayableSessionCut;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('cascades Boiler artifacts and nulls game pointers without deleting the game', function () {
    $story = Story::factory()->create();
    $user = User::factory()->create();
    $adaptation = StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);
    $session = SessionAdaptation::create([
        'story_adaptation_id' => $adaptation->id,
        'session_number' => 1,
        'session_status' => SessionAdaptationStatusEnum::COMPLETED,
        'boiler_status' => BoilerStatus::FINALIZED,
    ]);
    $cut = PlayableSessionCut::create([
        'story_id' => $story->id,
        'session_adaptation_id' => $session->id,
        'session_number' => 1,
        'cut_key' => 'standard',
        'status' => 'finalized',
    ]);
    $moment = $cut->moments()->create([
        'moment_key' => 'S1_M01',
        'moment_order' => 1,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S1_M01', 'choice_ids' => []],
    ]);
    $game = Game::create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
        'current_playable_session_cut_id' => $cut->id,
        'current_playable_moment_id' => $moment->id,
    ]);

    $session->delete();
    $game->refresh();

    expect($game->exists)->toBeTrue()
        ->and($game->current_playable_session_cut_id)->toBeNull()
        ->and($game->current_playable_moment_id)->toBeNull()
        ->and(PlayableSessionCut::find($cut->id))->toBeNull();
});
