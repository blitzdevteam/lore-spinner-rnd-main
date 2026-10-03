<?php

declare(strict_types=1);

use App\Ai\Adaptation\ColdOpenRepairContext;
use App\Ai\Adaptation\ColdOpenRepairValidator;
use App\Ai\Agents\Adaptation\ColdOpenRepairAgent;
use Laravel\Ai\Attributes\Model;

function peterPanDiagnosis(): array
{
    return [
        'situation_stakes_world' => [
            'established' => 'Night in the Darling nursery; a strange boy has failed to fasten his detached shadow and is asking Wendy to leave home for Neverland.',
            'deferred' => 'Tinker Bell’s jealousy and the mechanics of fairy dust are withheld.',
        ],
        'first_choice_spec' => [
            'setup_prose' => 'He catches you by the hand and draws you toward the open window.',
            'threshold_stake' => 'Whether Wendy crosses from home into Peter’s world now.',
            'question' => 'What do you do?',
            'option_1' => 'You go with Peter at once.',
            'option_2' => 'You hold back and insist that John and Michael must be taught to fly.',
            'option_3_unexpected' => 'You keep Peter from the window and make him prove himself.',
        ],
        'format_specific_cut' => [
            'must_reintroduce' => [
                [
                    'fact' => 'Peter’s shadow is a detachable, physical thing, and he cannot make it stick.',
                    'marker' => 'COLD_OPEN',
                ],
                [
                    'fact' => 'Peter entered by the nursery window.',
                    'marker' => 'COLD_OPEN',
                ],
                [
                    'fact' => 'Wendy has already heard of Peter.',
                    'marker' => 'LATER',
                ],
                [
                    'fact' => 'Tinker Bell is present in the nursery world.',
                    'marker' => 'LATER',
                ],
                [
                    'fact' => 'John and Michael are asleep nearby.',
                    'marker' => 'COLD_OPEN',
                ],
            ],
        ],
        'cold_open' => 'A boy is crying on the floor. Presently the shadow behaves. What do you do?',
        'start_event_position' => 39,
    ];
}

function approvedPeterPanOpening(): string
{
    return <<<'TEXT'
It is the middle of the night, and this is your nursery — the top room of a tall house in London, where you and your two brothers have slept every night of your lives. You are Wendy Darling, the eldest, and just now you are the only one awake. Across the room, John and Michael sleep on. Your mother and father are still out at their dinner party, so the house is quiet — quiet, and not quite right. The night-light has burned out. The window your mother always latches stands wide open to the cold.

And there is a boy on the floor.

He is no older than you, dressed all in skeleton leaves, and he is crying — not the way one cries for help, but the way a child cries over a toy that has broken. The broken thing is his own shadow, come loose of him and refusing to go back on. He has been trying to fasten it with a cake of soap, which is, of course, no way to mend a shadow at all.

His name, he tells you — as though you ought to have known it all along — is Peter Pan. And perhaps you did know it, for you have heard of him in stories. He has flown in from a place called Neverland, where there are boys with no mothers, and no one to tuck them in or tell them tales at night. That, he says, is what he came for. Someone to give the lost boys their stories. Someone to be their mother.

Then he holds out his hand, bare and certain, and asks you to come with him — out the open window, off into the dark, this very night.
TEXT;
}

function peterPanRepairPrompt(): string
{
    $context = (new ColdOpenRepairContext)->build(
        'Peter Pan: Wendy\'s Neverland',
        'peter-pan',
        1,
        'Wendy Darling',
        [
            'voice_anchor' => [['mode' => 'aside', 'prose' => 'Which is, of course, no way to mend a shadow at all.']],
            'anchor_card' => ['Keep the Barrie aside.'],
        ],
        peterPanDiagnosis(),
        [
            [
                'position' => 39,
                'title' => 'Wendy wakes',
                'content' => 'The boy tries to stick his shadow on with a cake of soap.',
            ],
        ],
    );

    return view('ai.agents.adaptation.cold-open-repair.system-prompt', $context)->render()
        ."\n"
        .view('ai.agents.adaptation.cold-open-repair.prompt', $context)->render();
}

