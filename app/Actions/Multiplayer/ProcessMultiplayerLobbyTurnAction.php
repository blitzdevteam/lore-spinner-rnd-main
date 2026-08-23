<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\ProcessGameTurnAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\MultiplayerLobbyProposal;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessMultiplayerLobbyTurnAction
{
    public function __construct(private readonly ProcessGameTurnAction $processGameTurn) {}

    public function handle(string $lobbyId, string $proposalId): void
    {
        $lobby = MultiplayerLobby::query()->find($lobbyId);

        if (
            $lobby === null
            || $lobby->status !== MultiplayerLobbyStatusEnum::Started
            || $lobby->turn_status !== MultiplayerArbiterTurnStatusEnum::Narrating
            || $lobby->game_id === null
            || $lobby->current_source_prompt_id === null
        ) {
            return;
        }

        $proposal = MultiplayerLobbyProposal::query()
            ->whereKey($proposalId)
            ->where('lobby_id', $lobby->id)
            ->where('source_prompt_id', $lobby->current_source_prompt_id)
            ->whereNotNull('selected_at')
            ->first();
        $sourcePrompt = Prompt::query()
            ->whereKey($lobby->current_source_prompt_id)
            ->where('game_id', $lobby->game_id)
            ->first();

        if ($proposal === null || $sourcePrompt === null) {
            return;
        }

        try {
            if ($sourcePrompt->prompt === null) {
                $this->processGameTurn->handle(
                    gameId: $lobby->game_id,
                    sourcePromptId: $sourcePrompt->id,
                    storedPromptValue: $proposal->text,
                    playerAction: $proposal->text,
                    explicitBranch: null,
                    userId: $lobby->host_user_id,
                );
            }
        } catch (Throwable $exception) {
            Log::channel('narration')->error('multiplayer.arbiter.turn_failed', [
                'lobby_id' => $lobby->id,
                'game_id' => $lobby->game_id,
                'source_prompt_id' => $sourcePrompt->id,
                'proposal_id' => $proposal->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $responsePrompt = Prompt::query()
            ->where('game_id', $lobby->game_id)
            ->where('source_prompt_id', $sourcePrompt->id)
            ->orderByDesc('id')
            ->first();
        $updatedLobby = DB::transaction(function () use ($lobby, $proposal, $sourcePrompt, $responsePrompt): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobby->id);

            if (
                $lockedLobby === null
                || $lockedLobby->status !== MultiplayerLobbyStatusEnum::Started
                || $lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::Narrating
                || $lockedLobby->current_source_prompt_id !== $sourcePrompt->id
            ) {
                return null;
            }

            if ($responsePrompt === null) {
                MultiplayerLobbyProposal::query()
                    ->whereKey($proposal->id)
                    ->update(['selected_at' => null]);
                $lockedLobby->update([
                    'turn_status' => MultiplayerArbiterTurnStatusEnum::AwaitingArbiter,
                ]);

                return $lockedLobby;
            }

            $game = Game::query()->find($lockedLobby->game_id);

            $members = $lockedLobby->members()
                ->orderBy('joined_at')
                ->orderBy('id')
                ->get();
            $nextArbiterMemberId = $this->nextArbiterMemberId(
                $members,
                $lockedLobby->current_arbiter_member_id,
            );

            if ($game?->current_session_complete) {
                $lockedLobby->update([
                    'current_source_prompt_id' => $responsePrompt->id,
                    'current_arbiter_member_id' => $nextArbiterMemberId,
                    'turn_status' => MultiplayerArbiterTurnStatusEnum::SessionComplete,
                ]);

                return $lockedLobby;
            }

            $lockedLobby->update([
                'current_source_prompt_id' => $responsePrompt->id,
                'current_arbiter_member_id' => $nextArbiterMemberId,
                'turn_status' => MultiplayerArbiterTurnStatusEnum::CollectingProposals,
            ]);

            return $lockedLobby;
        });

        if ($updatedLobby !== null) {
            MultiplayerArbiterStateChanged::dispatch($updatedLobby);
        }
    }

    /**
     * @param  Collection<int, MultiplayerLobbyMember>  $members
     */
    private function nextArbiterMemberId(Collection $members, ?string $currentArbiterMemberId): ?string
    {
        if ($members->isEmpty()) {
            return null;
        }

        $currentIndex = $members->search(
            static fn (MultiplayerLobbyMember $member): bool => $member->id === $currentArbiterMemberId,
        );

        if ($currentIndex === false) {
            return $members->first()?->id;
        }

        return $members->get(($currentIndex + 1) % $members->count())?->id;
    }
}
