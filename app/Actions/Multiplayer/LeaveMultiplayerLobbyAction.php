<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Events\MultiplayerLobbyDeleted;
use App\Events\MultiplayerLobbyMemberLeft;
use App\Models\MultiplayerLobby;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LeaveMultiplayerLobbyAction
{
    public function handle(User $user, MultiplayerLobby $lobby): void
    {
        /** @var array<int, int> $deletedLobbyMemberUserIds */
        $deletedLobbyMemberUserIds = [];

        $remainingLobby = DB::transaction(function () use ($user, $lobby, &$deletedLobbyMemberUserIds): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());

            if ($lockedLobby->host_user_id === $user->id) {
                $deletedLobbyMemberUserIds = array_values(array_unique([
                    ...$lockedLobby->members()
                        ->pluck('user_id')
                        ->map(static fn (mixed $memberUserId): int => (int) $memberUserId)
                        ->all(),
                    $lockedLobby->host_user_id,
                ]));

                $lockedLobby->members()->delete();
                $lockedLobby->delete();

                return null;
            }

            $lockedLobby->members()
                ->whereBelongsTo($user)
                ->firstOrFail()
                ->delete();

            return $lockedLobby->load(['host', 'members.user']);
        });

        if ($remainingLobby === null) {
            MultiplayerLobbyDeleted::dispatch((string) $lobby->getKey(), $deletedLobbyMemberUserIds);

            return;
        }

        MultiplayerLobbyMemberLeft::dispatch($remainingLobby);
    }
}
