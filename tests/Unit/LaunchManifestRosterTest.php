<?php

declare(strict_types=1);

use App\Support\LaunchCatalog;

it('registers every launch playable slug in the manifest with matching marketing titles', function (): void {
    $manifest = require database_path('stories/launch/manifest.php');
    $bySlug = [];

    foreach ($manifest['titles'] as $entry) {
        $bySlug[$entry['slug']] = $entry;
    }

    foreach (config('stories.launch_playable_slugs', []) as $slug) {
        expect($bySlug)->toHaveKey($slug)
            ->and($bySlug[$slug]['title'])->toBe(LaunchCatalog::displayTitle($slug));
    }
});

it('keeps non-launch pipeline titles out of the launch playable allowlist', function (): void {
    $notOnLaunchRoster = [
        'dream-police',
        'anima-machina',
        'wasteland',
        'driftheart',
        'the-unwritten-ones',
        'the-marvelous-land-of-oz',
        'theseus-and-the-minotaur',
        'orpheus-and-eurydice',
        'havoc-in-heaven',
        'the-white-bone-demon',
        'momotaro-the-peach-boy',
    ];

    $launchSlugs = config('stories.launch_playable_slugs', []);

    foreach ($notOnLaunchRoster as $slug) {
        expect($launchSlugs)->not->toContain($slug);
    }
});

it('keeps launch story cover assets on disk for titles that declare them', function (): void {
    $manifest = require database_path('stories/launch/manifest.php');

    foreach ($manifest['titles'] as $entry) {
        if (! isset($entry['story_cover'])) {
            continue;
        }

        $relative = (string) $entry['story_cover'];

        if (str_starts_with($relative, 'database:')) {
            $path = database_path('stories/'.mb_substr($relative, mb_strlen('database:')));

            expect($path)->toBeFile();
        }
    }
});
