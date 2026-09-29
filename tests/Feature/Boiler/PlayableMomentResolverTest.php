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
use App\Services\ChaosEngineService;
use App\Services\Runtime\PlayableMomentResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function boilerResolverResult(
    string $momentKey,
    bool $exit,
    string $footnote,
    string $routeRelation = '',
    string $impactScope = '',
): array {
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
            'route_relation' => $routeRelation,
            'impact_scope' => $impactScope,
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
        ->and($game->moment_footnote['text'] ?? null)->toBeNull()
        ->and($response->moment_key)->toBe('S1_M01')
        ->and($response->exit_condition_met)->toBeFalse()
        ->and($response->moment_result)->toBe('retained')
        ->and($response->moment_footnote)->toBeNull();
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
        'caught_up_to_moment_key' => '',
        'route_relation' => '',
        'impact_scope' => '',
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

it('does not invent a PHP footnote default when the player-turn footnote is empty', function () {
    $fixture = boilerResolverFixture();
    $engine = app(ChaosEngineService::class);
    $method = (new ReflectionClass($engine))->getMethod('validateBoilerNarrationResult');

    $result = [
        'visible_suggestions' => [],
        'choices' => [],
        'moment_progress' => [
            'moment_key' => 'S1_M01',
            'exit_condition_met' => false,
            'moment_footnote' => '',
        ],
    ];
    $method->invokeArgs($engine, [&$result, $fixture['game'], false]);

    expect($result['moment_progress']['moment_footnote'])->toBeNull();
});

it('injects budget, gravity, exit, forbidden, and next_bridge into the active-moment block', function () {
    $fixture = boilerResolverFixture();
    $fixture['first']->update([
        'scene_card' => [
            'moment_key' => 'S1_M01',
            'title' => 'The room',
            'choice_ids' => ['S1_C1'],
            'exit_condition' => 'The shard is understood.',
            'forbidden_expansions' => ['Do not stage the vigil.'],
            'next_bridge' => 'Carry the secret into the corridor.',
            'turn_budget' => [
                'target_turns' => 2,
                'gravity_after' => 3,
            ],
        ],
    ]);
    $fixture['session']->update([
        'session_choice_design' => [
            'branching_choices' => [
                [
                    'choice_id' => 'S1_C1',
                    'options' => [
                        ['label' => 'A', 'text' => 'Ask plainly'],
                        ['label' => 'B', 'text' => 'Wait'],
                        ['label' => 'C', 'text' => 'Leave'],
                    ],
                ],
            ],
            'emotional_choices' => [],
        ],
    ]);
    $fixture['game']->update(['playable_moment_turn_count' => 3]);

    $engine = app(ChaosEngineService::class);
    $resolve = (new ReflectionClass($engine))->getMethod('resolveActiveMomentContext');
    $build = (new ReflectionClass($engine))->getMethod('buildActiveMomentBlock');

    $context = $resolve->invoke($engine, $fixture['game']->fresh(), $fixture['session']->fresh());
    $block = $build->invoke($engine, $context);

    expect($context['pacing_state'])->toBe('AT GRAVITY')
        ->and($context['target_turns'])->toBe(2)
        ->and($context['gravity_after'])->toBe(3)
        ->and($context['turn_count'])->toBe(3)
        ->and($block)->toContain('Pacing state: AT GRAVITY')
        ->and($block)->toContain('Target turns: 2')
        ->and($block)->toContain('Gravity begins: 3')
        ->and($block)->toContain('EXIT CONDITION')
        ->and($block)->toContain('The shard is understood.')
        ->and($block)->toContain('STORYGUARD BOUNDARIES FOR THIS ROOM')
        ->and($block)->toContain('Do not stage the vigil.')
        ->and($block)->toContain('NEXT BRIDGE')
        ->and($block)->toContain('Carry the secret into the corridor.')
        ->and($block)->toContain('THE WORLD STARTS PULLING')
        ->and($block)->toContain('Ask plainly')
        ->and($block)->toContain('REQUIRED: emit valid moment_progress')
        ->and($block)->toContain('WHERE THE PLAYER MAY GO')
        ->and($block)->toContain('Comfortable room to follow them further: 3');
});

it('grants detour room independently of time spent in the room', function () {
    $fixture = boilerResolverFixture();
    $resolver = app(PlayableMomentResolver::class);
    $engine = app(ChaosEngineService::class);
    $resolve = (new ReflectionClass($engine))->getMethod('resolveActiveMomentContext');
    $sessions = $fixture['adaptation']->sessionAdaptations()->get();

    $source = $fixture['source'];
    foreach (['deepened', 'deepened', 'detoured'] as $relation) {
        $source = $resolver->applyPlayerTurn(
            $fixture['game']->fresh(),
            $source,
            'Stay with it.',
            boilerResolverResult('S1_M01', false, 'The player wants the room to answer them first.', $relation),
            [],
            $sessions,
        );
    }

    $game = $fixture['game']->fresh();
    $context = $resolve->invoke($engine, $game, $fixture['session']->fresh());

    // Three turns in the room, but only the single detour spent any allowance.
    expect($game->playable_moment_turn_count)->toBe(3)
        ->and($context['detour_beats_used'])->toBe(1)
        ->and($context['detour_beats_remaining'])->toBe(2);

    $resolver->applyPlayerTurn(
        $game,
        $source,
        'Back to the shard.',
        boilerResolverResult('S1_M01', false, 'They have made their point and are ready to be pulled back.', 'reconverged'),
        [],
        $sessions,
    );

    $reconverged = $resolve->invoke($engine, $fixture['game']->fresh(), $fixture['session']->fresh());

    expect($reconverged['detour_beats_used'])->toBe(0)
        ->and($reconverged['detour_beats_remaining'])->toBe(3);
});

it('persists route and impact signals and escalates persistent impact to a full state load', function () {
    $fixture = boilerResolverFixture();
    $resolver = app(PlayableMomentResolver::class);

    $response = $resolver->applyPlayerTurn(
        $fixture['game'],
        $fixture['source'],
        'Pocket the shard and lie about it.',
        boilerResolverResult(
            'S1_M01',
            false,
            'They want to be the kind of person who gets away with it; let someone almost notice.',
            'detoured',
            'persistent',
        ),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($response->route_relation)->toBe('detoured')
        ->and($response->impact_scope)->toBe('persistent')
        ->and($fixture['game']->fresh()->is_climactic_choice)->toBeTrue();
});

it('releases the session rather than trapping the player in a finished final room', function () {
    $fixture = boilerResolverFixture();
    $fixture['second']->update([
        'scene_card' => ['moment_key' => 'S1_M02', 'choice_ids' => ['S1_C4']],
    ]);
    $fixture['session']->update([
        'session_choice_design' => [
            'branching_choices' => [[
                'choice_id' => 'S1_C4',
                'options' => [
                    ['label' => 'A', 'text' => 'Close with mercy'],
                    ['label' => 'B', 'text' => 'Close with steel'],
                    ['label' => 'C', 'text' => 'Close with silence'],
                ],
            ]],
            'emotional_choices' => [],
        ],
    ]);
    $fixture['game']->update(['current_playable_moment_id' => $fixture['second']->id]);

    $resolver = app(PlayableMomentResolver::class);
    $sessions = $fixture['adaptation']->sessionAdaptations()->get();
    $source = $fixture['source'];

    foreach ([1, 2] as $attempt) {
        $source = $resolver->applyPlayerTurn(
            $fixture['game']->fresh(),
            $source,
            'I think that is everything.',
            boilerResolverResult('S1_M02', true, 'They feel finished but have not named how they want it to end.'),
            [],
            $sessions,
        );

        expect($fixture['game']->fresh()->current_session_complete)->toBeFalse();
    }

    $released = $resolver->applyPlayerTurn(
        $fixture['game']->fresh(),
        $source,
        'Really, we are done.',
        boilerResolverResult('S1_M02', true, 'Holding them here now would read as the story ignoring them.'),
        [],
        $sessions,
    );

    expect($fixture['game']->fresh()->current_session_complete)->toBeTrue()
        ->and($released->moment_result)->toBe('completed');
});

it('persists model-produced moment fields on the response prompt each player turn', function () {
    $fixture = boilerResolverFixture();
    $resolver = app(PlayableMomentResolver::class);
    $footnote = 'The player seeks proof before commitment; answer with one revealing resistance, then return the shard pressure.';

    $response = $resolver->applyPlayerTurn(
        $fixture['game'],
        $fixture['source'],
        'Inspect the shard.',
        boilerResolverResult('S1_M01', false, $footnote),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($response->moment_key)->toBe('S1_M01')
        ->and($response->exit_condition_met)->toBeFalse()
        ->and($response->moment_result)->toBe('retained')
        ->and($response->moment_footnote)->toBe($footnote)
        ->and($fixture['game']->fresh()->moment_footnote['text'] ?? null)->toBe($footnote);
});

it('keeps authored mappings on exit turns by sanitizing against the pre-exit moment', function () {
    $fixture = boilerResolverFixture();
    $fixture['first']->update([
        'scene_card' => [
            'moment_key' => 'S1_M01',
            'choice_ids' => ['S1_C1'],
            'next_moment_key' => 'S1_M02',
        ],
    ]);
    $fixture['second']->update([
        'scene_card' => [
            'moment_key' => 'S1_M02',
            'choice_ids' => [],
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
            'emotional_choices' => [],
        ],
    ]);

    $resolver = app(PlayableMomentResolver::class);
    $response = $resolver->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Take path A.',
        boilerResolverResult(
            'S1_M01',
            true,
            'The choice lands; close this room and bridge into the next pressure without restating the invitation.',
        ),
        [
            [
                'text' => 'Path A wording',
                'canonical_choice_id' => 'S1_C1',
                'canonical_path_label' => 'A',
            ],
        ],
        $fixture['adaptation']->sessionAdaptations()->get(),
        [
            'defining_choice_id' => 'S1_C1',
            'defining_path_label' => 'A',
            'defining_choice_line' => 'Path A is taken.',
        ],
    );

    expect($response->choices)->toBe([
        [
            'text' => 'Path A wording',
            'canonical_choice_id' => 'S1_C1',
            'canonical_path_label' => 'A',
        ],
    ])
        ->and($response->moment_result)->toBe('advanced')
        ->and($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['second']->id);
});

it('blocks session complete until an authored session-ending choice is resolved', function () {
    $fixture = boilerResolverFixture();
    $fixture['second']->update([
        'scene_card' => [
            'moment_key' => 'S1_M02',
            'choice_ids' => ['S1_C4'],
        ],
    ]);
    $fixture['session']->update([
        'session_choice_design' => [
            'branching_choices' => [
                [
                    'choice_id' => 'S1_C4',
                    'options' => [
                        ['label' => 'A', 'text' => 'Close with mercy'],
                        ['label' => 'B', 'text' => 'Close with steel'],
                        ['label' => 'C', 'text' => 'Close with silence'],
                    ],
                ],
            ],
            'emotional_choices' => [],
        ],
    ]);
    $fixture['game']->update([
        'current_playable_moment_id' => $fixture['second']->id,
        'playable_moment_turn_count' => 1,
    ]);

    $resolver = app(PlayableMomentResolver::class);
    $sessions = $fixture['adaptation']->sessionAdaptations()->get();

    $blocked = $resolver->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'I think we are done.',
        boilerResolverResult(
            'S1_M02',
            true,
            'The room feels finished in prose, but the authored ending choice has not yet been taken.',
        ),
        [],
        $sessions,
    );

    expect($fixture['game']->fresh()->current_session_complete)->toBeFalse()
        ->and($blocked->moment_result)->toBe('retained');

    $endingSource = $fixture['game']->fresh()->prompts()->create([
        'session_number' => 1,
        'response' => '<p>Still the last room.</p>',
        'choices' => [],
    ]);

    $completed = $resolver->applyPlayerTurn(
        $fixture['game']->fresh(),
        $endingSource,
        'Close with silence.',
        array_merge(
            boilerResolverResult(
                'S1_M02',
                true,
                'The ending choice is taken; let the session rest on the earned close without inventing another beat.',
            ),
            [
                'defining_choice_id' => 'S1_C4',
                'defining_path_label' => 'C',
                'defining_choice_line' => 'Silence closes the case.',
            ],
        ),
        [],
        $sessions,
        [
            'defining_choice_id' => 'S1_C4',
            'defining_path_label' => 'C',
            'defining_choice_line' => 'Silence closes the case.',
        ],
    );

    expect($fixture['game']->fresh()->current_session_complete)->toBeTrue()
        ->and($completed->moment_result)->toBe('completed');
});

it('replaces near-duplicate symbolic_memory and player_style entries', function () {
    $engine = app(ChaosEngineService::class);

    // Exact duplicate: stem matches, replaces with identical text — no line added.
    $first = $engine->appendMemory(null, 'Instinct: press for proof before trusting the room.');
    $duped = $engine->appendMemory($first, 'Instinct: press for proof before trusting the room.');

    expect($duped)->toBe($first);

    // Spacing variant: stem matches, replaces with the new spacing — no line added.
    $spaced = $engine->appendMemory($first, 'Instinct:   press for proof before trusting the room.');

    expect(mb_substr_count($spaced, "\n"))->toBe(0)
        ->and($engine->appendMemory($first, 'Completely different instinct about caution.'))->not->toBe($first);

    // Stem variant: same observation, evolved phrasing — replaces, not accumulates.
    $evolved = $engine->appendMemory(
        'Emmit is the kind of man who opens the door.',
        'Emmit is the kind of man who opens the door — and then reaches for the nearest warm thing.',
    );

    expect(mb_substr_count($evolved, "\n"))->toBe(0)
        ->and($evolved)->toContain('reaches for the nearest warm thing');

    $merged = $engine->mergeStateDelta(
        [
            ...$engine->emptyWorldState(),
            'player_style' => ['Presses for proof'],
        ],
        [
            'location' => null,
            'conditions' => null,
            'items' => null,
            'object_states' => null,
            'relationship_updates' => null,
            'world_flags' => null,
            'knowledge' => null,
            'notes' => null,
            'player_style' => ['presses for proof', 'Presses for proof'],
            'unresolved_promises' => null,
            'emotional_ledger_entries' => null,
        ],
    );

    expect($merged['player_style'])->toBe(['Presses for proof']);

    // Stem-based replacement in player_style: evolved phrasing replaces stale.
    $styleMerged = $engine->mergeStateDelta(
        [
            ...$engine->emptyWorldState(),
            'player_style' => ['Player is using Lilys drawing as a threshold object'],
        ],
        [
            'location' => null,
            'conditions' => null,
            'items' => null,
            'object_states' => null,
            'relationship_updates' => null,
            'world_flags' => null,
            'knowledge' => null,
            'notes' => null,
            'player_style' => ['Player is using Lilys drawing as a pressure valve'],
            'unresolved_promises' => null,
            'emotional_ledger_entries' => null,
        ],
    );

    expect($styleMerged['player_style'])->toBe(['Player is using Lilys drawing as a pressure valve']);
});

it('soft-holds malformed progress without inventing an exit and without a second agent call', function () {
    $fixture = boilerResolverFixture();
    $engine = app(ChaosEngineService::class);
    $method = (new ReflectionClass($engine))->getMethod('validateBoilerNarrationResult');

    $result = [
        'visible_suggestions' => [],
        'choices' => [],
        'moment_progress' => [
            'moment_key' => 'WRONG_KEY',
            'exit_condition_met' => true,
            'moment_footnote' => 'Keep pressure local while the metadata is repaired.',
        ],
    ];
    $method->invokeArgs($engine, [&$result, $fixture['game'], false]);

    expect($result['moment_progress']['moment_key'])->toBe('S1_M01')
        ->and($result['moment_progress']['exit_condition_met'])->toBeFalse()
        ->and($result['moment_progress']['moment_footnote'])->toBe(
            'Keep pressure local while the metadata is repaired.'
        );

    // Soft structural hold is in-process only — there is no second callAgent for Boiler repair.
    expect((new ReflectionClass($engine))->hasMethod('callAgent'))->toBeTrue();
});

it('catches the pointer up to the card the prose has already reached', function () {
    $fixture = boilerResolverFixture();
    $third = $fixture['cut']->moments()->create([
        'moment_key' => 'S1_M03',
        'moment_order' => 3,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S1_M03', 'choice_ids' => []],
    ]);
    $result = boilerResolverResult('S1_M01', true, 'They left the nursery by the window and are over the rooftops now.');
    $result['moment_progress']['caught_up_to_moment_key'] = 'S1_M03';

    $response = app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game'],
        $fixture['source'],
        'Fly out of the window.',
        $result,
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );
    $game = $fixture['game']->fresh();

    expect($game->current_playable_moment_id)->toBe($third->id)
        ->and($game->playable_moment_turn_count)->toBe(0)
        ->and($response->moment_result)->toBe('advanced')
        ->and((bool) $response->exit_condition_met)->toBeTrue();
});

