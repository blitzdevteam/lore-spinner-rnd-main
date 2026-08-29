<?php

declare(strict_types=1);

namespace App\Services\Runtime;

/**
 * Calculates the playable duration of an MP3 without relying on a host-level
 * ffprobe binary. TTS renders are always MP3s, and frame parsing keeps the
 * metering portable between local development and production workers.
 */
final class Mp3DurationCalculator
{
    public function milliseconds(string $audio): int
    {
        $length = mb_strlen($audio, '8bit');
        $offset = $this->audioOffset($audio);
        $durationSeconds = 0.0;
        $frames = 0;

        while ($offset <= $length - 4) {
            $frame = $this->frameAt($audio, $offset);

            if ($frame === null) {
                if ($frames > 0) {
                    break;
                }

                $offset++;

                continue;
            }

            if ($offset + $frame['length'] > $length) {
                break;
            }

            $durationSeconds += $frame['samples'] / $frame['sample_rate'];
            $frames++;
            $offset += $frame['length'];
        }

        return $frames === 0 ? 0 : (int) round($durationSeconds * 1_000);
    }

    private function audioOffset(string $audio): int
    {
        if (mb_strlen($audio, '8bit') < 10 || mb_substr($audio, 0, 3, '8bit') !== 'ID3') {
            return 0;
        }

        $flags = ord($audio[5]);
        $tagSize = (ord($audio[6]) << 21)
            | (ord($audio[7]) << 14)
            | (ord($audio[8]) << 7)
            | ord($audio[9]);

        return min(
            mb_strlen($audio, '8bit'),
            10 + $tagSize + (($flags & 0x10) !== 0 ? 10 : 0),
        );
    }

    /**
     * @return array{length:int, samples:int, sample_rate:int}|null
     */
    private function frameAt(string $audio, int $offset): ?array
    {
        $bytes = mb_substr($audio, $offset, 4, '8bit');
        if (mb_strlen($bytes, '8bit') !== 4) {
            return null;
        }

        $header = (ord($bytes[0]) << 24)
            | (ord($bytes[1]) << 16)
            | (ord($bytes[2]) << 8)
            | ord($bytes[3]);

        if (($header & 0xFFE00000) !== 0xFFE00000) {
            return null;
        }

        $versionBits = ($header >> 19) & 0b11;
        $layerBits = ($header >> 17) & 0b11;
        $bitrateIndex = ($header >> 12) & 0b1111;
        $sampleRateIndex = ($header >> 10) & 0b11;
        $padding = ($header >> 9) & 0b1;

        $version = match ($versionBits) {
            0b11 => 1,
            0b10 => 2,
            0b00 => 25,
            default => null,
        };
        $layer = match ($layerBits) {
            0b11 => 1,
            0b10 => 2,
            0b01 => 3,
            default => null,
        };

        if ($version === null || $layer === null || $bitrateIndex === 0 || $bitrateIndex === 0b1111) {
            return null;
        }

        $sampleRate = $this->sampleRate($version, $sampleRateIndex);
        $bitrate = $this->bitrate($version, $layer, $bitrateIndex);

        if ($sampleRate === 0 || $bitrate === 0) {
            return null;
        }

        $samples = match ($layer) {
            1 => 384,
            2 => 1_152,
            3 => $version === 1 ? 1_152 : 576,
        };
        $frameLength = match (true) {
            $layer === 1 => ((int) floor((12 * $bitrate * 1_000) / $sampleRate) + $padding) * 4,
            $layer === 3 && $version !== 1 => (int) floor((72 * $bitrate * 1_000) / $sampleRate) + $padding,
            default => (int) floor((144 * $bitrate * 1_000) / $sampleRate) + $padding,
        };

        if ($frameLength < 4) {
            return null;
        }

        return [
            'length' => $frameLength,
            'samples' => $samples,
            'sample_rate' => $sampleRate,
        ];
    }

    private function sampleRate(int $version, int $index): int
    {
        if ($index < 0 || $index > 2) {
            return 0;
        }

        $rates = match ($version) {
            1 => [44_100, 48_000, 32_000][$index],
            2 => [22_050, 24_000, 16_000][$index],
            25 => [11_025, 12_000, 8_000][$index],
            default => 0,
        };

        return $rates;
    }

    private function bitrate(int $version, int $layer, int $index): int
    {
        $rates = match (true) {
            $version === 1 && $layer === 1 => [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
            $version === 1 && $layer === 2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
            $version === 1 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
            $layer === 1 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
            default => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
        };

        return $rates[$index] ?? 0;
    }
}
