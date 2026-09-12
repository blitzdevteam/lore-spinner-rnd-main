<?php

declare(strict_types=1);

namespace App\Enums\Multiplayer;

enum MultiplayerLobbyModeEnum: string
{
    case Arbiter = 'arbiter';
    case Roulette = 'roulette';
}
