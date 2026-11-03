<?php

declare(strict_types=1);

namespace App\Actions\Discord;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final class ResolveDiscordGuestAction
{
    /**
     * @param  array{id: string, username: string, global_name: string|null}  $discordUser
     */
    public function handle(array $discordUser): User
    {
        try {
            return $this->resolve($discordUser);
        } catch (UniqueConstraintViolationException) {
            return $this->resolve($discordUser);
        }
    }

    /**
     * @param  array{id: string, username: string, global_name: string|null}  $discordUser
     */
    private function resolve(array $discordUser): User
    {
        $displayName = Str::limit($discordUser['global_name'] ?? $discordUser['username'], 64, '');

        $user = User::query()->firstOrCreate(
            ['discord_user_id' => $discordUser['id']],
            [
                'email' => 'discord-'.$discordUser['id'].'@'.User::PENDING_EMAIL_DOMAIN,
                'first_name' => $displayName,
                'last_name' => null,
                'password' => Str::random(64),
            ],
        );

        if ($user->first_name !== $displayName && ! $user->is_profile_completed) {
            $user->update(['first_name' => $displayName]);
        }

        return $user;
    }
}