it('never catches up past a card that carries an authored choice', function () {
    $fixture = boilerResolverFixture();
    $fixture['second']->update([
        'scene_card' => ['moment_key' => 'S1_M02', 'choice_ids' => ['S1_C1']],
    ]);
    $fixture['cut']->moments()->create([
        'moment_key' => 'S1_M03',
        'moment_order' => 3,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S1_M03', 'choice_ids' => []],
    ]);
    $result = boilerResolverResult('S1_M01', true, 'They are already past the choice in the prose.');
    $result['moment_progress']['caught_up_to_moment_key'] = 'S1_M03';

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game'],
        $fixture['source'],
        'Keep going.',
        $result,
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['second']->id);
});

it('takes a far catch-up once the narrator reports the same card on consecutive turns', function () {
    $fixture = boilerResolverFixture();
    $fixture['second']->update([
        'scene_card' => ['moment_key' => 'S1_M02', 'choice_ids' => ['S1_C1']],
    ]);
    $third = $fixture['cut']->moments()->create([
        'moment_key' => 'S1_M03',
        'moment_order' => 3,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S1_M03', 'choice_ids' => []],
    ]);
    $sessions = $fixture['adaptation']->sessionAdaptations()->get();
    $resolver = app(PlayableMomentResolver::class);

    $first = boilerResolverResult('S1_M01', false, 'They are already out of the window.');
    $first['moment_progress']['caught_up_to_moment_key'] = 'S1_M03';
    $resolver->applyPlayerTurn($fixture['game']->fresh(), $fixture['source'], 'Keep going.', $first, [], $sessions);

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['first']->id)
        ->and($fixture['game']->fresh()->moment_footnote['catch_up_candidate'])->toBe('S1_M03');

    $secondSource = $fixture['game']->prompts()->create([
        'session_number' => 1,
        'response' => '<p>Still behind.</p>',
        'choices' => [],
    ]);
    $second = boilerResolverResult('S1_M01', false, 'Over the rooftops now.');
    $second['moment_progress']['caught_up_to_moment_key'] = 'S1_M03';
    $resolver->applyPlayerTurn($fixture['game']->fresh(), $secondSource, 'Keep going.', $second, [], $sessions);

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($third->id);
});

