<?php

declare(strict_types=1);

use App\Support\LaunchCatalog;
use App\Support\StoryPlayability;

it('resolves launch marketing titles and worlds', function (): void {
    expect(LaunchCatalog::displayTitle('the-adventure-of-the-speckled-band'))
        ->toBe('Sherlock Holmes: The Speckled Band');

    expect(LaunchCatalog::worldName('pride-and-prejudice'))
        ->toBe('Jane Austen');

    expect(LaunchCatalog::worldSlugForStory('emma'))
        ->toBe('regency-england');

    expect(LaunchCatalog::worldTitle('baker-street'))
        ->toBe('Baker Street');
});

it('lists every launch playable slug in the catalog', function (): void {
    $playable = config('stories.launch_playable_slugs', []);
    $catalog = array_keys(LaunchCatalog::titles());

    foreach ($playable as $slug) {
        expect($catalog)->toContain($slug);
    }
});

it('keeps already-boiled rnd titles playable', function (): void {
    expect(StoryPlayability::isLaunchSlug('momotaro-the-peach-boy'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('the-unwritten-ones'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('dr-jekyll-and-mr-hyde'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('the-strange-case-of-dr-jekyll-and-mr-hyde'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('treasure-island'))->toBeFalse()
        ->and(StoryPlayability::isLaunchSlug('wasteland'))->toBeFalse();
});

it('resolves playable slug aliases', function (): void {
    expect(StoryPlayability::canonicalSlug('the-strange-case-of-dr-jekyll-and-mr-hyde'))
        ->toBe('dr-jekyll-and-mr-hyde');
});

it('lists every launch world from the catalog', function (): void {
    expect(LaunchCatalog::worlds())->toHaveCount(14)
        ->and(collect(LaunchCatalog::worlds())->pluck('title')->all())->toContain('Oz', 'Baker Street', 'Arthurian Legends');
});
