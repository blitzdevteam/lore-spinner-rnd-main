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

it('keeps Continue from inventing protagonist decisions or rehashing', function (): void {
    $action = (string) file_get_contents(app_path('Actions/Game/ProcessGameTurnAction.php'));

    expect($action)
        ->toContain('Continue is not a decision by the protagonist.')
        ->toContain('give them no new lines')
        ->toContain('Do not re-describe the journey');
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