it('bounds a Continue at the next card that carries an authored decision', function () {
    $fixture = boilerResolverFixture();
    $fixture['second']->update([
        'scene_card' => ['moment_key' => 'S1_M02', 'title' => 'The Window', 'choice_ids' => ['S1_C1']],
    ]);
    $fixture['cut']->moments()->create([
        'moment_key' => 'S1_M03',
        'moment_order' => 3,
        'source_event_ids' => [],
        'scene_card' => ['moment_key' => 'S1_M03', 'choice_ids' => ['S1_C2']],
    ]);

    $bound = app(ChaosEngineService::class)->continueBoundInstruction($fixture['game']->fresh());

    expect($bound)->toContain('S1_M02 "The Window"')
        ->and($bound)->toContain('caught_up_to_moment_key=S1_M02')
        ->and($bound)->not->toContain('S1_M03');
});

it('treats a later card named as moment_key as a catch-up instead of holding the stale card', function () {
    $fixture = boilerResolverFixture();

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game'],
        $fixture['source'],
        'Follow him out.',
        boilerResolverResult('S1_M02', true, 'The player followed Peter out of the nursery.'),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['second']->id);
});

it('ignores a catch-up key that names an earlier or unknown card', function () {
    $fixture = boilerResolverFixture();
    $fixture['game']->update(['current_playable_moment_id' => $fixture['second']->id]);
    $result = boilerResolverResult('S1_M02', false, 'Still in the second room.');
    $result['moment_progress']['caught_up_to_moment_key'] = 'S1_M01';

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Look around.',
        $result,
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['second']->id);
});