it('pulls Peter Pan facts into the repair prompt and forbids a closing question', function (): void {
    $prompt = peterPanRepairPrompt();

    expect($prompt)
        ->toContain('Wendy Darling')
        ->toContain('Peter’s shadow is a detachable, physical thing')
        ->toContain('Peter entered by the nursery window.')
        ->toContain('John and Michael are asleep nearby.')
        ->toContain('cake of soap')
        ->toContain('Which is, of course, no way to mend a shadow at all.')
        ->toContain('RULE 0 — ALWAYS SECOND PERSON, PRESENT TENSE')
        ->toContain('THE FOUR REQUIRED BEATS')
        ->toContain('CLARITY TEST')
        ->toContain('Do not write a closing question.')
        ->toContain('This question is for the buttons. Do not write it: What do you do?')
        ->toContain('You go with Peter at once.')
        ->toContain('WORKED FIRST SCREEN — what the user sees (Peter Pan / Wendy Darling)')
        ->toContain('Then he holds out his hand, bare and certain')
        ->toContain('COMPLETE CURRENT BOILER ENTRY-POINT DIAGNOSIS')
        ->not->toContain("this very night.\n\nDo you take his hand?");
});

it('drops the editorial reason from a cold-open fact', function (): void {
    $context = (new ColdOpenRepairContext)->build(
        'Peter Pan: Wendy\'s Neverland',
        'peter-pan',
        1,
        'Wendy Darling',
        ['anchor_card' => ['Keep the aside.']],
        [
            'situation_stakes_world' => ['established' => 'Night in the nursery.'],
            'format_specific_cut' => [
                'must_reintroduce' => [[
                    'fact' => 'Peter’s shadow is a detachable, physical thing, and the immediate problem is that he cannot make it stick; this matters because Wendy’s first bond with him is practical caretaking, not abstract fascination.',
                    'marker' => 'COLD_OPEN',
                ]],
            ],
        ],
        [],
    );

    expect($context['cold_open_facts'])->toBe([
        'Peter’s shadow is a detachable, physical thing, and the immediate problem is that he cannot make it stick',
    ]);
});

it('keeps Peter Pan as a labeled craft example while filling another title from Boiler', function (): void {
    $context = (new ColdOpenRepairContext)->build(
        'Anima Machina',
        'anima-machina',
        1,
        'Nora',
        ['anchor_card' => ['Short sentences.']],
        [
            'situation_stakes_world' => ['established' => 'You are in a dive lab.'],
            'first_choice_spec' => [
                'setup_prose' => 'A shard is set down between you.',
                'question' => 'What do you do with the shard?',
                'option_1' => 'Take the shard.',
            ],
        ],
        [],
    );

    $system = view('ai.agents.adaptation.cold-open-repair.system-prompt', $context)->render();

    expect($system)
        ->toContain('Nora')
        ->toContain('WORKED FIRST SCREEN — what the user sees (Peter Pan / Wendy Darling)')
        ->toContain('import Peter Pan details into another title.')
        ->toContain('A shard is set down between you.');
});

it('runs the repair on Sonnet 4.6', function (): void {
    $model = (new ReflectionClass(ColdOpenRepairAgent::class))
        ->getAttributes(Model::class)[0]
        ->newInstance();

    expect($model->value)->toBe('claude-sonnet-4-6');
});

it('keeps a string reintroduction list for titles that store one', function (): void {
    $context = (new ColdOpenRepairContext)->build(
        'Anima Machina',
        'anima-machina',
        1,
        'Nora',
        ['anchor_card' => ['Short sentences.']],
        [
            'format_specific_cut' => [
                'must_reintroduce' => 'Nora’s competence as a diver, and the RENDAN label as the first live threshold.',
            ],
            'situation_stakes_world' => ['established' => 'You are in an Amora dive lab.'],
        ],
        [],
    );

    expect($context['cold_open_facts'])->toBe([
        'Nora’s competence as a diver, and the RENDAN label as the first live threshold.',
    ]);
});

it('windows source events from the diagnosed start', function (): void {
    $events = [];

    foreach (range(1, 55) as $position) {
        $events[] = [
            'position' => $position,
            'title' => "Event {$position}",
            'content' => "Content {$position}",
        ];
    }

    $window = (new ColdOpenRepairContext)->sourceWindow($events, 39);

    expect(array_column($window, 'position'))->toBe(range(39, 50));
});

it('accepts the approved Peter Pan opening and rejects the closing question', function (): void {
    $validator = new ColdOpenRepairValidator;
    $approved = approvedPeterPanOpening();

    expect($validator->failures($approved, 'Wendy Darling'))->toBe([])
        ->and($validator->wordCount($approved))->toBeLessThanOrEqual(350)
        ->and($validator->failures($approved."\n\nDo you take his hand?", 'Wendy Darling'))
        ->toContain('The cold open ends on a question to the player. End on the action of the choice instead.');
});
