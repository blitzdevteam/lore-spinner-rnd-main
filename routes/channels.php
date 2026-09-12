<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, string|int $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.{gameId}', function (User $user, string $gameId): bool {
    $ownerId = Game::query()->whereKey($gameId)->value('user_id');

    return $ownerId !== null && (int) $ownerId === (int) $user->id;
}, ['guards' => ['user']]);

Broadcast::channel('multiplayer.lobbies', static fn (User $user): bool => $user->is_profile_completed, ['guards' => ['user']]);

Broadcast::channel('multiplayer.lobby.{lobbyId}', function (User $user, string $lobbyId): array|bool {
    $lobby = MultiplayerLobby::query()->find($lobbyId);

    if ($lobby === null || ! $lobby->isMember($user)) {
        return false;
    }

    return [
        'id' => (string) $user->id,
        'name' => $user->full_name,
        'avatar' => $user->avatar,
    ];
}, ['guards' => ['user']]);
