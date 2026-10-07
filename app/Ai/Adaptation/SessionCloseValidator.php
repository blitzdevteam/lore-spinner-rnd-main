<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use RuntimeException;

/**
 * The close is the last thing the player reads in a session. It may not ask
 * them a question, instruct them, or offer an option that is not theirs.
 */
final class SessionCloseValidator
{
    private const string TRAILING_QUESTION = '/\?\s*["\'”’)\]]*\s*$/u';

    /**
     * @param  array<string, mixed>  $close
     *
     * @throws RuntimeException
     */
    public function validate(array $close): void
    {
        $failures = [];

        foreach (['resolution_prose', 'hook_transition', 'session_end_choice.final_line'] as $path) {
            if (preg_match(self::TRAILING_QUESTION, mb_trim((string) data_get($close, $path, ''))) === 1) {
                $failures[] = "{$path} ends on a question. End on an image or action instead.";
            }
        }

        if (preg_match('/\b(choose|choice|decide)\b/iu', (string) data_get($close, 'session_end_choice.final_line', '')) === 1) {
            $failures[] = 'session_end_choice.final_line talks to the player about choosing. Make it an in-world closing image.';
        }

        foreach (['option_a', 'option_b', 'option_c'] as $option) {
            $text = (string) data_get($close, "session_end_choice.{$option}.text", '');

            if (preg_match('/\byou(?:r|rs|rself)?\b/iu', $text) !== 1) {
                $failures[] = "session_end_choice.{$option}.text is not the player's own action: \"{$text}\".";
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(implode("\n", $failures));
        }
    }
}
