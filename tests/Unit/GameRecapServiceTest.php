<?php

declare(strict_types=1);

use App\Services\GameRecapService;
use ReflectionMethod;

it('keeps the recap voice contract free of universal ai tells', function (): void {
    $method = new ReflectionMethod(GameRecapService::class, 'systemPrompt');
    $method->setAccessible(true);
    $prompt = $method->invoke(new GameRecapService);

    expect($prompt)
        ->toContain('No em dashes or double hyphens')
        ->toContain('No ellipses')
        ->toContain('No “It’s not X, it’s Y.”')
        ->toContain('Do not use tapestry, delve, underscore')
        ->toContain('Do not use “Something shifted”')
        ->toContain('Do not use interior/cognitive narration')
        ->toContain('Do not use headings, bullets, labels, markdown');
});
