<?php

declare(strict_types=1);

namespace App\Support;

use App\Rules\NotBreachedPassword;
use App\Rules\SecurePassword;
use Illuminate\Validation\Rules\Password;

final class PasswordRequirements
{
    /**
     * Rules for a newly chosen password. Stored passwords are not rechecked.
     *
     * @return list<string|Password|SecurePassword|NotBreachedPassword>
     */
    public static function rules(): array
    {
        return [
            'string',
            'max:255',
            Password::min(8),
            new SecurePassword,
            new NotBreachedPassword,
        ];
    }
}
