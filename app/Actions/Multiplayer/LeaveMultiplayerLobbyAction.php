<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Events\MultiplayerLobbyMemberLeft;
use App\Models\MultiplayerLobby;
use App\Models\User;
use App\Support\Multiplayer\MultiplayerLobbyPauseState;
use Illuminate\Support\Facades\DB;

final class LeaveMultiplayerLobbyAction
{
    public function handle(User $user, MultiplayerLobby $lobby): bool
    {
        $remainingLobby = DB::transaction(function () use ($user, $lobby): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());
            $leftAt = now();

            if (in_array($lockedLobby->status, [
                MultiplayerLobbyStatusEnum::Started,
                MultiplayerLobbyStatusEnum::Paused,
                MultiplayerLobbyStatusEnum::Closed,
                MultiplayerLobbyStatusEnum::Completed,
            ], true)) {
                $lockedLobby->members()
                    ->whereBelongsTo($user)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->update([
                        'left_at' => $leftAt,
                        'last_seen_at' => $leftAt,
                    ]);
                $lockedLobby->update(['last_activity_at' => $leftAt]);

                MultiplayerLobbyPauseState::apply($lockedLobby, $leftAt);

                return $lockedLobby->load(['host', 'members.user']);
            }

            $lockedLobby->members()
                ->whereBelongsTo($user)
                ->firstOrFail()
                ->delete();
            $lockedLobby->update(['last_activity_at' => $leftAt]);

            return $lockedLobby->load(['host', 'members.user']);
        });

        MultiplayerLobbyMemberLeft::dispatch($remainingLobby);

        if ($remainingLobby->status === MultiplayerLobbyStatusEnum::Paused) {
            MultiplayerArbiterStateChanged::dispatch($remainingLobby);

            return true;
        }

        return false;
    }
}
