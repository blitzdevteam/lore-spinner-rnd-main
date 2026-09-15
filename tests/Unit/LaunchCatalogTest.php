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
        ->toBe('jane-austen');

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
    expect(StoryPlayability::isLaunchSlug('momotaro-the-peach-boy'))->toBeFalse()
        ->and(StoryPlayability::isLaunchSlug('the-unwritten-ones'))->toBeFalse()
        ->and(StoryPlayability::isLaunchSlug('dr-jekyll-and-mr-hyde'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('the-strange-case-of-dr-jekyll-and-mr-hyde'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('treasure-island'))->toBeTrue()
        ->and(StoryPlayability::isLaunchSlug('wasteland'))->toBeFalse();
});

it('resolves playable slug aliases', function (): void {
    expect(StoryPlayability::canonicalSlug('the-strange-case-of-dr-jekyll-and-mr-hyde'))
        ->toBe('dr-jekyll-and-mr-hyde');
});

it('binds each launch playable slug to a playable protagonist', function (): void {
    $expected = [
        'the-wonderful-wizard-of-oz' => 'Dorothy Gale',
        'peter-pan' => 'Wendy Darling',
        'the-adventure-of-the-speckled-band' => 'Dr. John Watson',
        'a-scandal-in-bohemia' => 'Dr. John Watson',
        'the-adventure-of-the-blue-carbuncle' => 'Dr. John Watson',
        'dracula' => 'Mina Murray',
        'frankenstein' => 'The Creature',
        'dr-jekyll-and-mr-hyde' => 'Dr. Henry Jekyll',
        'the-tell-tale-heart' => 'The Narrator',
        'pride-and-prejudice' => 'Elizabeth Bennet',
        'emma' => 'Emma Woodhouse',
        'treasure-island' => 'Jim Hawkins',
        'around-the-world-in-eighty-days' => 'Jean Passepartout',
        'the-three-musketeers' => "D'Artagnan",
        'robin-hood' => 'Robin Hood',
        'journey-to-the-center-of-the-earth' => 'Axel',
        'the-invisible-man' => 'Griffin',
        'sir-gawain-and-the-green-knight' => 'Sir Gawain',
    ];

    foreach ($expected as $slug => $protagonist) {
        expect(LaunchCatalog::protagonist($slug))->toBe($protagonist);
    }
});

it('lists every launch world from the catalog', function (): void {
    expect(LaunchCatalog::worlds())->toHaveCount(7)
        ->and(collect(LaunchCatalog::worlds())->pluck('title')->all())->toContain(
            'World of Oz',
            'Baker Street',
            'Gothic Horrors',
            'Camelot',
        );
});

it('keeps the frontend playable allowlist in sync with config', function (): void {
    $source = file_get_contents(resource_path('js/data/playableStorySlugs.ts'));

    expect($source)->toContain('export const PLAYABLE_ALLOWLIST_SLUGS')
        ->and($source)->toContain('export function canonicalPlayableSlug')
        ->and($source)->toContain('export function compareStoriesByPlayableOrder')
        ->and($source)->toContain('export function compareWorldsByPlayableOrder');

    $slugs = [
        ...config('stories.launch_playable_slugs', []),
        ...config('stories.boiled_playable_slugs', []),
        ...array_keys(config('stories.playable_slug_aliases', [])),
        ...array_values(config('stories.playable_slug_aliases', [])),
    ];

    foreach (array_unique($slugs) as $slug) {
        expect($source)->toContain("'{$slug}'");
    }
});
