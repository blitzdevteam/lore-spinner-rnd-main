<?php

declare(strict_types=1);

use App\Services\Adaptation\SessionEventLoader;
use App\Services\ChaosEngineService;

function styleContractEngine(): ChaosEngineService
{
    return new ChaosEngineService(new SessionEventLoader());
}

/**
 * @param  array<string, mixed>  $arguments
 */
function callEnginePrivate(string $method, array $arguments): mixed
{
    return (new ReflectionMethod(ChaosEngineService::class, $method))
        ->invokeArgs(styleContractEngine(), $arguments);
}

it('keeps the turn instruction free of demand and naming primes', function (): void {
    $instructions = styleContractEngine()->turnInstructions('WENDY');

    expect($instructions)
        ->toContain('KEEP THE SHAPE OF THE ACTION')
        ->toContain('A QUESTION IS NOT A DECISION')
        ->toContain('WRITE FORWARD')
        ->not->toContain('Name it')
        ->not->toContain('name it')
        ->not->toContain('demand her')
        ->not->toContain('—');
});

it('keeps the sparse state contract generic and free of verdicts', function (): void {
    $contract = styleContractEngine()->sparseStateDeltaContract();

    expect($contract)
        ->toContain('CLAIMS ARE NOT FACTS')
        ->toContain('SENSATIONS ARE NOT POWERS')
        ->toContain('DROP WHAT IS NO LONGER TRUE')
        ->not->toContain('Peter')
        ->not->toContain('Tiger Lily')
        ->not->toContain('—');
});

it('frames tier one as a record, not a mention list, and caps growing fields', function (): void {
    $knowledge = array_map(fn (int $i): string => "fact {$i}", range(1, 30));
    $notes = array_map(fn (int $i): string => "note {$i}", range(1, 30));
    $style = array_map(fn (int $i): string => "tendency {$i}", range(1, 10));

    $tier1 = callEnginePrivate('formatTier1', [[
        'location' => 'the nursery',
        'items' => ['a thimble'],
        'knowledge' => $knowledge,
        'notes' => $notes,
        'player_style' => $style,
    ]]);

    expect($tier1)
        ->toContain('It is not a list of things to mention.')
        ->toContain('fact 30')
        ->toContain('fact 19')
        ->not->toContain('fact 18')
        ->toContain('note 30')
        ->not->toContain('note 18')
        ->toContain('tendency 10')
        ->toContain('tendency 7')
        ->not->toContain('tendency 6')
        ->toContain('their latest action always outranks these');
});

it('replaces the callback-material dump with a capped background line in tier two', function (): void {
    $relics = array_map(fn (int $i): string => "Relic {$i}: intact", range(1, 20));

    $tier2 = callEnginePrivate('formatTier2', [[
        'object_states' => ['Lantern: lit', ...$relics],
        'relationship_updates' => [],
        'world_flags' => [],
    ], [['title' => 'The cellar', 'content' => 'The lantern swings overhead.']]]);

    expect($tier2)
        ->not->toContain('callback material')
        ->toContain('Object states: Lantern: lit')
        ->toContain('Other recorded state (background for consistency only')
        ->toContain('Relic 20: intact')
        ->toContain('Relic 9: intact')
        ->not->toContain('Relic 8: intact');
});

it('keeps Continue a compact authored read-ahead without assigning a branch', function (): void {
    $action = (string) file_get_contents(app_path('Actions/Game/ProcessGameTurnAction.php'));

    expect($action)
        ->toContain('wants the authored story told')
        ->toContain('every fact, rule, object change, location change')
        ->toContain('Never select or commit A/B/C')
        ->toContain('stop with that decision');
});

it('replaces the critique footnote and repetition primes in the narrator template', function (): void {
    $template = (string) file_get_contents(
        resource_path('views/ai/agents/chaos/runtime-narrator-template.blade.php')
    );

    expect($template)
        ->toContain('- NO REHASHING:')
        ->toContain('BOILER PLAYER-DIRECTION FOOTNOTE')
        ->toContain('WHERE THEY ARE STEERING')
        ->toContain('USING THE INCOMING FOOTNOTE')
        ->toContain('PAY OFF, DO NOT REMIND')
        ->toContain('YOUR OWN SLIPS ARE THE EXCEPTION')
        ->not->toContain('OBEY IT')
        ->not->toContain('never correct yourself')
        ->not->toContain('PREFER MEMORY TO INVENTION')
        ->not->toContain('PROVOCATION');
});

