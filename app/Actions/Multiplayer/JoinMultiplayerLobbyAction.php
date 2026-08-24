<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyMemberRoleEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerLobbyMemberJoined;
use App\Exceptions\Multiplayer\MultiplayerLobbyJoinException;
use App\Models\MultiplayerLobby;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class JoinMultiplayerLobbyAction
{
    public function handle(User $user, MultiplayerLobby $lobby): MultiplayerLobby
    {
        $joined = false;

        $lobby = DB::transaction(function () use ($user, $lobby, &$joined): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());

            if ($lockedLobby->members()->whereBelongsTo($user)->exists()) {
                return $lockedLobby->load(['host', 'members.user']);
            }

            if ($lockedLobby->status !== MultiplayerLobbyStatusEnum::Waiting) {
                throw MultiplayerLobbyJoinException::isNotWaiting();
            }

            if ($lockedLobby->members()->count() >= $lockedLobby->max_players) {
                throw MultiplayerLobbyJoinException::isFull();
            }

            $lockedLobby->members()->create([
                'user_id' => $user->id,
                'role' => MultiplayerLobbyMemberRoleEnum::Player,
                'joined_at' => now(),
            ]);

            $joined = true;

            return $lockedLobby->load(['host', 'members.user']);
        });

        if ($joined) {
            MultiplayerLobbyMemberJoined::dispatch($lobby);
        }

        return $lobby;
    }
}
