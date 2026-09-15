<?php

declare(strict_types=1);

use App\Services\Runtime\CompletionReceiptBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CompletionReceiptFixtures;

uses(DatabaseTransactions::class);

it('returns null when the session is not complete', function () {
    $game = CompletionReceiptFixtures::loadForShow(CompletionReceiptFixtures::game(
        gameAttributes: ['current_session_complete' => false],
    ));

    expect((new CompletionReceiptBuilder)->build($game))->toBeNull();
});

it('compiles a session ceremony from close design, ledger, and the chosen path', function () {
    $game = CompletionReceiptFixtures::loadForShow(CompletionReceiptFixtures::game());
    $receipt = (new CompletionReceiptBuilder)->build($game);

    expect($receipt)->not->toBeNull()
        ->and($receipt['mode'])->toBe('session')
        ->and($receipt['story_title'])->toBe('The Adventure of the Speckled Band')
        ->and($receipt['session_number'])->toBe(1)
        ->and($receipt['final_line'])->toBe('The light waits; so, now, does Holmes.')
        ->and($receipt['world_remembers'])->toHaveCount(3)
        ->and($receipt['world_remembers'][0])->toBe('You sat the case into one dreadful line.')
        ->and($receipt['world_remembers'])->not->toContain('An older, quieter fact the card should not prefer.')
        ->and($receipt['becoming']['identity'])->toBe('The partner who will enter the room.')
        ->and($receipt['becoming']['style_line'])->toBe('You test before you trust the dark.')
        ->and($receipt['commitment'])->toBe('You told Holmes you would go — but only after testing the signal once more in your mind.')
        ->and($receipt['what_waits']['next_opens'])->toBe('Session 2 opens on the vigil itself — in the spirit you chose.')
        ->and($receipt['what_waits']['unresolved_promise'])->toBe('Helen’s safety is not yet paid.')
        ->and($receipt['share_line'])->toBe('I know how the room is meant to kill. I have not entered it yet.')
        ->and($receipt)->not->toHaveKey('what_settled')
        ->and($receipt)->not->toHaveKey('became')
        ->and(json_encode($receipt))->not->toMatch('/chaotic|lawful|neutral/i')
        ->and(json_encode($receipt))->not->toContain('The player wants the truth to land softly.');
});

it('compiles a story ceremony without open loops on the last session', function () {
    $game = CompletionReceiptFixtures::loadForShow(CompletionReceiptFixtures::game(
        gameAttributes: [
            'current_session_number' => 2,
            'defining_path_label' => 'B',
            'defining_choice_line' => 'I chose how much of it the world gets to know.',
            'world_state' => [
                'knowledge' => ['The method is known: the room confessed under lamp-light.'],
                'emotional_ledger' => [
                    ['category' => 'promises_kept', 'entry' => 'You held the silent vigil.'],
                    ['category' => 'harm_refused', 'entry' => 'You refused to make Helen’s life a spectacle.'],
                ],
                'unresolved_promises' => ['This must never appear as an open loop on story end.'],
                'player_style' => ['Measured. Loyal to the living more than to the spectacle.'],
            ],
        ],
    ));

    $receipt = (new CompletionReceiptBuilder)->build($game);

    expect($receipt['mode'])->toBe('story')
        ->and($receipt['final_line'])->toBe('The case is solved; its meaning, and its measure, remain in your hands.')
        ->and($receipt['what_settled'])->toContain('Helen is safe.')
        ->and($receipt['record'])->toContain('You held the silent vigil.')
        ->and($receipt['became']['identity'])->toBe('The partner who will enter the room.')
        ->and($receipt['leaves_behind'])->toBe('A room that will never look innocent again.')
        ->and($receipt)->not->toHaveKey('what_waits')
        ->and($receipt)->not->toHaveKey('commitment')
        ->and($receipt)->not->toHaveKey('world_remembers')
        ->and(json_encode($receipt))->not->toContain('This must never appear as an open loop on story end.');
});

it('falls back to last narration when close design is missing', function () {
    $game = CompletionReceiptFixtures::loadForShow(CompletionReceiptFixtures::game(
        closeDesign: [
            'session_end_choice' => [
                'choice_question' => '',
                'option_a' => ['text' => '', 'next_session_opens' => ''],
                'option_b' => ['text' => '', 'next_session_opens' => ''],
                'option_c' => ['text' => '', 'next_session_opens' => ''],
                'final_line' => '',
            ],
            'resolution_prose' => '',
            'hook_transition' => '',
        ],
        gameAttributes: [
            'defining_path_label' => null,
            'defining_choice_line' => 'You named the trap before you entered it.',
            'alignment_scaffold' => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
            'world_state' => [
                'knowledge' => ['The ventilator is a mouth.'],
                'emotional_ledger' => [],
                'relationship_updates' => [],
                'player_style' => [],
                'unresolved_promises' => [],
            ],
        ],
    ));

    $game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>The night holds. The light waits with you.</p>',
        'choices' => [],
    ]);
    $game->load('prompts');

    $receipt = (new CompletionReceiptBuilder)->build($game);

    expect($receipt['final_line'])->toBe('The light waits with you.')
        ->and($receipt['world_remembers'])->toContain('The ventilator is a mouth.')
        ->and($receipt)->not->toHaveKey('becoming')
        ->and($receipt)->not->toHaveKey('commitment')
        ->and($receipt)->not->toHaveKey('what_waits');
});

it('omits identity when the story-native label contains raw alignment words', function () {
    $game = CompletionReceiptFixtures::loadForShow(CompletionReceiptFixtures::game(
        sessionMap: [
            'alignment_labels' => [
                [
                    'label' => 'Chaotic investigator',
                    'maps_to_internal' => 'lawful',
                    'behavioral_markers' => [],
                    'narrative_consequences' => '',
                    'voice_signature' => '',
                ],
            ],
        ],
    ));

    expect((new CompletionReceiptBuilder)->build($game))->not->toHaveKey('becoming')
        ->and(json_encode((new CompletionReceiptBuilder)->build($game)))
        ->not->toMatch('/chaotic|lawful|neutral/i');
});

it('does not lazy-load adaptation relations for a thin receipt', function () {
    $bare = CompletionReceiptFixtures::game()->fresh();

    expect($bare->relationLoaded('story'))->toBeFalse()
        ->and((new CompletionReceiptBuilder)->build($bare)['mode'])->toBe('session')
        ->and((new CompletionReceiptBuilder)->build($bare))->not->toHaveKey('becoming')
        ->and((new CompletionReceiptBuilder)->build($bare))->not->toHaveKey('final_line');
});
