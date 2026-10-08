<?php

declare(strict_types=1);

use App\Ai\Adaptation\VoiceTextMetrics;
use App\Jobs\Adaptation\VoiceLockBookJob;

it('measures narration and dialogue separately with exact counts', function () {
    $metrics = VoiceTextMetrics::measure(implode("\n\n", [
        'Emma Woodhouse, handsome, clever, and rich, seemed to unite some of the best blessings of existence.',
        '"Poor Miss Taylor!" said Mr. Woodhouse. "I wish she were here again."',
        'She sighed.',
    ]));

    expect($metrics['narration']['sentences'])->toBe(3)
        ->and($metrics['narration']['per_1000_words']['semicolons'])->toBe(0.0)
        ->and($metrics['dialogue']['per_1000_words']['exclamation_marks'])->toBeGreaterThan(0)
        ->and($metrics['narration']['per_1000_words']['exclamation_marks'])->toBe(0.0)
        ->and($metrics['speech_verbs']['said_share_pct'])->toBe(100.0)
        ->and($metrics['dialogue_share_pct'])->toBeGreaterThan(0)
        ->and(array_sum($metrics['narration']['sentence_length_pct']))->toEqualWithDelta(100, 0.5);
});

it('keeps a hard-wrapped speech inside its quotation until the paragraph ends', function () {
    $metrics = VoiceTextMetrics::measure("“Only one more, papa; only for Mr.\nElton. Poor Mr. Elton!” said Emma.\n\nShe smiled.");

    expect($metrics['narration']['per_1000_words']['exclamation_marks'])->toBe(0.0)
        ->and($metrics['narration']['per_1000_words']['semicolons'])->toBe(0.0)
        ->and($metrics['dialogue']['per_1000_words']['semicolons'])->toBeGreaterThan(0);
});

it('accepts a voice profile with every runtime-critical part', function () {
    VoiceLockBookJob::assertComplete([
        'profile_type' => 'NOVELIST',
        'voice_anchor' => array_fill(0, 6, ['prose' => 'You walk.']),
        'anchor_card' => array_fill(0, 8, 'Delete every em dash.'),
        'runtime_self_check' => array_fill(0, 8, 'Search punctuation.'),
        'author_voice_dna_profile' => [
            'signature_writing_techniques' => array_fill(0, 8, ['technique' => 'Irony']),
            'dialogue_fingerprint_per_character' => [['character' => 'Emma']],
        ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects a voice profile missing its exemplars', function () {
    VoiceLockBookJob::assertComplete([
        'profile_type' => 'NOVELIST',
        'voice_anchor' => [],
        'anchor_card' => array_fill(0, 8, 'x'),
        'runtime_self_check' => array_fill(0, 8, 'x'),
        'author_voice_dna_profile' => ['signature_writing_techniques' => array_fill(0, 8, []), 'dialogue_fingerprint_per_character' => [[]]],
    ]);
})->throws(RuntimeException::class, 'fewer than 6 voice_anchor exemplars');
