<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerLobbyMemberReadinessChanged;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Models\MultiplayerLobby;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateMultiplayerLobbyReadinessAction
{
    public function handle(User $user, MultiplayerLobby $lobby, bool $isReady): MultiplayerLobby
    {
        $readinessChanged = false;

        $lobby = DB::transaction(function () use ($user, $lobby, $isReady, &$readinessChanged): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());

            if ($lockedLobby->status !== MultiplayerLobbyStatusEnum::Waiting) {
                throw MultiplayerLobbyStateException::isNotWaiting();
            }

            $member = $lockedLobby->members()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->firstOrFail();

            if ($member->is_ready !== $isReady) {
                $member->update(['is_ready' => $isReady]);
                $readinessChanged = true;
            }

            return $lockedLobby->load(['host', 'members.user']);
        });

        if ($readinessChanged) {
            MultiplayerLobbyMemberReadinessChanged::dispatch($lobby);
        }

        return $lobby;
    }
}
