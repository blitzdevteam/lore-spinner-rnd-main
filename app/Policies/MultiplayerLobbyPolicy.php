<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MultiplayerLobby;
use App\Models\User;

final class MultiplayerLobbyPolicy
{
    public function view(User $user, MultiplayerLobby $lobby): bool
    {
        return $lobby->isMember($user);
    }

    public function start(User $user, MultiplayerLobby $lobby): bool
    {
        return $lobby->host_user_id === $user->id;
    }

    public function updateSettings(User $user, MultiplayerLobby $lobby): bool
    {
        return $lobby->host_user_id === $user->id;
    }
}
