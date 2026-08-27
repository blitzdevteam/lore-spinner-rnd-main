<?php

declare(strict_types=1);

namespace App\Enums\Multiplayer;

enum MultiplayerLobbyStatusEnum: string
{
    case Waiting = 'waiting';
    case Started = 'started';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
