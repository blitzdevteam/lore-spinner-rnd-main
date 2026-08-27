<?php

declare(strict_types=1);

namespace App\Support\Multiplayer;

use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Models\MultiplayerLobby;
use Illuminate\Support\Carbon;

final class MultiplayerLobbyPauseState
{
    public static function apply(MultiplayerLobby $lobby, Carbon $pausedAt): void
    {
        if ($lobby->status !== MultiplayerLobbyStatusEnum::Started) {
            return;
        }

        $attributes = [
            'status' => MultiplayerLobbyStatusEnum::Paused,
        ];

        if (
            $lobby->turn_status === MultiplayerArbiterTurnStatusEnum::AwaitingArbiter
            && $lobby->decision_deadline_at !== null
        ) {
            $attributes['decision_deadline_at'] = null;
            $attributes['decision_remaining_seconds'] = max(
                0,
                $lobby->decision_deadline_at->getTimestamp() - $pausedAt->getTimestamp(),
            );
        }

        $lobby->update($attributes);
    }
}