it('advances a choice-free card the narrator keeps holding past gravity', function () {
    $fixture = boilerResolverFixture();
    $fixture['first']->update([
        'scene_card' => ['moment_key' => 'S1_M01', 'choice_ids' => [], 'turn_budget' => ['target_turns' => 4, 'gravity_after' => 5]],
    ]);
    $fixture['game']->update(['playable_moment_turn_count' => 5]);

    $response = app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Wake John.',
        boilerResolverResult('S1_M01', false, 'The player woke John.'),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );
    $game = $fixture['game']->fresh();

    expect($game->current_playable_moment_id)->toBe($fixture['second']->id)
        ->and($game->playable_moment_turn_count)->toBe(0)
        ->and($response->moment_result)->toBe('advanced')
        ->and((bool) $response->exit_condition_met)->toBeTrue();
});

it('holds a card with an untaken branching choice even past gravity', function () {
    $fixture = boilerDecisionCardFixture();
    $fixture['first']->update([
        'scene_card' => ['moment_key' => 'S1_M01', 'choice_ids' => ['S1_C1'], 'turn_budget' => ['target_turns' => 4, 'gravity_after' => 5]],
    ]);
    $fixture['game']->update(['playable_moment_turn_count' => 7]);

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Look around.',
        boilerResolverResult('S1_M01', false, 'The player looked around.'),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['first']->id);
});

