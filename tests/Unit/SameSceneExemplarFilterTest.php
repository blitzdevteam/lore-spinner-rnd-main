<?php

declare(strict_types=1);

use App\Ai\Adaptation\RuntimeNarratorTemplateBuilder;
use App\Models\SessionAdaptation;
use App\Models\Story;

/**
 * Regression fixture from the real Wasteland session-1 runtime prompt
 * (docs/Boiler/Debug/Thomas log/wasteland-session-1-runtime-injected.txt).
 *
 * Two of these exemplars were translated from beats session 1 narrates. The
 * cold_tension one sat at the top of the Voice Anchor while the narrator opened
 * on that exact scene, and its wording surfaced in the output. Section 4A
 * already forbade reusing it in prose and the instruction did not hold, which
 * is why the exemplar is withheld at assembly instead.
 */
function wastelandExemplars(): array
{
    return [
        ['mode' => 'cold_tension', 'source' => 'Pontis and Emmit opening the sealed cargo door in the wing compartment and discovering something alive inside'],
        ['mode' => 'physical_action', 'source' => 'Emmit and the others scrambling for the escape pods as the garbage aircraft begins dumping its human cargo'],
        ['mode' => 'quiet_aftermath', 'source' => 'Emmit discovering the hidden aircraft in the forbidden chamber'],
        ['mode' => 'environmental', 'source' => "Arrival at Leo's encampment and fortress reveal"],
        ['mode' => 'dialogue_bearing', 'source' => 'Leo testing Emmit in the makeshift hangar'],
        ['mode' => 'emotional_weight', 'source' => "Emmit's hologram call with Lily, interrupted by Janet"],
        ['mode' => 'environmental', 'source' => 'Opening desert water-harvest sequence with children, plastic sheets, poles, and guarded labor'],
    ];
}

function wastelandMoments(): array
{
    return [
        [
            'moment_key' => 'S1_M01',
            'title' => 'The Sealed Hold (Cold open at the sealed cargo door. Fold the truck approach, auto-lit compartment, and burned overflow switch into fast establishing pressure, then breathe fully on the tapping and the seal choice.)',
            'runtime_goal' => 'Ground Emmit as an engineer through reflex, land his home-stake, and put his hands on a moral threshold he alone can cross.',
            'live_pressure' => 'The tapping is too steady to be metal, the seal is company-protected, and breaking it is dangerous, but someone may be alive inches away.',
        ],
        [
            'moment_key' => 'S1_M06',
            'title' => "It's Dumping (The plane banks, trash slides, and the dump opening grows. Survival race identified toward the escape pods.)",
            'runtime_goal' => 'Convert horror into active survival with a concrete escape objective under lethal industrial mechanics.',
            'live_pressure' => 'Tons of trash slide toward a widening rear opening as hundreds fight for their lives.',
        ],
        [
            'moment_key' => 'S1_M05',
            'title' => 'Among the Dumped (Waking on decomposing garbage inside the dumping aircraft. Reunion with the crew and the horror-scale reveal of hundreds of people.)',
            'runtime_goal' => 'Deliver the world-shift horror: Emmit is now the cargo he refused to see, surrounded by the disposed.',
            'live_pressure' => 'Emmit is drugged and choking, the crew is shaken, and the truth of who did this is landing on him.',
        ],
    ];
}

function filterExemplars(array $exemplars, array $moments): array
{
    $builder = new RuntimeNarratorTemplateBuilder(app(App\Services\Adaptation\SessionEventLoader::class));

    $method = new ReflectionMethod($builder, 'withoutSameSceneExemplars');

    $filtered = $method->invoke(
        $builder,
        ['voice_anchor' => $exemplars],
        $moments,
        new Story,
        new SessionAdaptation,
    );

    return array_map(
        fn (array $exemplar): string => (string) $exemplar['source'],
        (array) $filtered['voice_anchor'],
    );
}

it('withholds exemplars translated from beats the session narrates', function (): void {
    $kept = filterExemplars(wastelandExemplars(), wastelandMoments());

    expect($kept)->not->toContain('Pontis and Emmit opening the sealed cargo door in the wing compartment and discovering something alive inside')
        ->and($kept)->not->toContain('Emmit and the others scrambling for the escape pods as the garbage aircraft begins dumping its human cargo');
});

it('keeps exemplars that only share a character name with the session', function (): void {
    $kept = filterExemplars(wastelandExemplars(), wastelandMoments());

    // Every one of these names Emmit or Leo without covering a session-1 beat.
    expect($kept)->toContain('Emmit discovering the hidden aircraft in the forbidden chamber')
        ->and($kept)->toContain('Leo testing Emmit in the makeshift hangar')
        ->and($kept)->toContain("Emmit's hologram call with Lily, interrupted by Janet")
        ->and($kept)->toContain("Arrival at Leo's encampment and fortress reveal")
        ->and($kept)->toHaveCount(5);
});

it('flags rather than withholds when the anchor would fall below the floor', function (): void {
    // Only the two colliding exemplars plus two others: withholding would leave
    // two, so the pool must survive intact and carry the collision flag.
    $thinPool = [
        wastelandExemplars()[0],
        wastelandExemplars()[1],
        wastelandExemplars()[2],
        wastelandExemplars()[4],
    ];

    $builder = new RuntimeNarratorTemplateBuilder(app(App\Services\Adaptation\SessionEventLoader::class));
    $method  = new ReflectionMethod($builder, 'withoutSameSceneExemplars');

    $filtered = $method->invoke(
        $builder,
        ['voice_anchor' => $thinPool],
        wastelandMoments(),
        new Story,
        new SessionAdaptation,
    );

    $anchor = (array) $filtered['voice_anchor'];

    expect($anchor)->toHaveCount(4)
        ->and($anchor[0]['same_scene_collision'] ?? false)->toBeTrue()
        ->and($anchor[1]['same_scene_collision'] ?? false)->toBeTrue()
        ->and($anchor[2]['same_scene_collision'] ?? false)->toBeFalse()
        ->and($anchor[3]['same_scene_collision'] ?? false)->toBeFalse();
});

it('leaves the voice profile untouched when the story has no boiler moments', function (): void {
    expect(filterExemplars(wastelandExemplars(), []))->toHaveCount(7);
});
