<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\MultiplayerLobbyProposal;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin MultiplayerLobby
 */
final class MultiplayerArbiterStateResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $this->loadMissing('members.user');

        $currentUserId = (int) ($request->user('user')?->getAuthIdentifier() ?? 0);
        $currentUserMember = $this->members->firstWhere('user_id', $currentUserId);
        $isCurrentUserArbiter = $currentUserMember?->id === $this->current_arbiter_member_id;
        $isProposalCollectionOpen = $this->turn_status === MultiplayerArbiterTurnStatusEnum::CollectingProposals;
        $canRevealAllProposals = ! $isProposalCollectionOpen;

        $proposals = $this->current_source_prompt_id === null
            ? collect()
            : $this->proposals()
                ->where('source_prompt_id', $this->current_source_prompt_id)
                ->with('member.user')
                ->orderBy('created_at')
                ->get();
        $currentSourcePrompt = $this->current_source_prompt_id === null
            ? null
            : Prompt::query()
                ->select(['id', 'source_prompt_id'])
                ->find($this->current_source_prompt_id);
        $lastSelectedProposal = $currentSourcePrompt?->source_prompt_id === null
            ? null
            : $this->proposals()
                ->where('source_prompt_id', $currentSourcePrompt->source_prompt_id)
                ->whereNotNull('selected_at')
                ->latest('selected_at')
                ->first();

        $submittedMemberIds = $proposals
            ->pluck('member_id')
            ->map(static fn (mixed $memberId): string => (string) $memberId)
            ->all();

        return [
            'turn_status' => $this->turn_status?->value,
            'source_prompt_id' => $this->current_source_prompt_id,
            'current_arbiter_member_id' => $this->current_arbiter_member_id,
            'decision_deadline_at' => $this->decision_deadline_at?->toISOString(),
            'decision_remaining_seconds' => $this->decision_remaining_seconds,
            'is_current_user_arbiter' => $isCurrentUserArbiter,
            'awaiting_member_ids' => $this->members
                ->reject(fn (MultiplayerLobbyMember $member): bool => $member->id === $this->current_arbiter_member_id)
                ->reject(fn (MultiplayerLobbyMember $member): bool => in_array($member->id, $submittedMemberIds, true))
                ->pluck('id')
                ->values()
                ->all(),
            'proposals' => $proposals
                ->map(function (MultiplayerLobbyProposal $proposal) use ($canRevealAllProposals, $currentUserId): array {
                    $isOwnProposal = $proposal->member->user_id === $currentUserId;

                    return [
                        'id' => $proposal->id,
                        'member_id' => $proposal->member_id,
                        'text' => $canRevealAllProposals || $isOwnProposal ? $proposal->text : null,
                        'is_selected' => $proposal->selected_at !== null,
                        'selected_at' => $proposal->selected_at?->toISOString(),
                    ];
                })
                ->values()
                ->all(),
            'last_selected_proposal' => $lastSelectedProposal === null
                ? null
                : [
                    'id' => $lastSelectedProposal->id,
                    'member_id' => $lastSelectedProposal->member_id,
                    'text' => $lastSelectedProposal->text,
                    'is_selected' => true,
                    'selected_at' => $lastSelectedProposal->selected_at?->toISOString(),
                ],
        ];
    }
}
