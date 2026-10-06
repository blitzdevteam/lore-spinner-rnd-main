<?php

declare(strict_types=1);

use App\Services\ChaosEngineService;

function invokeEngine(string $method, mixed ...$arguments): mixed
{
    $engine = app(ChaosEngineService::class);

    return (new ReflectionClass($engine))->getMethod($method)->invoke($engine, ...$arguments);
}

it('gives full consequence guidance only in the session the choice was made', function () {
    $path = [
        'choice_session_number' => 1,
        'current_session_echo' => 'The cup is cracked.',
        'next_session_payoff' => 'Hook remembers the cup.',
        'narrator_behavior' => 'Let the crack show in small ways.',
        'hard_limits' => ['The cup never mends.'],
    ];

    $sameSession = invokeEngine('buildConsequenceEchoBlock', $path, 1);
    $nextSession = invokeEngine('buildConsequenceEchoBlock', $path, 2);
    $later = invokeEngine('buildConsequenceEchoBlock', $path, 4);

    expect($sameSession)->toContain('The cup is cracked.')
        ->and($sameSession)->toContain('The cup never mends.')
        ->and($nextSession)->toContain('Hook remembers the cup.')
        ->and($nextSession)->not->toContain('The cup is cracked.')
        ->and($nextSession)->toContain('settled history')
        ->and($later)->not->toContain('Hook remembers the cup.')
        ->and($later)->toContain('Let the crack show in small ways.');
});

it('archives session-scoped state as labelled history at a session boundary', function () {
    $engine = app(ChaosEngineService::class);
    $state = [
        ...$engine->emptyWorldState(),
        'items' => ['the acorn button'],
        'object_states' => ['window: open'],
        'world_flags' => ['peter_in_nursery: true'],
        'reintroduced_facts' => ['shadow'],
    ];

    $archived = $engine->archiveSessionState($state, 1);
    $merged = $engine->mergeStateDelta($archived, ['object_states' => ['window: shut']]);

    expect($archived['object_states'])->toBe([])
        ->and($archived['items'])->toBe(['the acorn button'])
        ->and($archived['history'])->toContain('Session 1: window: open')
        ->and($archived['history'])->toContain('Session 1: peter_in_nursery: true')
        ->and($merged['history'])->toBe($archived['history'])
        ->and($merged['reintroduced_facts'])->toBe(['shadow'])
        ->and($merged['object_states'])->toBe(['window: shut']);
});

it('closes along the author path without re-asking a declined ending choice', function () {
    $design = [
        'resolution_prose' => 'The nursery is quiet.',
        'session_end_choice' => [
            'choice_question' => 'Where does Wendy look?',
            'option_a' => ['text' => 'At the window'],
            'final_line' => 'And the star winks.',
        ],
    ];

    $asked = invokeEngine('buildSessionCloseBlock', $design, false);
    $declined = invokeEngine('buildSessionCloseBlock', $design, true);

    expect($asked)->toContain('present these three options')
        ->and($declined)->not->toContain('present these three options')
        ->and($declined)->toContain('SESSION-END CHOICE DECLINED')
        ->and($declined)->toContain('And the star winks.')
        ->and($declined)->toContain('The nursery is quiet.');
});

it('allows a continuity bridge before a later session cold open only when an ending is known', function () {
    $context = [
        'session_number' => 2,
        'cold_open' => 'You wake in the Never Land.',
        'previous_session_ending' => 'You stay in the nursery and shut the window.',
    ];

    $withEnding = invokeEngine('buildOpeningSection', $context, []);
    $withoutEnding = invokeEngine('buildOpeningSection', [...$context, 'previous_session_ending' => ''], []);

    expect($withEnding)->toContain('CONTINUITY BRIDGE')
        ->and($withEnding)->toContain('You stay in the nursery and shut the window.')
        ->and($withoutEnding)->not->toContain('CONTINUITY BRIDGE')
        ->and($withoutEnding)->toContain('The first words the player reads are the first words above.');
});
