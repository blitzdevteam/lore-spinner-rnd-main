<?php

declare(strict_types=1);

use App\Support\LaunchCatalog;
use App\Support\StoryPlayability;

it('resolves launch marketing titles and worlds', function (): void {
    expect(LaunchCatalog::displayTitle('the-adventure-of-the-speckled-band'))
        ->toBe('Sherlock Holmes: The Speckled Band');

    expect(LaunchCatalog::worldName('pride-and-prejudice'))
        ->toBe('Regency England');

    expect(LaunchCatalog::worldSlugForStory('emma'))
        ->toBe('regency-england');

    expect(LaunchCatalog::worldTitle('baker-street'))
        ->toBe('Baker Street');
});

it('lists every launch catalog slug in the playable allowlist', function (): void {
    $playable = StoryPlayability::launchSlugs();
    $catalog = array_keys(LaunchCatalog::titles());

    foreach ($catalog as $slug) {
        expect($playable)->toContain($slug);
    }
});

it('keeps already-boiled rnd titles playable', function (): void {
    expect(StoryPlayability::isLaunchSlug('momotaro-the-peach-boy'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('the-unwritten-ones'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('wasteland'))->toBeFalse()
        ->and(StoryPlayability::isLaunchSlug('anima-machina'))->toBeFalse();
});

it('lists every launch world from the catalog', function (): void {
    expect(LaunchCatalog::worlds())->toHaveCount(14)
        ->and(collect(LaunchCatalog::worlds())->pluck('title')->all())->toContain('Land of Oz', 'Baker Street', 'Arthurian Britain');
});