it('releases an emotional-choice card the narrator keeps holding past gravity', function () {
    $fixture = boilerResolverFixture();
    $fixture['first']->update([
        'scene_card' => ['moment_key' => 'S1_M01', 'choice_ids' => ['S1_E2'], 'turn_budget' => ['target_turns' => 4, 'gravity_after' => 5]],
    ]);
    $fixture['game']->update(['playable_moment_turn_count' => 5]);

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Look around.',
        boilerResolverResult('S1_M01', false, 'The player looked around.'),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['second']->id);
});

function boilerDecisionCardFixture(): array
{
    $fixture = boilerResolverFixture();
    $fixture['first']->update([
        'scene_card' => ['moment_key' => 'S1_M01', 'choice_ids' => ['S1_C1'], 'next_moment_key' => 'S1_M02'],
    ]);
    $fixture['session']->update([
        'session_choice_design' => [
            'branching_choices' => [[
                'choice_id' => 'S1_C1',
                'options' => [
                    ['label' => 'A', 'text' => 'Go'],
                    ['label' => 'B', 'text' => 'Stay'],
                    ['label' => 'C', 'text' => 'Bargain'],
                ],
            ]],
            'emotional_choices' => [],
        ],
    ]);

    return $fixture;
}

it('holds a decision card until its authored branching choice is taken', function () {
    $fixture = boilerDecisionCardFixture();

    $response = app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Talk with him.',
        boilerResolverResult('S1_M01', true, 'The player kept talking with him.'),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );
    $game = $fixture['game']->fresh();

    expect($game->current_playable_moment_id)->toBe($fixture['first']->id)
        ->and($response->moment_result)->toBe('retained')
        ->and((bool) $response->exit_condition_met)->toBeFalse()
        ->and($game->moment_footnote['choice_blocked_turns'])->toBe(1);
});

