<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\Factory;
use Illuminate\Validation\NotPwnedVerifier;

final class NotBreachedPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || ! config('auth.check_breached_passwords')) {
            return;
        }

        $safe = (new NotPwnedVerifier(app(Factory::class), 3))->verify([
            'value' => $value,
            'threshold' => 0,
        ]);

        if (! $safe) {
            $fail('The password has appeared in a data breach. Choose a different password.');
        }
    }
}
