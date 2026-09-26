<?php

declare(strict_types=1);

use App\Services\Adaptation\SessionEventLoader;
use App\Services\ChaosEngineService;

function memoryContextEngine(): ChaosEngineService
{
    return new ChaosEngineService(new SessionEventLoader());
}

/**
 * @param  array<int, mixed>  $arguments
 */
function callMemoryContextPrivate(string $method, array $arguments): mixed
{
    return (new ReflectionMethod(ChaosEngineService::class, $method))
        ->invokeArgs(memoryContextEngine(), $arguments);
}

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function memoryContextDelta(array $changes): array
{
    return [
        'location' => null,
        'conditions' => null,
        'items' => null,
        'object_states' => null,
        'relationship_updates' => null,
        'world_flags' => null,
        'knowledge' => null,
        'notes' => null,
        'player_style' => null,
        'unresolved_promises' => null,
        'emotional_ledger_entries' => null,
        ...$changes,
    ];
}

it('lets a changed fact supersede the old one but keeps facts about different people apart', function (): void {
    $engine = memoryContextEngine();

    $merged = $engine->mergeStateDelta(
        [...$engine->emptyWorldState(), 'knowledge' => ['The guard trusts the protagonist', 'Peter can fly using fairy dust']],
        memoryContextDelta(['knowledge' => ['The guard no longer trusts the protagonist', 'John can fly using fairy dust']]),
    );

    expect($merged['knowledge'])->toBe([
        'Peter can fly using fairy dust',
        'The guard no longer trusts the protagonist',
        'John can fly using fairy dust',
    ]);
});

it('treats differently written keys for one tracked thing as the same key', function (): void {
    $engine = memoryContextEngine();

    $merged = $engine->mergeStateDelta(
        [...$engine->emptyWorldState(), 'object_states' => ['Stable_Door: locked']],
        memoryContextDelta(['object_states' => ['the stable door: forced open']]),
    );

    expect($merged['object_states'])->toBe(['the stable door: forced open']);
});

it('keeps every stored ledger entry but shows only distinct ones', function (): void {
    $engine = memoryContextEngine();
    $ledger = [
        ['category' => 'fear', 'entry' => 'Wendy fears the crocodile in the lagoon'],
        ['category' => 'fear', 'entry' => 'Wendy fears the crocodile in the lagoon'],
    ];

    $merged = $engine->mergeStateDelta(
        [...$engine->emptyWorldState(), 'emotional_ledger' => $ledger],
        memoryContextDelta([]),
    );
    $tier3 = callMemoryContextPrivate('formatTier3', [['emotional_ledger' => $ledger]]);

    expect($merged['emotional_ledger'])->toHaveCount(2)
        ->and(mb_substr_count($tier3, 'Wendy fears the crocodile'))->toBe(1);
});

it('brings an older fact back when this turn reaches for it', function (): void {
    $knowledge = [
        'The crocodile swallowed a ticking clock',
        ...array_map(fn (int $i): string => "Harbour detail number {$i} recorded", range(1, 29)),
    ];

    $tier1 = callMemoryContextPrivate('formatTier1', [
        ['location' => 'the lagoon', 'knowledge' => $knowledge],
        'I listen for the crocodile and its clock',
        '',
    ]);

    expect($tier1)
        ->toContain('The crocodile swallowed a ticking clock')
        ->toContain('Harbour detail number 29 recorded')
        ->not->toContain('Harbour detail number 12 recorded');
});

it('labels facts the last narration already stated instead of offering them again', function (): void {
    $tier1 = callMemoryContextPrivate('formatTier1', [
        ['location' => 'the nursery', 'knowledge' => ['Wendy sewed the shadow back on', 'The window latch is broken']],
        'I open the window',
        'Wendy sewed the shadow back on with small careful stitches.',
    ]);

    expect($tier1)
        ->toContain('Already on the page in your last turn')
        ->toMatch('/Already on the page[^\n]*Wendy sewed the shadow back on/')
        ->toMatch('/Knowledge[^\n]*The window latch is broken/');
});

it('measures phrasing the narrator keeps reusing across recent turns', function (): void {
    $block = callMemoryContextPrivate('recentPhrasingBlock', [[
        'The stars wheel slowly overhead as you climb. Peter laughs.',
        'Far below, the stars wheel slowly overhead and the sea glitters.',
        'You drift on — the stars wheel slowly overhead once more.',
    ]]);

    expect($block)
        ->toContain('stars wheel slowly overhead')
        ->toContain('Your last turn used 1 dash');
});

it('says nothing about phrasing before there are two turns to compare', function (): void {
    expect(callMemoryContextPrivate('recentPhrasingBlock', [['Only one turn so far.']]))->toBe('');
});

it('frames length as density and rations signature moves in the narrator template', function (): void {
    $template = (string) file_get_contents(
        resource_path('views/ai/agents/chaos/runtime-narrator-template.blade.php')
    );
    $engine = (string) file_get_contents(app_path('Services/ChaosEngineService.php'));

    expect($template)
        ->toContain('SIGNATURE MOVES ARE SEASONING, NOT A CHECKLIST')
        ->toContain('WORD ECONOMY: every word in the prose must earn its place.')
        ->toContain('- WORD VALUE:')
        ->toContain('- NOT THE SAME TRICK TWICE:')
        ->toContain('STORY OVERTOOK THE CARD')
        ->toContain('caught_up_to_moment_key')
        ->and($engine)
        ->not->toContain('Acceptable band')
        ->toContain('not an amount to fill')
        ->toContain('SUPERSEDE WHAT CHANGED');
});

it('asks the narrator to report when the story has overtaken the active card', function (): void {
    $schema = (string) file_get_contents(app_path('Ai/Agents/Chaos/ChaosNarrationSchema.php'));

    expect($schema)->toContain("'caught_up_to_moment_key' => \$schema->string()->required()");
});