it('makes Continue read on like a book and defer to the decision directive', function (): void {
    $engine = styleContractEngine();

    expect($engine->turnInstructions('WENDY', true))
        ->toContain('like the next pages of the book')
        ->toContain('never invent a preference')
        ->toContain('slow down and stop so the player can steer')
        ->and($engine->turnInstructions('WENDY'))
        ->not->toContain('THIS IS A CONTINUE');
});

it('lets dashes follow the anchor card instead of banning them outright', function (): void {
    expect(styleContractEngine()->turnInstructions('WENDY'))
        ->toContain('DASHES FOLLOW THE ANCHOR CARD')
        ->toContain('a speaker breaking off mid-line')
        ->not->toContain('—');
});

it('only accepts an exit once every outcome is on the page', function (): void {
    $block = callEnginePrivate('buildActiveMomentBlock', [[
        'moment_key' => 'S1_M03',
        'turn_count' => 1,
        'target_turns' => 4,
        'exit_condition' => 'Tink is loose and Wendy has seen a real fairy.',
    ]]);

    expect($block)
        ->toContain('If EVERY outcome in the exit condition has already happened on the page')
        ->toContain('a character who must appear has not appeared')
        ->toContain('so is leaving one with its scene unplayed');
});

it('marks a card with an untaken branching choice as a decision point', function (): void {
    $block = callEnginePrivate('buildActiveMomentBlock', [[
        'moment_key' => 'S1_M02',
        'turn_count' => 1,
        'target_turns' => 5,
        'pending_branching_choice_ids' => ['S1_C1'],
    ]]);

    expect($block)
        ->toContain('DECISION POINT: this card carries the authored choice S1_C1')
        ->toContain('the three visible_suggestions ARE its A, B, and C options')
        ->toContain('The runtime holds this card until it is.');

    expect(callEnginePrivate('buildActiveMomentBlock', [['moment_key' => 'S1_M02']]))
        ->not->toContain('DECISION POINT');
});

it('lets Continue stop at an unseen decision and pass a declined one without weakening the choice gate', function (): void {
    $context = [
        'moment_key' => 'S1_M02',
        'turn_count' => 1,
        'target_turns' => 5,
        'pending_branching_choice_ids' => ['S1_C1'],
    ];
    $normal = callEnginePrivate('buildActiveMomentBlock', [$context]);
    $unseen = callEnginePrivate('buildActiveMomentBlock', [$context, false, true]);
    $declined = callEnginePrivate('buildActiveMomentBlock', [[...$context, 'card_choice_presented' => true], false, true]);

    expect($normal)->toContain('The runtime holds this card until it is.')
        ->and($unseen)->toContain('CONTINUE SLOWS DOWN HERE')
        ->and($declined)->toContain('pressed Continue instead')
        ->and($declined)->toContain('CONTINUE NARRATES LIKE A BOOK')
        ->and($unseen)->not->toContain('The runtime holds this card until it is.')
        ->and($declined)->not->toContain('The runtime holds this card until it is.');
});

it('ends every turn instruction with a mode-specific hard self-check', function () {
    $engine = app(ChaosEngineService::class);
    $normal = $engine->turnInstructions('WENDY');
    $continue = $engine->turnInstructions('WENDY', true);

    expect($normal)->toContain('HARD CHECK BEFORE YOU RETURN')
        ->and($normal)->toContain('DO NOT INVENT CANON')
        ->and($normal)->not->toContain('REPORT WHERE THE PROSE ACTUALLY STOPPED')
        ->and($continue)->toContain('REPORT WHERE THE PROSE ACTUALLY STOPPED')
        ->and($normal)->toEndWith('Return the full structured object.');
});

it('keeps symbolic memory a factual record the narration must not echo', function () {
    $template = file_get_contents(resource_path('views/ai/agents/chaos/runtime-narrator-template.blade.php'));
    $schema = file_get_contents(app_path('Ai/Agents/Chaos/ChaosNarrationSchema.php'));

    expect($template)->toContain('It is a record, not prose')
        ->and($template)->not->toContain('interior weather')
        ->and($template)->not->toContain('what sort of self')
        ->and($schema)->toContain('Facts only: no imagery, no meaning');
});
