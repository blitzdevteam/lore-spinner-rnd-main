<?php

declare(strict_types=1);

use App\ChaosMode\ChaosStoryConfig;

it('binds Dr Watson as the playable protagonist on every Baker Street title', function (): void {
    foreach ([
        'the-adventure-of-the-speckled-band',
        'the-adventure-of-the-blue-carbuncle',
        'a-scandal-in-bohemia',
    ] as $slug) {
        expect(ChaosStoryConfig::find($slug)['protagonist'])->toBe('Watson')
            ->and(ChaosStoryConfig::protagonistFor($slug, 'Holmes'))->toBe('Watson');
    }
});

it('falls back when a slug has no chaos config', function (): void {
    expect(ChaosStoryConfig::protagonistFor('unknown-title', 'Alice'))->toBe('Alice')
        ->and(ChaosStoryConfig::protagonistFor('unknown-title'))->toBe('the protagonist');
});
