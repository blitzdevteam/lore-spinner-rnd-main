<?php

declare(strict_types=1);

use App\Ai\Adaptation\VoiceAnchorSanitizer;

it('lifts a leaked session_relevance tail out of the prose', function (string $prose, string $cleanProse, string $relevance): void {
    $exemplar = (new VoiceAnchorSanitizer)->sanitizeExemplar(['mode' => 'quiet', 'prose' => $prose]);

    expect($exemplar['prose'])->toBe($cleanProse)
        ->and($exemplar['session_relevance'])->toBe($relevance);
})->with([
    'plain tail' => [
        "You stand at the window. The night is wide.','session_relevance':'session_1_primary",
        'You stand at the window. The night is wide.',
        'session_1_primary',
    ],
    'tail after closing quote' => [
        'You climb into the bed."\',\'session_relevance\':\'later_session',
        'You climb into the bed."',
        'later_session',
    ],
    'double-quoted json tail' => [
        'You listen.", "session_relevance": "all_sessions"',
        'You listen.',
        'all_sessions',
    ],
]);

it('leaves clean exemplars untouched', function (): void {
    $exemplar = ['mode' => 'quiet', 'prose' => 'You listen to the sea.', 'session_relevance' => 'all_sessions'];

    expect((new VoiceAnchorSanitizer)->sanitizeExemplar($exemplar))->toBe($exemplar);
});

it('keeps an existing session_relevance key over the leaked one', function (): void {
    $exemplar = (new VoiceAnchorSanitizer)->sanitizeExemplar([
        'prose' => "You wait.','session_relevance':'later_session",
        'session_relevance' => 'all_sessions',
    ]);

    expect($exemplar)->toBe(['prose' => 'You wait.', 'session_relevance' => 'all_sessions']);
});

it('repairs a whole profile idempotently and counts corruption', function (): void {
    $sanitizer = new VoiceAnchorSanitizer;
    $profile = [
        'anchor_card' => ['keep me'],
        'voice_anchor' => [
            ['prose' => "You run.','session_relevance':'session_1_primary"],
            ['prose' => 'You rest.', 'session_relevance' => 'all_sessions'],
        ],
    ];

    $repaired = $sanitizer->sanitizeProfile($profile);

    expect($sanitizer->corruptedExemplarCount($profile))->toBe(1)
        ->and($sanitizer->corruptedExemplarCount($repaired))->toBe(0)
        ->and($repaired['anchor_card'])->toBe(['keep me'])
        ->and($repaired['voice_anchor'][0])->toBe(['prose' => 'You run.', 'session_relevance' => 'session_1_primary'])
        ->and($sanitizer->sanitizeProfile($repaired))->toBe($repaired);
});

it('tolerates a profile without voice anchors', function (): void {
    expect((new VoiceAnchorSanitizer)->sanitizeProfile(['anchor_card' => []]))->toBe(['anchor_card' => []]);
});
