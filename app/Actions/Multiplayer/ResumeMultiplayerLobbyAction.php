<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\ProcessGameOpeningAction;
use App\Actions\Game\ProcessGameTurnAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyProposal;
use App\Models\Prompt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ResumeMultiplayerLobbyAction
{
    public function __construct(
        private readonly ProcessGameOpeningAction $processGameOpening,
        private readonly ProcessGameTurnAction $processGameTurn,
        private readonly ProcessMultiplayerLobbyOpeningAction $processOpening,
        private readonly ProcessMultiplayerLobbyNextSessionAction $processNextSession,
        private readonly ProcessMultiplayerLobbyTurnAction $processTurn,
    ) {}

    public function handle(string $lobbyId): void
    {
        $lobby = MultiplayerLobby::query()->find($lobbyId);

        if (
            $lobby === null
            || $lobby->status !== MultiplayerLobbyStatusEnum::Started
            || $lobby->game_id === null
        ) {
            return;
        }

        if ($lobby->turn_status === MultiplayerArbiterTurnStatusEnum::Opening) {
            $this->resumeOpening($lobby);

            return;
        }

        if ($lobby->turn_status === MultiplayerArbiterTurnStatusEnum::Narrating) {
            $this->resumeNarration($lobby);
        }
    }

    private function resumeOpening(MultiplayerLobby $lobby): void
    {
        $game = Game::query()->find($lobby->game_id);

        if ($game === null || Cache::has($this->processGameOpening->pendingKey($game->id))) {
            return;
        }

        $sourcePrompt = Prompt::query()
            ->where('game_id', $game->id)
            ->where('session_number', $game->current_session_number)
            ->whereNull('source_prompt_id')
            ->whereNull('prompt')
            ->orderByDesc('id')
            ->first();

        if ($sourcePrompt !== null) {
            $this->completeOpening($lobby, $sourcePrompt);

            return;
        }

        if ((int) $game->current_session_number === 1) {
            $this->processOpening->handle($lobby->id);

            return;
        }

        $this->processNextSession->handle($lobby->id);
    }

    private function resumeNarration(MultiplayerLobby $lobby): void
    {
        $gameId = $lobby->game_id;

        if ($gameId === null || Cache::has($this->processGameTurn->pendingKey($gameId))) {
            return;
        }

        $proposalId = MultiplayerLobbyProposal::query()
            ->where('lobby_id', $lobby->id)
            ->where('source_prompt_id', $lobby->current_source_prompt_id)
            ->whereNotNull('selected_at')
            ->value('id');

        if (! is_string($proposalId)) {
            return;
        }

        $this->processTurn->handle($lobby->id, $proposalId);
    }

    private function completeOpening(MultiplayerLobby $lobby, Prompt $sourcePrompt): void
    {
        if ($sourcePrompt->choices !== []) {
            $sourcePrompt->forceFill(['choices' => []])->save();
        }

        $updatedLobby = DB::transaction(function () use ($lobby, $sourcePrompt): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobby->id);

            if (
                $lockedLobby === null
                || $lockedLobby->status !== MultiplayerLobbyStatusEnum::Started
                || $lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::Opening
                || $lockedLobby->current_source_prompt_id !== null
            ) {
                return null;
            }

            $game = Game::query()->find($lockedLobby->game_id);

            $lockedLobby->update([
                'current_source_prompt_id' => $sourcePrompt->id,
                'turn_status' => $game?->current_session_complete
                    ? MultiplayerArbiterTurnStatusEnum::SessionComplete
                    : MultiplayerArbiterTurnStatusEnum::CollectingProposals,
            ]);

            return $lockedLobby;
        });

        if ($updatedLobby !== null) {
            MultiplayerArbiterStateChanged::dispatch($updatedLobby);
        }
    }
}
