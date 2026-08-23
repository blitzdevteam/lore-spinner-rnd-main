<?php

declare(strict_types=1);

namespace App\Enums\Multiplayer;

enum MultiplayerLobbyMemberRoleEnum: string
{
    case Host = 'host';
    case Player = 'player';
}
