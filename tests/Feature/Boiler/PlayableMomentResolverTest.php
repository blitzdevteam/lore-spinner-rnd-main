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
use App\Services\ChaosEngineService;
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

it('demotes invalid mapped suggestions and still commits the turn', function () {
    $fixture = boilerResolverFixture();
    $fixture['first']->update([
        'scene_card' => [
            'moment_key' => 'S1_M01',
            'choice_ids' => ['S1_C1', 'S1_E1'],
            'next_moment_key' => 'S1_M02',
        ],
    ]);
    $fixture['session']->update([
        'session_choice_design' => [
            'branching_choices' => [
                [
                    'choice_id' => 'S1_C1',
                    'options' => [
                        ['label' => 'A', 'text' => 'Path A'],
                        ['label' => 'B', 'text' => 'Path B'],
                        ['label' => 'C', 'text' => 'Path C'],
                    ],
                ],
            ],
            'emotional_choices' => [
                [
                    'choice_id' => 'S1_E1',
                    'options' => [
                        ['label' => 'A', 'text' => 'Soft'],
                        ['label' => 'B', 'text' => 'Hard'],
                        ['label' => 'C', 'text' => 'Quiet'],
                    ],
                ],
            ],
        ],
    ]);

    $resolver = app(PlayableMomentResolver::class);
    $response = $resolver->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Choose something messy.',
        boilerResolverResult(
            'S1_M01',
            false,
            'The player is probing boundaries; answer with grounded resistance while keeping the active pressure alive and responsive.',
        ),
        [
            [
                'text' => 'Unknown choice mapping',
                'canonical_choice_id' => 'S1_C99',
                'canonical_path_label' => 'A',
            ],
            [
                'text' => 'Bad path on real choice',
                'canonical_choice_id' => 'S1_C1',
                'canonical_path_label' => 'Z',
            ],
            [
                'text' => 'Valid emotional mapping',
                'canonical_choice_id' => 'S1_E1',
                'canonical_path_label' => 'B',
            ],
        ],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($response->choices)->toBe([
        [
            'text' => 'Unknown choice mapping',
            'canonical_choice_id' => null,
            'canonical_path_label' => null,
        ],
        [
            'text' => 'Bad path on real choice',
            'canonical_choice_id' => null,
            'canonical_path_label' => null,
        ],
        [
            'text' => 'Valid emotional mapping',
            'canonical_choice_id' => 'S1_E1',
            'canonical_path_label' => 'B',
        ],
    ])
        ->and($fixture['source']->fresh()->prompt)->toBe('Choose something messy.')
        ->and($fixture['game']->fresh()->playable_moment_turn_count)->toBe(1)
        ->and(Prompt::where('game_id', $fixture['game']->id)->count())->toBe(2);
});

it('commits a turn after soft-repairing missing moment_progress and empty footnotes', function () {
    $fixture = boilerResolverFixture();
    $resolver = app(PlayableMomentResolver::class);

    $response = $resolver->applyPlayerTurn(
        $fixture['game'],
        $fixture['source'],
        'Probe the room.',
        [
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
            'moment_progress' => null,
        ],
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    $game = $fixture['game']->fresh();

    expect($response->response)->toBe('<p>The room answers.</p>')
        ->and($fixture['source']->fresh()->prompt)->toBe('Probe the room.')
        ->and($game->playable_moment_turn_count)->toBe(1)
        ->and($game->current_playable_moment_id)->toBe($fixture['first']->id)
        ->and($game->moment_footnote['text'] ?? null)->toBe(
            'The player acted; answer the immediate pressure and keep the active room alive.'
        );
});

it('repairs missing or stale moment_progress onto the active moment', function () {
    $fixture = boilerResolverFixture();
    $engine = app(ChaosEngineService::class);
    $method = (new ReflectionClass($engine))->getMethod('validateBoilerNarrationResult');

    $missing = [
        'visible_suggestions' => [],
        'choices' => [],
        'moment_progress' => null,
    ];
    $method->invokeArgs($engine, [&$missing, $fixture['game'], true]);
    expect($missing['moment_progress'])->toBe([
        'moment_key' => 'S1_M01',
        'exit_condition_met' => false,
        'moment_footnote' => null,
    ]);

    $stale = [
        'visible_suggestions' => [],
        'choices' => [],
        'moment_progress' => [
            'moment_key' => 'S1_M99',
            'exit_condition_met' => true,
            'moment_footnote' => 'The player presses for proof; answer with one concrete resistance and keep the room live.',
        ],
    ];
    $method->invokeArgs($engine, [&$stale, $fixture['game'], false]);
    expect($stale['moment_progress']['moment_key'])->toBe('S1_M01')
        ->and($stale['moment_progress']['exit_condition_met'])->toBeFalse()
        ->and($stale['moment_progress']['moment_footnote'])->toBe(
            'The player presses for proof; answer with one concrete resistance and keep the room live.'
        );
});

it('repairs opening narration that wrongly includes a footnote', function () {
    $fixture = boilerResolverFixture();
    $engine = app(ChaosEngineService::class);
    $method = (new ReflectionClass($engine))->getMethod('validateBoilerNarrationResult');

    $invalid = [
        'visible_suggestions' => [],
        'choices' => ['legacy'],
        'moment_progress' => [
            'moment_key' => 'S1_M01',
            'exit_condition_met' => true,
            'moment_footnote' => 'Opening narration must not critique a player who has not acted.',
        ],
    ];
    $method->invokeArgs($engine, [&$invalid, $fixture['game'], true]);

    expect($invalid['moment_progress']['exit_condition_met'])->toBeFalse()
        ->and($invalid['moment_progress']['moment_footnote'])->toBeNull()
        ->and($invalid['choices'])->toBe([])
        ->and($invalid['visible_suggestions'])->toBe([]);
});
