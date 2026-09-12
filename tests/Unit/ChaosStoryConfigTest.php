<?php

declare(strict_types=1);

use App\ChaosMode\ChaosStoryConfig;

it('binds Dr Watson as the playable protagonist on every Baker Street title', function (): void {
    foreach ([
        'the-adventure-of-the-speckled-band',
        'the-adventure-of-the-blue-carbuncle',
        'a-scandal-in-bohemia',
    ] as $slug) {
        expect(ChaosStoryConfig::find($slug)['protagonist'])->toBe('Dr. John Watson')
            ->and(ChaosStoryConfig::protagonistFor($slug, 'Holmes'))->toBe('Dr. John Watson');
    }
});

it('binds Dr Jekyll as the playable protagonist including legacy slug aliases', function (): void {
    foreach ([
        'dr-jekyll-and-mr-hyde',
        'the-strange-case-of-dr-jekyll-and-mr-hyde',
        'jekyll-and-hyde',
    ] as $slug) {
        expect(ChaosStoryConfig::find($slug)['protagonist'])->toBe('Dr. Henry Jekyll')
            ->and(ChaosStoryConfig::find($slug)['prefer_literal_opening'] ?? false)->toBeFalse()
            ->and(ChaosStoryConfig::protagonistFor($slug, 'Utterson'))->toBe('Dr. Henry Jekyll');
    }
});

it('resolves launch-only titles from the launch catalog', function (): void {
    expect(ChaosStoryConfig::find('dracula')['protagonist'])->toBe('Mina Murray')
        ->and(ChaosStoryConfig::protagonistFor('peter-pan'))->toBe('Wendy Darling');
});

it('falls back when a slug has no chaos config', function (): void {
    expect(ChaosStoryConfig::protagonistFor('unknown-title', 'Alice'))->toBe('Alice')
        ->and(ChaosStoryConfig::protagonistFor('unknown-title'))->toBe('the protagonist');
});
