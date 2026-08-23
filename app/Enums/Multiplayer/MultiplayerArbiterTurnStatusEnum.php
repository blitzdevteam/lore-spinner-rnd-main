<?php

declare(strict_types=1);

namespace App\Enums\Multiplayer;

enum MultiplayerArbiterTurnStatusEnum: string
{
    case Opening = 'opening';
    case CollectingProposals = 'collecting_proposals';
    case AwaitingArbiter = 'awaiting_arbiter';
    case Narrating = 'narrating';
    case SessionComplete = 'session_complete';
}
