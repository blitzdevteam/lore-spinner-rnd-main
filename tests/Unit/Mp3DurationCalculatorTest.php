<?php

declare(strict_types=1);

use App\Services\Runtime\Mp3DurationCalculator;

function mp3DurationTestFrame(int $versionBits, int $bitrateIndex, int $sampleRateIndex, int $length): string
{
    $header = 0xFFE00000
        | ($versionBits << 19)
        | (0b01 << 17)
        | (0b1 << 16)
        | ($bitrateIndex << 12)
        | ($sampleRateIndex << 10);

    return pack('N', $header).str_repeat("\0", $length - 4);
}

it('calculates MP3 duration from audio frames after an ID3 tag', function () {
    // MPEG-1 Layer III, 128 kbps, 44.1 kHz: 1,152 samples per frame.
    $frame = mp3DurationTestFrame(0b11, 9, 0, 417);
    $id3 = 'ID3'.chr(4).chr(0).chr(0).chr(0).chr(0).chr(0).chr(4).'test';

    expect(app(Mp3DurationCalculator::class)->milliseconds($id3.$frame.$frame))
        ->toBe(52);
});

it('returns zero when no valid MP3 frame exists', function () {
    expect(app(Mp3DurationCalculator::class)->milliseconds('not an mp3'))->toBe(0);
});
