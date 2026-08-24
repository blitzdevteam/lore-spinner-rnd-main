<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResolveMultiplayerLobbyGuestAction
{
    public function handle(string $email, string $firstName, string $lastName): User
    {
        return DB::transaction(function () use ($email, $firstName, $lastName): User {
            $user = User::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($user?->is_profile_completed) {
                throw ValidationException::withMessages([
                    'email' => 'This email belongs to an existing player. Please log in to join the room.',
                ]);
            }

            if ($user !== null) {
                $user->update([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);

                return $user;
            }

            return User::query()->create([
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Str::random(64),
            ]);
        });
    }
}
