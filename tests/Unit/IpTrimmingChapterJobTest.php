<?php

declare(strict_types=1);

use App\Jobs\Adaptation\IpTrimmingChapterJob;
use App\Jobs\Adaptation\VoiceLockChapterJob;
use App\Models\Chapter;

it('numbers paragraphs from one, split on blank lines', function () {
    expect(IpTrimmingChapterJob::paragraphs("First line.\n\nSecond line.\n\n\nThird."))
        ->toBe([1 => 'First line.', 2 => 'Second line.', 3 => 'Third.']);
});

it('rebuilds trimmed text verbatim with one marker per trimmed run', function () {
    $paragraphs = [
        1 => '"Emma," said Mr. Knightley, "you must not."',
        2 => 'The hedgerows were long and green and very long indeed.',
        3 => 'The lane went on past the farm.',
        4 => 'Emma laughed.',
    ];

    $text = IpTrimmingChapterJob::assembleTrimmedText($paragraphs, [
        ['from_paragraph' => 1, 'to_paragraph' => 1, 'classification' => 'PRESERVE', 'reference' => 'Ch.3 P1'],
        ['from_paragraph' => 2, 'to_paragraph' => 3, 'classification' => 'TRIM', 'reference' => 'Ch.3 P2-3'],
    ], [
        ['reference' => 'Ch.3 P2-3', 'conversion_type' => 'EXPLORABLE ENVIRONMENT'],
    ]);

    expect($text)->toBe(implode("\n\n", [
        '"Emma," said Mr. Knightley, "you must not."',
        '[TRIMMED: 17 words of explorable environment. See Conversion Notes, Ch.3 P2-3.]',
        'Emma laughed.',
    ]));
});

it('keeps a paragraph the model failed to classify', function () {
    $text = IpTrimmingChapterJob::assembleTrimmedText([1 => 'Kept.', 2 => 'Also kept.'], [], []);

    expect($text)->toBe("Kept.\n\nAlso kept.");
});

it('sizes the timeout by calls and stays under the queue retry window', function () {
    $short = (new Chapter)->forceFill(['content' => str_repeat('a', 1_000)]);
    $huge = (new Chapter)->forceFill(['content' => str_repeat('a', 2_000_000)]);

    expect(IpTrimmingChapterJob::resolveTimeout($short))->toBe(300)
        ->and(IpTrimmingChapterJob::resolveTimeout($huge))->toBe(1_100);
});

it('samples a spread of chapters for voice lock, first and last included', function () {
    $chapters = collect(range(1, 55))->map(fn (int $position): Chapter => (new Chapter)->forceFill(['id' => $position, 'position' => $position]));

    $sample = VoiceLockChapterJob::sample($chapters);

    expect($sample)->toHaveCount(VoiceLockChapterJob::MAX_SAMPLED_CHAPTERS)
        ->and($sample->first()->id)->toBe(1)
        ->and($sample->last()->id)->toBe(55)
        ->and(VoiceLockChapterJob::sample($chapters->take(5)))->toHaveCount(5);
});
