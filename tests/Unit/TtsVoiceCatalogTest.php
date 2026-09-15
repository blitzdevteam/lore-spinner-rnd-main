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
        ->and(collect($options)->pluck('label'))->toContain('Ellis', 'Imogen', 'Jofra', 'Autumn Veil', 'David (Deep)', 'Jonathan', 'Emma Taylor');
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
        ->filter(fn (array $title): bool => ($title['world_slug'] ?? null) === 'baker-street')
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

it('assigns launch-sheet narrator defaults by world and standalone title', function (string $slug, string $voiceId): void {
    expect(TtsVoiceCatalog::recommendedForSlug($slug))->toBe($voiceId)
        ->and(ChaosStoryConfig::ttsVoiceId($slug))->toBe($voiceId);
})->with([
    'world of oz uses Imogen' => ['the-wonderful-wizard-of-oz', 'jv41DhCf464zw0TI7I1w'],
    'marvelous land of oz uses Imogen' => ['the-marvelous-land-of-oz', 'jv41DhCf464zw0TI7I1w'],
    'pride and prejudice uses Imogen' => ['pride-and-prejudice', 'jv41DhCf464zw0TI7I1w'],
    'emma inherits austen Imogen' => ['emma', 'jv41DhCf464zw0TI7I1w'],
    'dracula uses Jonathan' => ['dracula', '4u5cJuSmHP9d6YRolsOu'],
    'frankenstein uses Jonathan' => ['frankenstein', '4u5cJuSmHP9d6YRolsOu'],
    'jekyll uses Jonathan' => ['dr-jekyll-and-mr-hyde', '4u5cJuSmHP9d6YRolsOu'],
    'tell-tale heart uses Jonathan' => ['the-tell-tale-heart', '4u5cJuSmHP9d6YRolsOu'],
    'three musketeers uses David Deep' => ['the-three-musketeers', 'BNgbHR0DNeZixGQVzloa'],
    'robin hood uses David Deep' => ['robin-hood', 'BNgbHR0DNeZixGQVzloa'],
    'treasure island uses David Deep' => ['treasure-island', 'BNgbHR0DNeZixGQVzloa'],
    'eighty days uses David Deep' => ['around-the-world-in-eighty-days', 'BNgbHR0DNeZixGQVzloa'],
    'center of the earth uses David Deep' => ['journey-to-the-center-of-the-earth', 'BNgbHR0DNeZixGQVzloa'],
    'peter and wendy uses Emma Taylor' => ['peter-pan', 'S9EGwlCtMF7VXtENq79v'],
    'invisible man uses Jonathan' => ['the-invisible-man', '4u5cJuSmHP9d6YRolsOu'],
    'masque of the red death uses Jonathan' => ['the-masque-of-the-red-death', '4u5cJuSmHP9d6YRolsOu'],
    'rue morgue uses Jonathan' => ['the-murders-in-the-rue-morgue', '4u5cJuSmHP9d6YRolsOu'],
    'twenty thousand leagues uses David Deep' => ['twenty-thousand-leagues-under-the-seas', 'BNgbHR0DNeZixGQVzloa'],
    'doctor moreau uses David Deep' => ['the-island-of-doctor-moreau', 'BNgbHR0DNeZixGQVzloa'],
    'unknown titles fall back to Jofra' => ['not-a-real-story', 'NuRyEq0OdD9mMOyd51UZ'],
]);

it('keeps camelot on Declan Sage until a launch voice is chosen', function (): void {
    expect(TtsVoiceCatalog::recommendedForSlug('sir-gawain-and-the-green-knight'))
        ->toBe('kqVT88a5QfII1HNAEPTJ');
});
