<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
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
        $isCurrentUserChooser = $isCurrentUserArbiter;
        $isProposalCollectionOpen = $this->turn_status === MultiplayerArbiterTurnStatusEnum::CollectingProposals;
        $canRevealAllProposals = $this->mode !== MultiplayerLobbyModeEnum::Arbiter || ! $isProposalCollectionOpen;

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
                ->select(['id', 'source_prompt_id', 'choices'])
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
        $awaitingMemberIds = $this->mode === MultiplayerLobbyModeEnum::Arbiter
            ? $this->members
                ->reject(fn (MultiplayerLobbyMember $member): bool => $member->id === $this->current_arbiter_member_id)
                ->reject(fn (MultiplayerLobbyMember $member): bool => in_array($member->id, $submittedMemberIds, true))
                ->pluck('id')
                ->values()
                ->all()
            : [];
        $generatedChoices = $this->mode === MultiplayerLobbyModeEnum::Roulette
            ? collect((array) ($currentSourcePrompt?->choices ?? []))
                ->map(static fn (mixed $choice, mixed $index): array => is_array($choice)
                    ? [
                        'index' => (int) $index,
                        'text' => (string) ($choice['text'] ?? ''),
                        'canonical_choice_id' => $choice['canonical_choice_id'] ?? null,
                        'canonical_path_label' => $choice['canonical_path_label'] ?? null,
                    ]
                    : [
                        'index' => (int) $index,
                        'text' => (string) $choice,
                        'canonical_choice_id' => null,
                        'canonical_path_label' => null,
                    ])
                ->filter(static fn (array $choice): bool => $choice['text'] !== '')
                ->values()
                ->all()
            : [];
        $turnsPlayedByMemberId = $this->proposals()
            ->whereNotNull('selected_at')
            ->selectRaw('member_id, count(*) as turns_played')
            ->groupBy('member_id')
            ->pluck('turns_played', 'member_id')
            ->map(static fn (mixed $turnsPlayed): int => (int) $turnsPlayed)
            ->all();

        return [
            'mode' => $this->mode->value,
            'turn_status' => $this->turn_status?->value,
            'source_prompt_id' => $this->current_source_prompt_id,
            'current_arbiter_member_id' => $this->current_arbiter_member_id,
            'current_chooser_member_id' => $this->current_arbiter_member_id,
            'decision_deadline_at' => $this->decision_deadline_at?->toISOString(),
            'decision_remaining_seconds' => $this->decision_remaining_seconds,
            'is_current_user_arbiter' => $isCurrentUserArbiter,
            'is_current_user_chooser' => $isCurrentUserChooser,
            'awaiting_member_ids' => $awaitingMemberIds,
            'generated_choices' => $generatedChoices,
            'turns_played_by_member_id' => $turnsPlayedByMemberId,
            'proposals' => $proposals
                ->map(function (MultiplayerLobbyProposal $proposal) use ($canRevealAllProposals, $currentUserId): array {
                    $isOwnProposal = $proposal->member->user_id === $currentUserId;

                    return [
                        'id' => $proposal->id,
                        'member_id' => $proposal->member_id,
                        'text' => $canRevealAllProposals || $isOwnProposal ? $proposal->text : null,
                        'choice_index' => $proposal->choice_index,
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
                    'choice_index' => $lastSelectedProposal->choice_index,
                    'is_selected' => true,
                    'selected_at' => $lastSelectedProposal->selected_at?->toISOString(),
                ],
        ];
    }
}
