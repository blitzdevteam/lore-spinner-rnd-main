<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use RuntimeException;

final class NarrationResultNormalizer
{
    /**
     * @param  array<string, mixed>  $result
     * @return array<int, string|array{text:string,canonical_choice_id:?string,canonical_path_label:?string}>
     */
    public function choices(array $result, bool $boilerActive): array
    {
        if ($boilerActive) {
            if (! array_key_exists('visible_suggestions', $result) || ! is_array($result['visible_suggestions'])) {
                throw new RuntimeException('Boiler narration requires visible_suggestions, including an empty array when none are shown.');
            }

            if (count($result['visible_suggestions']) > 3) {
                throw new RuntimeException('Boiler narration may return at most three visible suggestions.');
            }

            return array_map(function ($suggestion): array {
                if (! is_array($suggestion)) {
                    throw new RuntimeException('Every visible suggestion must be an object.');
                }

                $text = trim((string) ($suggestion['text'] ?? ''));
                $choiceId = trim((string) ($suggestion['canonical_choice_id'] ?? ''));
                $pathLabel = strtoupper(trim((string) ($suggestion['canonical_path_label'] ?? '')));

                if ($text === '' || mb_strlen($text) > 160) {
                    throw new RuntimeException('Visible suggestion text is empty or too long.');
                }

                if (($choiceId === '') !== ($pathLabel === '')) {
                    throw new RuntimeException('Visible suggestion canonical choice ID and path label must both be present or both be absent.');
                }

                if ($pathLabel !== '' && ! in_array($pathLabel, ['A', 'B', 'C'], true)) {
                    throw new RuntimeException('Visible suggestion canonical path label must be A, B, or C.');
                }

                return [
                    'text' => $text,
                    'canonical_choice_id' => $choiceId !== '' ? $choiceId : null,
                    'canonical_path_label' => $pathLabel !== '' ? $pathLabel : null,
                ];
            }, $result['visible_suggestions']);
        }

        $choices = $result['choices'] ?? [];
        if (! is_array($choices) || count($choices) > 3) {
            throw new RuntimeException('Legacy narration choices must be an array containing at most three strings.');
        }

        return array_map(static function ($choice): string {
            if (! is_string($choice) || trim($choice) === '') {
                throw new RuntimeException('Legacy narration choice must be a non-empty string.');
            }

            return trim($choice);
        }, $choices);
    }
}
