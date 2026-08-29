<?php

declare(strict_types=1);

namespace App\Jobs\Multiplayer;

use App\Actions\Multiplayer\ProcessMultiplayerLobbyTurnAction;
use App\Actions\Multiplayer\RecordMultiplayerLobbyHeartbeatAction;
use App\Actions\Multiplayer\ResolveMultiplayerLobbyDecisionDeadlineAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ResolveMultiplayerLobbyDecisionDeadlineJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $lobbyId,
        private string $sourcePromptId,
        private int $deadlineTimestamp,
    ) {}

    public function handle(
        RecordMultiplayerLobbyHeartbeatAction $recordMultiplayerLobbyHeartbeat,
        ResolveMultiplayerLobbyDecisionDeadlineAction $resolveMultiplayerLobbyDecisionDeadline,
        ProcessMultiplayerLobbyTurnAction $processMultiplayerLobbyTurn,
    ): void {
        if ($recordMultiplayerLobbyHeartbeat->detectDepartures($this->lobbyId)) {
            return;
        }

        $selection = $resolveMultiplayerLobbyDecisionDeadline->handle(
            $this->lobbyId,
            $this->sourcePromptId,
            $this->deadlineTimestamp,
        );

        if ($selection === null) {
            return;
        }

        $processMultiplayerLobbyTurn->handle($selection['lobby']->id, $selection['proposal_id']);
    }
}
