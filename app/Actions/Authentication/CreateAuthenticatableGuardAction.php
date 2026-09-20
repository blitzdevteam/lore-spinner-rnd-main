<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Enums\UserType;
use App\Models\Creator;
use App\Models\User;
use App\Models\Writer;
use InvalidArgumentException;

final readonly class CreateAuthenticatableGuardAction
{
    private const array GUARD_MODELS = [
        'user' => User::class,
        'creator' => Creator::class,
        'writer' => Writer::class,
    ];

    public function handle(string $guard, string $email, string $password): User|Creator|Writer
    {
        if (! array_key_exists($guard, self::GUARD_MODELS)) {
            throw new InvalidArgumentException(sprintf('Guard `%s` is an invalid guard.', $guard));
        }

        $model = self::GUARD_MODELS[$guard];
        $attributes = [
            'email' => $email,
            'password' => $password,
        ];

        if ($model === User::class) {
            $attributes['type'] = UserType::User;
        }

        return $model::create($attributes);
    }
}
