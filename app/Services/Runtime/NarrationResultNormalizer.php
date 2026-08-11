<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use Illuminate\Support\Facades\Log;
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
            $suggestions = $result['visible_suggestions'] ?? [];
            if (! is_array($suggestions)) {
                Log::channel('narration')->warning('boiler.visible_suggestions_normalized_empty', [
                    'reason' => 'missing_or_non_array',
                ]);

                return [];
            }

            if (count($suggestions) > 3) {
                Log::channel('narration')->warning('boiler.visible_suggestions_normalized_truncated', [
                    'original_count' => count($suggestions),
                ]);
                $suggestions = array_slice($suggestions, 0, 3);
            }

            $normalized = [];
            foreach ($suggestions as $suggestion) {
                if (! is_array($suggestion)) {
                    Log::channel('narration')->warning('boiler.visible_suggestion_dropped', [
                        'reason' => 'not_object',
                    ]);

                    continue;
                }

                $text = mb_trim((string) ($suggestion['text'] ?? ''));
                $choiceId = mb_trim((string) ($suggestion['canonical_choice_id'] ?? ''));
                $pathLabel = mb_strtoupper(mb_trim((string) ($suggestion['canonical_path_label'] ?? '')));

                if ($text === '' || mb_strlen($text) > 160) {
                    Log::channel('narration')->warning('boiler.visible_suggestion_dropped', [
                        'reason' => 'empty_or_too_long',
                    ]);

                    continue;
                }

                if (($choiceId === '') !== ($pathLabel === '')) {
                    Log::channel('narration')->warning('boiler.visible_suggestion_demoted', [
                        'reason' => 'half_mapped_identity',
                        'canonical_choice_id' => $choiceId,
                        'canonical_path_label' => $pathLabel,
                    ]);
                    $choiceId = '';
                    $pathLabel = '';
                }

                if ($pathLabel !== '' && ! in_array($pathLabel, ['A', 'B', 'C'], true)) {
                    Log::channel('narration')->warning('boiler.visible_suggestion_demoted', [
                        'reason' => 'invalid_path_label',
                        'canonical_choice_id' => $choiceId,
                        'canonical_path_label' => $pathLabel,
                    ]);
                    $choiceId = '';
                    $pathLabel = '';
                }

                $normalized[] = [
                    'text' => $text,
                    'canonical_choice_id' => $choiceId !== '' ? $choiceId : null,
                    'canonical_path_label' => $pathLabel !== '' ? $pathLabel : null,
                ];
            }

            return $normalized;
        }

        $choices = $result['choices'] ?? [];
        if (($result['session_complete'] ?? false) === true && $choices === []) {
            return [];
        }

        if (! is_array($choices) || count($choices) !== 3) {
            throw new RuntimeException('Incomplete legacy narration must contain exactly three choice strings.');
        }

        return array_values(array_map(static function ($choice): string {
            if (! is_string($choice) || mb_trim($choice) === '') {
                throw new RuntimeException('Legacy narration choice must be a non-empty string.');
            }

            return mb_trim($choice);
        }, $choices));
    }
}
