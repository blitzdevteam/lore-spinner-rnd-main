<?php

declare(strict_types=1);

use App\ChaosMode\ChaosStoryConfig;
use App\Support\LaunchCatalog;
use App\Tts\TtsVoiceCatalog;

it('exposes the approved narration catalog grouped by gender', function (): void {
    $options = TtsVoiceCatalog::options();

    expect($options)->toHaveCount(15)
        ->and(collect($options)->pluck('id')->unique()->count())->toBe(15)
        ->and(collect($options)->where('gender', 'male')->count())->toBe(9)
        ->and(collect($options)->where('gender', 'female')->count())->toBe(6)
        ->and(collect($options)->pluck('label'))->toContain('Ellis', 'Imogen', 'Jofra', 'Autumn Veil');
});

it('keeps story and world defaults on the allow-list', function (): void {
    $allowed = TtsVoiceCatalog::allowedIds();

    foreach ([...array_values(config('tts-voices.story_defaults')), ...array_values(config('tts-voices.world_defaults'))] as $voiceId) {
        expect($allowed)->toContain($voiceId);
    }

    expect(TtsVoiceCatalog::fallbackId())->toBe('NuRyEq0OdD9mMOyd51UZ')
        ->and(TtsVoiceCatalog::isAllowed('QxpsWUTZAxznFqyH1goJ'))->toBeTrue()
        ->and(TtsVoiceCatalog::isAllowed('not-a-voice'))->toBeFalse();
});

it('uses Ellis for every Baker Street Sherlock story', function (): void {
    $bakerStreet = collect(LaunchCatalog::titles())
        ->filter(fn (array $title): bool => $title['world_slug'] === 'baker-street')
        ->keys();

    expect($bakerStreet->all())->toContain(
        'the-adventure-of-the-speckled-band',
        'a-scandal-in-bohemia',
        'the-adventure-of-the-blue-carbuncle',
    );

    foreach ($bakerStreet as $slug) {
        expect(TtsVoiceCatalog::recommendedForSlug($slug))->toBe('QxpsWUTZAxznFqyH1goJ')
            ->and(ChaosStoryConfig::ttsVoiceId($slug))->toBe('QxpsWUTZAxznFqyH1goJ');
    }
});

it('assigns story-specific narrator defaults from the catalog', function (string $slug, string $voiceId): void {
    expect(TtsVoiceCatalog::recommendedForSlug($slug))->toBe($voiceId);
})->with([
    'pride and prejudice uses Imogen' => ['pride-and-prejudice', 'jv41DhCf464zw0TI7I1w'],
    'emma uses Emma Taylor' => ['emma', 'S9EGwlCtMF7VXtENq79v'],
    'anima machina uses Amelia' => ['anima-machina', 'pGAwIQNN9UjOkKxjAyGQ'],
    'the matrix uses Declan Sage' => ['the-matrix', 'kqVT88a5QfII1HNAEPTJ'],
    'unknown titles fall back to Jofra' => ['not-a-real-story', 'NuRyEq0OdD9mMOyd51UZ'],
]);
