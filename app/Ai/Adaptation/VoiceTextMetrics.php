<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Exact prose statistics measured over a complete novel, so the voice profile
 * states the author's real ratios instead of a model's estimate of them.
 *
 * Dialogue is the text inside quotation marks; narration is everything else.
 * A quotation runs across hard-wrapped lines and ends at a paragraph break.
 */
final class VoiceTextMetrics
{
    private const array SENTENCE_BUCKETS = [
        '1_3w' => [1, 3], '4_5w' => [4, 5], '6_8w' => [6, 8], '9_12w' => [9, 12],
        '13_18w' => [13, 18], '19_25w' => [19, 25], '26_plus_w' => [26, PHP_INT_MAX],
    ];

    private const array SPEECH_VERBS = [
        'said', 'says', 'asked', 'cried', 'replied', 'answered', 'exclaimed', 'whispered', 'shouted',
        'returned', 'continued', 'added', 'observed', 'rejoined', 'murmured', 'called', 'began', 'resumed',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function measure(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        [$narration, $dialogue] = self::splitDialogue($text);

        $narrationWords = self::wordCount($narration);
        $dialogueWords = self::wordCount($dialogue);
        $totalWords = max(1, $narrationWords + $dialogueWords);
        $sentenceLengths = array_map(self::wordCount(...), self::sentences($narration));
        $sentenceCount = max(1, count($sentenceLengths));
        $paragraphs = array_values(array_filter(preg_split('/\n\s*\n/', $text) ?: [], static fn (string $p): bool => mb_trim($p) !== ''));

        $buckets = [];
        foreach (self::SENTENCE_BUCKETS as $name => [$min, $max]) {
            $count = count(array_filter($sentenceLengths, static fn (int $length): bool => $length >= $min && $length <= $max));
            $buckets[$name] = round($count / $sentenceCount * 100, 1);
        }

        $speechVerbs = array_count_values(array_map('mb_strtolower', self::matches('/\b('.implode('|', self::SPEECH_VERBS).')\b/iu', $narration)));
        $speechVerbTotal = max(1, array_sum($speechVerbs));
        arsort($speechVerbs);

        return [
            'words_total' => $narrationWords + $dialogueWords,
            'dialogue_share_pct' => round($dialogueWords / $totalWords * 100, 1),
            'narration' => [
                'sentences' => count($sentenceLengths),
                'average_sentence_words' => round(array_sum($sentenceLengths) / $sentenceCount, 1),
                'sentence_length_pct' => $buckets,
                'ing_opening_pct' => round(count(array_filter(self::sentences($narration), static fn (string $s): bool => preg_match('/^\W*\p{L}+ing\b/u', $s) === 1)) / $sentenceCount * 100, 1),
                'per_1000_words' => self::punctuation($narration, $narrationWords),
            ],
            'dialogue' => [
                'per_1000_words' => self::punctuation($dialogue, $dialogueWords),
            ],
            'average_paragraph_words' => round($totalWords / max(1, count($paragraphs)), 1),
            'speech_verbs' => [
                'said_share_pct' => round((($speechVerbs['said'] ?? 0) + ($speechVerbs['says'] ?? 0)) / $speechVerbTotal * 100, 1),
                'most_used' => array_slice($speechVerbs, 0, 10, true),
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitDialogue(string $text): array
    {
        $narration = [];
        $dialogue = [];

        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $paragraph) {
            $inside = false;

            foreach (preg_split('/(“|”|")/u', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $piece) {
                if ($piece === '“' || $piece === '”' || $piece === '"') {
                    $inside = $piece === '“' || ($piece === '"' && ! $inside);

                    continue;
                }

                $inside ? $dialogue[] = $piece : $narration[] = $piece;
            }

            $narration[] = "\n\n";
        }

        return [implode(' ', $narration), implode(' ', $dialogue)];
    }

    /**
     * @return list<string>
     */
    private static function sentences(string $text): array
    {
        $parts = preg_split('/(?<!\b(?:Mr|Mrs|Ms|Dr|St|Jr|Sr|Rev|Col|Capt|Gen|Hon|Mme|Messrs)\.)(?<=[.!?])["”’)\]]*\s+/u', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => self::wordCount($s) > 0));
    }

    /**
     * @return array<string, float>
     */
    private static function punctuation(string $text, int $words): array
    {
        $per1000 = static fn (int $count): float => round($count / max(1, $words) * 1000, 2);

        return [
            'semicolons' => $per1000(mb_substr_count($text, ';')),
            'colons' => $per1000(mb_substr_count($text, ':')),
            'em_dashes' => $per1000(preg_match_all('/\x{2014}|\x{2013}|--/u', $text)),
            'exclamation_marks' => $per1000(mb_substr_count($text, '!')),
            'question_marks' => $per1000(mb_substr_count($text, '?')),
            'ellipses' => $per1000(preg_match_all('/\.{3}|\x{2026}/u', $text)),
            'commas' => $per1000(mb_substr_count($text, ',')),
            'periods' => $per1000(mb_substr_count($text, '.')),
        ];
    }

    private static function wordCount(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text);
    }

    /**
     * @return list<string>
     */
    private static function matches(string $pattern, string $text): array
    {
        preg_match_all($pattern, $text, $found);

        return $found[1] ?? [];
    }
}
