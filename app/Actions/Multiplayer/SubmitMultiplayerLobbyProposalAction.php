<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\MultiplayerLobbyProposal;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SubmitMultiplayerLobbyProposalAction
{
    public function handle(User $user, MultiplayerLobby $lobby, string $text): MultiplayerLobby
    {
        $lobby = DB::transaction(function () use ($user, $lobby, $text): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());

            if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Paused) {
                throw MultiplayerLobbyStateException::isPaused();
            }

            if ($lockedLobby->status !== MultiplayerLobbyStatusEnum::Started) {
                throw MultiplayerLobbyStateException::isNotStarted();
            }

            if ($lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::CollectingProposals) {
                throw MultiplayerLobbyStateException::isNotCollectingProposals();
            }

            $members = $lockedLobby->members()
                ->orderBy('joined_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $member = $members->firstWhere('user_id', $user->id);

            if (! $member instanceof MultiplayerLobbyMember) {
                throw MultiplayerLobbyStateException::isNotStarted();
            }

            if ($member->id === $lockedLobby->current_arbiter_member_id) {
                throw MultiplayerLobbyStateException::onlyNonArbitersCanPropose();
            }

            $sourcePrompt = Prompt::query()
                ->whereKey($lockedLobby->current_source_prompt_id)
                ->where('game_id', $lockedLobby->game_id)
                ->lockForUpdate()
                ->first();

            if ($sourcePrompt === null || $sourcePrompt->prompt !== null) {
                throw MultiplayerLobbyStateException::isNotCollectingProposals();
            }

            $existingProposal = MultiplayerLobbyProposal::query()
                ->where('source_prompt_id', $sourcePrompt->id)
                ->where('member_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($existingProposal !== null) {
                throw MultiplayerLobbyStateException::proposalAlreadySubmitted();
            }

            $lockedLobby->proposals()->create([
                'source_prompt_id' => $sourcePrompt->id,
                'member_id' => $member->id,
                'text' => $text,
            ]);

            $requiredMemberIds = $members
                ->reject(static fn (MultiplayerLobbyMember $candidate): bool => $candidate->id === $lockedLobby->current_arbiter_member_id)
                ->pluck('id')
                ->all();
            $submittedMemberIds = MultiplayerLobbyProposal::query()
                ->where('lobby_id', $lockedLobby->id)
                ->where('source_prompt_id', $sourcePrompt->id)
                ->lockForUpdate()
                ->pluck('member_id')
                ->all();

            if (array_diff($requiredMemberIds, $submittedMemberIds) === []) {
                $lockedLobby->update([
                    'turn_status' => MultiplayerArbiterTurnStatusEnum::AwaitingArbiter,
                ]);
            }

            return $lockedLobby->load(['host', 'members.user', 'game']);
        });

        MultiplayerArbiterStateChanged::dispatch($lobby);

        return $lobby;
    }
}
