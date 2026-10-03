<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Structural checks for a repaired cold open.
 *
 * The prose must be playable on its own: named protagonist, second person,
 * within the word budget, and no question aimed at the player. The choice
 * buttons carry the decision.
 */
final class ColdOpenRepairValidator
{
    /**
     * @return list<string>
     */
    public function failures(string $prose, string $protagonist): array
    {
        $prose = mb_trim($prose);
        $failures = [];

        if ($prose === '') {
            return ['The cold open is empty.'];
        }

        if (str_contains($prose, '===')) {
            $failures[] = 'The cold open contains an editorial marker.';
        }

        $words = $this->words($prose);
        $count = count($words);

        if ($count > 350) {
            $failures[] = "The cold open is {$count} words. The limit is 350.";
        }

        $opening = implode(' ', array_slice($words, 0, 40));

        if (preg_match('/\byou(?:r|rs|rself)?\b/iu', $opening) !== 1) {
            $failures[] = 'The opening does not address the player as you.';
        }

        $name = mb_trim($protagonist);

        if ($name !== '' && mb_stripos($prose, $name) === false) {
            $failures[] = "The cold open never names {$name}.";
        }

        if ($name !== '' && mb_stripos(implode(' ', array_slice($words, 0, 80)), $name) === false) {
            $failures[] = "{$name} is not named within the first 80 words.";
        }

        if ($this->endsWithPlayerQuestion($prose)) {
            $failures[] = 'The cold open ends on a question to the player. End on the action of the choice instead.';
        }

        return $failures;
    }

    public function wordCount(string $prose): int
    {
        return count($this->words($prose));
    }

    /**
     * @return list<string>
     */
    private function words(string $prose): array
    {
        $parts = preg_split('/\s+/u', mb_trim($prose)) ?: [];

        return array_values(array_filter($parts, fn (string $word): bool => $word !== ''));
    }

    private function endsWithPlayerQuestion(string $prose): bool
    {
        if (preg_match('/\?\s*["\'”’)\]]*\s*$/u', $prose) === 1) {
            return true;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', mb_trim($prose)) ?: [];

        if ($sentences === []) {
            return false;
        }

        $last = mb_trim((string) $sentences[array_key_last($sentences)]);

        return preg_match('/^(do you|what do you|will you|should you|what will you)\b/iu', $last) === 1;
    }
}
