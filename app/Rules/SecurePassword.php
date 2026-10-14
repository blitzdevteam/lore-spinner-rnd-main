<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\CommonPasswords;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SecurePassword implements ValidationRule
{
    private const int DIGIT_RUN = 4;

    private const int LETTER_RUN = 5;

    private const int REPEATED_RUN = 4;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if ($this->isCommon($value)) {
            $fail('The password is too common. Choose one that is harder to guess.');

            return;
        }

        if ($this->hasRepeatedRun($value)) {
            $fail('The password cannot repeat the same character four times in a row.');

            return;
        }

        if ($this->hasSequentialRun($value)) {
            $fail('The password cannot contain sequential numbers like 1234 or sequential letters like abcde.');
        }
    }

    private function isCommon(string $password): bool
    {
        $normalized = mb_strtolower($password);

        if (CommonPasswords::contains($normalized)) {
            return true;
        }

        $letters = preg_replace('/[^a-z]/', '', $normalized);

        if (! is_string($letters) || $letters === '' || $letters === $normalized) {
            return false;
        }

        return CommonPasswords::contains($letters);
    }

    private function hasRepeatedRun(string $password): bool
    {
        $characters = mb_str_split(mb_strtolower($password));
        $run = 1;
        $previous = null;

        foreach ($characters as $character) {
            if ($character === $previous) {
                $run++;

                if ($run >= self::REPEATED_RUN) {
                    return true;
                }

                continue;
            }

            $run = 1;
            $previous = $character;
        }

        return false;
    }

    private function hasSequentialRun(string $password): bool
    {
        $characters = mb_str_split(mb_strtolower($password));
        $run = 1;
        $direction = 0;
        $previous = null;

        foreach ($characters as $character) {
            $current = $this->sequenceCode($character);

            if ($previous !== null && $current !== null && $this->continuesSequence($previous, $current, $direction)) {
                $direction = $current > $previous ? 1 : -1;
                $run++;

                if ($this->runIsRejected($previous, $run)) {
                    return true;
                }
            } else {
                $run = 1;
                $direction = 0;
            }

            $previous = $current;
        }

        return $this->lettersAreSequential($password);
    }

    private function sequenceCode(string $character): ?int
    {
        $code = mb_ord($character);

        if (($code >= 48 && $code <= 57) || ($code >= 97 && $code <= 122)) {
            return $code;
        }

        return null;
    }

    private function continuesSequence(int $previous, int $current, int $direction): bool
    {
        if (! $this->sameKind($previous, $current) || abs($current - $previous) !== 1) {
            return false;
        }

        $step = $current > $previous ? 1 : -1;

        return $direction === 0 || $direction === $step;
    }

    private function sameKind(int $previous, int $current): bool
    {
        $previousIsDigit = $previous >= 48 && $previous <= 57;

        return $previousIsDigit === ($current >= 48 && $current <= 57);
    }

    private function runIsRejected(int $code, int $run): bool
    {
        $isDigit = $code >= 48 && $code <= 57;

        return $isDigit ? $run >= self::DIGIT_RUN : $run >= self::LETTER_RUN;
    }

    private function lettersAreSequential(string $password): bool
    {
        $letters = preg_replace('/[^a-z]/', '', mb_strtolower($password));

        if (! is_string($letters) || mb_strlen($letters) < self::DIGIT_RUN) {
            return false;
        }

        $direction = ord($letters[1]) - ord($letters[0]);

        if (abs($direction) !== 1) {
            return false;
        }

        $length = mb_strlen($letters);

        for ($index = 2; $index < $length; $index++) {
            if (ord($letters[$index]) - ord($letters[$index - 1]) !== $direction) {
                return false;
            }
        }

        return true;
    }
}
