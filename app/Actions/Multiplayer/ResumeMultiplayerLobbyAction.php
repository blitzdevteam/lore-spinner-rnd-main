<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\ProcessGameOpeningAction;
use App\Actions\Game\ProcessGameTurnAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Jobs\Multiplayer\ResolveMultiplayerLobbyDecisionDeadlineJob;
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

            return;
        }

        if (in_array($lobby->turn_status, [
            MultiplayerArbiterTurnStatusEnum::CollectingProposals,
            MultiplayerArbiterTurnStatusEnum::AwaitingArbiter,
            MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice,
        ], true)) {
            $this->resumeDecisionDeadline($lobby);
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

    private function resumeDecisionDeadline(MultiplayerLobby $lobby): void
    {
        $resumedDecision = DB::transaction(function () use ($lobby): ?array {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobby->id);

            if (
                $lockedLobby === null
                || $lockedLobby->status !== MultiplayerLobbyStatusEnum::Started
                || ! in_array($lockedLobby->turn_status, [
                    MultiplayerArbiterTurnStatusEnum::CollectingProposals,
                    MultiplayerArbiterTurnStatusEnum::AwaitingArbiter,
                    MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice,
                ], true)
                || $lockedLobby->current_source_prompt_id === null
                || $lockedLobby->decision_deadline_at !== null
                || ! $lockedLobby->hasDecisionTimer()
            ) {
                return null;
            }

            $remainingSeconds = max(
                0,
                $lockedLobby->decision_remaining_seconds
                    ?? $lockedLobby->decisionWindowSeconds(),
            );

            if ($remainingSeconds <= 0) {
                return null;
            }
            $deadlineAt = now()->addSeconds($remainingSeconds);

            $lockedLobby->update([
                'decision_deadline_at' => $deadlineAt,
                'decision_remaining_seconds' => null,
            ]);

            return [
                'lobby' => $lockedLobby->load(['host', 'members.user', 'game']),
                'source_prompt_id' => (string) $lockedLobby->current_source_prompt_id,
                'deadline_at' => $deadlineAt,
            ];
        });

        if ($resumedDecision === null) {
            return;
        }

        MultiplayerArbiterStateChanged::dispatch($resumedDecision['lobby']);

        ResolveMultiplayerLobbyDecisionDeadlineJob::dispatch(
            $resumedDecision['lobby']->id,
            $resumedDecision['source_prompt_id'],
            $resumedDecision['deadline_at']->getTimestamp(),
        )->delay($resumedDecision['deadline_at']->copy()->addSeconds(
            $resumedDecision['lobby']->mode === MultiplayerLobbyModeEnum::Roulette ? 1 : 3,
        ));
    }

    private function completeOpening(MultiplayerLobby $lobby, Prompt $sourcePrompt): void
    {
        if ($lobby->mode === MultiplayerLobbyModeEnum::Arbiter && $sourcePrompt->choices !== []) {
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
                || $lockedLobby->current_source_prompt_id !== null
            ) {
                return null;
            }

            $game = Game::query()->find($lockedLobby->game_id);
            $turnStatus = $game?->current_session_complete
                ? MultiplayerArbiterTurnStatusEnum::SessionComplete
                : ($lockedLobby->mode === MultiplayerLobbyModeEnum::Roulette
                    ? MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice
                    : MultiplayerArbiterTurnStatusEnum::CollectingProposals);
            $decisionTimer = $turnStatus === MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice
                ? $lockedLobby->nextDecisionTimerAttributes()
                : [
                    'decision_deadline_at' => null,
                    'decision_remaining_seconds' => null,
                ];
            $rouletteDecisionDeadlineAt = $decisionTimer['decision_deadline_at'];

            $lockedLobby->update([
                'current_source_prompt_id' => $sourcePrompt->id,
                'turn_status' => $turnStatus,
                'decision_deadline_at' => $decisionTimer['decision_deadline_at'],
                'decision_remaining_seconds' => $decisionTimer['decision_remaining_seconds'],
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
