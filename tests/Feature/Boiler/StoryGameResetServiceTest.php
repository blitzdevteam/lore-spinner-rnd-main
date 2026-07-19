<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameReset;
use App\Models\PlayableSessionCut;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Models\User;
use App\Services\Adaptation\StoryGameResetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('clears legacy and Boiler state through one shared reset service', function () {
    $story = Story::factory()->create();
    $user = User::factory()->create();
    $adaptation = StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => 'completed',
        'boiler_enabled' => true,
    ]);
    $session = SessionAdaptation::create([
        'story_adaptation_id' => $adaptation->id,
        'session_number' => 1,
        'session_status' => 'completed',
        'boiler_status' => 'finalized',
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
        'current_story_cycle_number' => 2,
        'model' => 'claude-sonnet-4-6',
        'world_state' => ['location' => 'The room'],
        'symbolic_memory' => 'A remembered choice.',
        'alignment_scaffold' => ['chaotic' => 1, 'lawful' => 0, 'neutral' => 0],
        'defining_choice_id' => 'S1_C1',
        'defining_path_label' => 'A',
        'defining_choice_line' => 'A line.',
        'is_climactic_choice' => true,
        'current_session_complete' => true,
        'current_playable_session_cut_id' => $cut->id,
        'current_playable_moment_id' => $moment->id,
        'playable_moment_turn_count' => 4,
        'moment_footnote' => ['moment_key' => 'S1_M01', 'text' => 'Insight', 'source_prompt_id' => '01TEST'],
        'completed_at' => now(),
    ]);
    $game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>Old narration.</p>',
        'choices' => [],
    ]);

    app(StoryGameResetService::class)->reset($game);
    $reset = $game->fresh();

    expect($reset->current_story_cycle_number)->toBe(3)
        ->and($reset->prompts()->count())->toBe(0)
        ->and($reset->current_session_complete)->toBeFalse()
        ->and($reset->current_playable_session_cut_id)->toBeNull()
        ->and($reset->current_playable_moment_id)->toBeNull()
        ->and($reset->playable_moment_turn_count)->toBe(0)
        ->and($reset->moment_footnote)->toBeNull()
        ->and($reset->world_state)->toBeNull()
        ->and($reset->defining_choice_id)->toBeNull()
        ->and($reset->completed_at)->toBeNull()
        ->and(GameReset::where('game_id', $game->id)->count())->toBe(1);
});
