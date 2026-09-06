<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\ProcessGameOpeningAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Jobs\Multiplayer\ResolveMultiplayerLobbyDecisionDeadlineJob;
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
            $this->processGameOpening->handleBegin($lobby->game_id, $lobby->voiceOverEnabled());
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

        if (
            $lobby->mode === MultiplayerLobbyModeEnum::Arbiter
            && $sourcePrompt !== null
            && $sourcePrompt->choices !== []
        ) {
            $sourcePrompt->forceFill(['choices' => []])->save();
        }

        $rouletteDecisionDeadlineAt = null;

        $updatedLobby = DB::transaction(function () use ($lobby, $sourcePrompt, &$rouletteDecisionDeadlineAt): ?MultiplayerLobby {
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
            $turnStatus = $game?->current_session_complete
                ? MultiplayerArbiterTurnStatusEnum::SessionComplete
                : ($lockedLobby->mode === MultiplayerLobbyModeEnum::Roulette
                    ? MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice
                    : MultiplayerArbiterTurnStatusEnum::CollectingProposals);
            $decisionDeadlineAt = null;
            $decisionRemainingSeconds = null;

            if ($turnStatus === MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice) {
                if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Started) {
                    $decisionDeadlineAt = now()->addSeconds($lockedLobby->decisionWindowSeconds());
                    $rouletteDecisionDeadlineAt = $decisionDeadlineAt;
                } else {
                    $decisionRemainingSeconds = $lockedLobby->decisionWindowSeconds();
                }
            }

            $lockedLobby->update([
                'current_source_prompt_id' => $sourcePrompt->id,
                'turn_status' => $turnStatus,
                'decision_deadline_at' => $decisionDeadlineAt,
                'decision_remaining_seconds' => $decisionRemainingSeconds,
            ]);

            return $lockedLobby;
        });

        if ($updatedLobby !== null) {
            MultiplayerArbiterStateChanged::dispatch($updatedLobby);
        }

        if ($updatedLobby !== null && $rouletteDecisionDeadlineAt !== null) {
            ResolveMultiplayerLobbyDecisionDeadlineJob::dispatch(
                $updatedLobby->id,
                $sourcePrompt->id,
                $rouletteDecisionDeadlineAt->getTimestamp(),
            )->delay($rouletteDecisionDeadlineAt->copy()->addSeconds(1));
        }
    }
}
