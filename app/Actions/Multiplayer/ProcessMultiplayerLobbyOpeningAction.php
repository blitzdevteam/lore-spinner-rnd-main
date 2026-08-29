<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\ProcessGameOpeningAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessMultiplayerLobbyOpeningAction
{
    public function __construct(private readonly ProcessGameOpeningAction $processGameOpening) {}

    public function handle(string $lobbyId): void
    {
        $lobby = MultiplayerLobby::query()->find($lobbyId);

        if (
            $lobby === null
            || $lobby->status !== MultiplayerLobbyStatusEnum::Started
            || $lobby->turn_status !== MultiplayerArbiterTurnStatusEnum::Opening
            || $lobby->game_id === null
        ) {
            return;
        }

        try {
            $this->processGameOpening->handleBegin($lobby->game_id);
        } catch (Throwable $exception) {
            Log::channel('narration')->error('multiplayer.arbiter.opening_failed', [
                'lobby_id' => $lobby->id,
                'game_id' => $lobby->game_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $sourcePrompt = Prompt::query()
            ->where('game_id', $lobby->game_id)
            ->where('session_number', 1)
            ->whereNull('prompt')
            ->orderByDesc('id')
            ->first();

        if ($sourcePrompt !== null && $sourcePrompt->choices !== []) {
            $sourcePrompt->forceFill(['choices' => []])->save();
        }

        $updatedLobby = DB::transaction(function () use ($lobby, $sourcePrompt): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobby->id);

            if (
                $lockedLobby === null
                || ! in_array($lockedLobby->status, [
                    MultiplayerLobbyStatusEnum::Started,
                    MultiplayerLobbyStatusEnum::Paused,
                ], true)
                || $lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::Opening
            ) {
                return null;
            }

            if ($sourcePrompt === null) {
                return $lockedLobby;
            }

            $game = Game::query()->find($lockedLobby->game_id);

            $lockedLobby->update([
                'current_source_prompt_id' => $sourcePrompt->id,
                'turn_status' => $game?->current_session_complete
                    ? MultiplayerArbiterTurnStatusEnum::SessionComplete
                    : MultiplayerArbiterTurnStatusEnum::CollectingProposals,
                'decision_deadline_at' => null,
                'decision_remaining_seconds' => null,
            ]);

            return $lockedLobby;
        });

        if ($updatedLobby !== null) {
            MultiplayerArbiterStateChanged::dispatch($updatedLobby);
        }
    }
}