it('does not let a catch-up jump past an untaken choice on the current card', function () {
    $fixture = boilerDecisionCardFixture();
    $result = boilerResolverResult('S1_M01', true, 'The prose ran ahead.');
    $result['moment_progress']['caught_up_to_moment_key'] = 'S1_M02';

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Keep going.',
        $result,
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['first']->id);
});

it('advances a decision card once a typed action resolves the choice', function () {
    $fixture = boilerDecisionCardFixture();
    $result = array_merge(
        boilerResolverResult('S1_M01', true, 'The player chose to go.'),
        ['defining_choice_id' => 'S1_C1', 'defining_path_label' => 'A', 'defining_choice_line' => 'She goes.'],
    );

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'I go with him.',
        $result,
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );
    $game = $fixture['game']->fresh();

    expect($game->current_playable_moment_id)->toBe($fixture['second']->id)
        ->and($game->defining_choice_id)->toBe('S1_C1');
});

it('releases a decision card after repeated blocked exits so the player is never trapped', function () {
    $fixture = boilerDecisionCardFixture();
    $fixture['game']->update([
        'moment_footnote' => ['moment_key' => 'S1_M01', 'text' => null, 'choice_blocked_turns' => 2],
    ]);

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Keep talking.',
        boilerResolverResult('S1_M01', true, 'The player kept talking.'),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['second']->id);
});

it('does not gate a card that only carries an emotional choice', function () {
    $fixture = boilerResolverFixture();
    $fixture['first']->update([
        'scene_card' => ['moment_key' => 'S1_M01', 'choice_ids' => ['S1_E1'], 'next_moment_key' => 'S1_M02'],
    ]);
    $fixture['session']->update([
        'session_choice_design' => [
            'branching_choices' => [],
            'emotional_choices' => [['choice_id' => 'S1_E1', 'options' => [['label' => 'A', 'text' => 'Soft']]]],
        ],
    ]);

    app(PlayableMomentResolver::class)->applyPlayerTurn(
        $fixture['game']->fresh(),
        $fixture['source'],
        'Move on.',
        boilerResolverResult('S1_M01', true, 'The player moved on.'),
        [],
        $fixture['adaptation']->sessionAdaptations()->get(),
    );

    expect($fixture['game']->fresh()->current_playable_moment_id)->toBe($fixture['second']->id);
});
