<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Models\MultiplayerLobby;
use Illuminate\Support\Facades\DB;

final class ReconcileMultiplayerLobbyPauseAction
{
    public function handle(MultiplayerLobby $lobby): bool
    {
        $resumedLobby = DB::transaction(function () use ($lobby): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobby->id);

            if (
                $lockedLobby === null
                || $lockedLobby->status !== MultiplayerLobbyStatusEnum::Paused
                || $lockedLobby->game_id === null
                || $lockedLobby->members()->whereNotNull('left_at')->exists()
            ) {
                return null;
            }

            $lockedLobby->update(['status' => MultiplayerLobbyStatusEnum::Started]);

            return $lockedLobby;
        });

        if ($resumedLobby === null) {
            return false;
        }

        MultiplayerArbiterStateChanged::dispatch($resumedLobby);

        $lobbyId = $resumedLobby->id;

        dispatch(static function () use ($lobbyId): void {
            app(ResumeMultiplayerLobbyAction::class)->handle($lobbyId);
        })->afterResponse();

        return true;
    }
}
