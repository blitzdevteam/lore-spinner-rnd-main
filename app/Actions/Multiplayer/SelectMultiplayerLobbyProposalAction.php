<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\MultiplayerLobbyProposal;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SelectMultiplayerLobbyProposalAction
{
    public function handle(
        User $user,
        MultiplayerLobby $lobby,
        MultiplayerLobbyProposal $proposal,
    ): MultiplayerLobby {
        $lobby = DB::transaction(function () use ($user, $lobby, $proposal): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());

            if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Paused) {
                throw MultiplayerLobbyStateException::isPaused();
            }

            if ($lockedLobby->status !== MultiplayerLobbyStatusEnum::Started) {
                throw MultiplayerLobbyStateException::isNotStarted();
            }

            if (
                $lockedLobby->mode !== MultiplayerLobbyModeEnum::Arbiter
                || $lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::AwaitingArbiter
            ) {
                throw MultiplayerLobbyStateException::proposalsAreNotReady();
            }

            $members = $lockedLobby->members()
                ->orderBy('joined_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $arbiter = $members->firstWhere('id', $lockedLobby->current_arbiter_member_id);

            if (! $arbiter instanceof MultiplayerLobbyMember || $arbiter->user_id !== $user->id) {
                throw MultiplayerLobbyStateException::onlyCurrentArbiterCanChoose();
            }

            $sourcePrompt = Prompt::query()
                ->whereKey($lockedLobby->current_source_prompt_id)
                ->where('game_id', $lockedLobby->game_id)
                ->lockForUpdate()
                ->first();

            if ($sourcePrompt === null || $sourcePrompt->prompt !== null) {
                throw MultiplayerLobbyStateException::proposalsAreNotReady();
            }

            $requiredMemberIds = $members
                ->reject(static fn (MultiplayerLobbyMember $member): bool => $member->id === $arbiter->id)
                ->pluck('id')
                ->all();
            $submittedMemberIds = MultiplayerLobbyProposal::query()
                ->where('lobby_id', $lockedLobby->id)
                ->where('source_prompt_id', $sourcePrompt->id)
                ->lockForUpdate()
                ->pluck('member_id')
                ->all();

            if (array_diff($requiredMemberIds, $submittedMemberIds) !== []) {
                throw MultiplayerLobbyStateException::proposalsAreNotReady();
            }

            $selectedProposal = MultiplayerLobbyProposal::query()
                ->whereKey($proposal->getKey())
                ->where('lobby_id', $lockedLobby->id)
                ->where('source_prompt_id', $sourcePrompt->id)
                ->lockForUpdate()
                ->first();

            if ($selectedProposal === null || $selectedProposal->member_id === $arbiter->id) {
                throw MultiplayerLobbyStateException::proposalIsNotAvailable();
            }

            $selectedProposal->update(['selected_at' => now()]);
            $lockedLobby->update([
                'turn_status' => MultiplayerArbiterTurnStatusEnum::Narrating,
                'decision_deadline_at' => null,
                'decision_remaining_seconds' => null,
                'last_activity_at' => now(),
            ]);

            return $lockedLobby->load(['host', 'members.user', 'game']);
        });

        MultiplayerArbiterStateChanged::dispatch($lobby);

        return $lobby;
    }
}
