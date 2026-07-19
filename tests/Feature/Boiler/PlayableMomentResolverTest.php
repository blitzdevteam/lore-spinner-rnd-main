<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Game;
use App\Models\PlayableSessionCut;
use App\Models\Prompt;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Models\User;
use App\Services\Runtime\PlayableMomentResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function boilerResolverResult(string $momentKey, bool $exit, string $footnote): array
{
    return [
        'response' => '<p>The room answers.</p>',
        'state_delta' => [
            'location' => '',
            'conditions' => [],
            'items' => [],
            'object_states' => [],
            'relationship_updates' => [],
            'world_flags' => [],
            'knowledge' => [],
            'notes' => [],
            'player_style' => [],
            'unresolved_promises' => [],
            'emotional_ledger_entries' => [],
        ],
        'alignment_tally_delta' => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
        'is_climactic_choice' => false,
        'defining_choice_id' => '',
        'defining_path_label' => '',
        'defining_choice_line' => '',
        'symbolic_memory_update' => '',
        'session_complete' => false,
        'moment_progress' => [
            'moment_key' => $momentKey,
            'exit_condition_met' => $exit,
            'moment_footnote' => $footnote,
        ],
    ];
}

function boilerResolverFixture(): array
{
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
        'session_choice_design' => ['branching_choices' => []],
    ]);
    $cut = PlayableSessionCut::create([
        'story_id' => $story->id,
        'session_adaptation_id' => $session->id,
        'session_number' => 1,
        'cut_key' => 'standard',
        'status' => 'finalized',
    ]);
    $first = $cut->moments()->create([
        'moment_key' => 'S1_M01',
        'moment_order' => 1,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S1_M01', 'choice_ids' => [], 'next_moment_key' => 'S1_M02'],
    ]);
    $second = $cut->moments()->create([
        'moment_key' => 'S1_M02',
        'moment_order' => 2,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S1_M02', 'choice_ids' => []],
    ]);
    $game = Game::create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
        'current_playable_session_cut_id' => $cut->id,
        'current_playable_moment_id' => $first->id,
        'alignment_scaffold' => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
    ]);
    $source = $game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>Opening.</p>',
        'choices' => [],
    ]);

    return compact('adaptation', 'session', 'cut', 'first', 'second', 'game', 'source');
}

it('commits one response per source prompt and does not increment twice', function () {
    $fixture = boilerResolverFixture();
    $resolver = app(PlayableMomentResolver::class);
    $sessions = $fixture['adaptation']->sessionAdaptations()->get();
    $result = boilerResolverResult(
        'S1_M01',
        false,
        'The player seeks proof before commitment; answer with one revealing resistance, then return the shard pressure.',
    );

    $firstResponse = $resolver->applyPlayerTurn(
        $fixture['game'],
        $fixture['source'],
        'Inspect the shard.',
        $result,
        [],
        $sessions,
    );
    $secondResponse = $resolver->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source']->fresh(),
        'Inspect the shard.',
        $result,
        [],
        $sessions,
    );

    expect($secondResponse->id)->toBe($firstResponse->id)
        ->and($fixture['game']->fresh()->playable_moment_turn_count)->toBe(1)
        ->and(Prompt::where('game_id', $fixture['game']->id)->count())->toBe(2);
});

it('advances by moment order and preserves the final pointer on completion', function () {
    $fixture = boilerResolverFixture();
    $resolver = app(PlayableMomentResolver::class);
    $sessions = $fixture['adaptation']->sessionAdaptations()->get();

    $response = $resolver->applyPlayerTurn(
        $fixture['game'],
        $fixture['source'],
        'Commit to the answer.',
        boilerResolverResult(
            'S1_M01',
            true,
            'The commitment is real; carry its secrecy into the next room and let the world immediately notice the changed posture.',
        ),
        [],
        $sessions,
    );
    $game = $fixture['game']->fresh();

    expect($game->current_playable_moment_id)->toBe($fixture['second']->id)
        ->and($game->playable_moment_turn_count)->toBe(0)
        ->and($game->current_session_complete)->toBeFalse();

    $resolver->applyPlayerTurn(
        $game,
        $response,
        'Finish the room.',
        boilerResolverResult(
            'S1_M02',
            true,
            'The final pressure is resolved; preserve the earned consequence and let the completed session rest without adding another choice.',
        ),
        [],
        $sessions,
    );
    $completed = $game->fresh();

    expect($completed->current_session_complete)->toBeTrue()
        ->and($completed->current_playable_moment_id)->toBe($fixture['second']->id)
        ->and($completed->current_playable_session_cut_id)->toBe($fixture['cut']->id);
});
