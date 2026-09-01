<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\ProcessGameTurnAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterNarrationStreamed;
use App\Events\MultiplayerArbiterStateChanged;
use App\Jobs\Multiplayer\ResolveMultiplayerLobbyDecisionDeadlineJob;
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

        $explicitBranch = $this->explicitBranch($lobby, $sourcePrompt, $proposal);

        try {
            if ($sourcePrompt->prompt === null) {
                MultiplayerArbiterNarrationStreamed::dispatch(
                    (string) $lobby->id,
                    (string) $sourcePrompt->id,
                    MultiplayerArbiterNarrationStreamed::PHASE_READY,
                    null,
                    $proposal->text,
                );

                $this->processGameTurn->handleStreaming(
                    gameId: $lobby->game_id,
                    sourcePromptId: $sourcePrompt->id,
                    storedPromptValue: $proposal->text,
                    playerAction: $proposal->text,
                    explicitBranch: $explicitBranch,
                    userId: $lobby->host_user_id,
                    onResponseDelta: static function (string $delta) use ($lobby, $sourcePrompt): void {
                        MultiplayerArbiterNarrationStreamed::dispatch(
                            (string) $lobby->id,
                            (string) $sourcePrompt->id,
                            MultiplayerArbiterNarrationStreamed::PHASE_DELTA,
                            $delta,
                        );
                    },
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

        if ($responsePrompt !== null) {
            if ($lobby->mode === MultiplayerLobbyModeEnum::Arbiter && $responsePrompt->choices !== []) {
                $responsePrompt->forceFill(['choices' => []])->save();
            }

            MultiplayerArbiterNarrationStreamed::dispatch(
                (string) $lobby->id,
                (string) $sourcePrompt->id,
                MultiplayerArbiterNarrationStreamed::PHASE_DONE,
                null,
                $proposal->text,
                $responsePrompt,
            );
        } else {
            MultiplayerArbiterNarrationStreamed::dispatch(
                (string) $lobby->id,
                (string) $sourcePrompt->id,
                MultiplayerArbiterNarrationStreamed::PHASE_ERROR,
                null,
                null,
                null,
                'Narration hiccuped — please retry this turn.',
            );
        }

        $retryDecisionDeadlineAt = null;

        $updatedLobby = DB::transaction(function () use ($lobby, $proposal, $sourcePrompt, $responsePrompt, &$retryDecisionDeadlineAt): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobby->id);

            if (
                $lockedLobby === null
                || ! in_array($lockedLobby->status, [
                    MultiplayerLobbyStatusEnum::Started,
                    MultiplayerLobbyStatusEnum::Paused,
                ], true)
                || $lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::Narrating
                || $lockedLobby->current_source_prompt_id !== $sourcePrompt->id
            ) {
                return null;
            }

            if ($responsePrompt === null) {
                MultiplayerLobbyProposal::query()
                    ->whereKey($proposal->id)
                    ->update(['selected_at' => null]);
                $attributes = [
                    'turn_status' => $lockedLobby->mode === MultiplayerLobbyModeEnum::Roulette
                        ? MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice
                        : MultiplayerArbiterTurnStatusEnum::AwaitingArbiter,
                ];

                if ($lockedLobby->mode === MultiplayerLobbyModeEnum::Roulette) {
                    $attributes['decision_deadline_at'] = null;
                    $attributes['decision_remaining_seconds'] = null;
                } elseif ($lockedLobby->status === MultiplayerLobbyStatusEnum::Started) {
                    $decisionWindowSeconds = (int) $lockedLobby->settings['decision_window_seconds'];
                    $retryDecisionDeadlineAt = now()->addSeconds($decisionWindowSeconds);
                    $attributes['decision_deadline_at'] = $retryDecisionDeadlineAt;
                    $attributes['decision_remaining_seconds'] = null;
                } else {
                    $decisionWindowSeconds = (int) $lockedLobby->settings['decision_window_seconds'];
                    $attributes['decision_deadline_at'] = null;
                    $attributes['decision_remaining_seconds'] = $decisionWindowSeconds;
                }

                $lockedLobby->update($attributes);

                return $lockedLobby;
            }

            $game = Game::query()->find($lockedLobby->game_id);

            $members = $lockedLobby->members()
                ->orderBy('joined_at')
                ->orderBy('id')
                ->get();
            $nextTurnMemberId = $this->nextTurnMemberId(
                $members,
                $lockedLobby->current_arbiter_member_id,
            );

            if ($game?->current_session_complete) {
                $lockedLobby->update([
                    'current_source_prompt_id' => $responsePrompt->id,
                    'current_arbiter_member_id' => $nextTurnMemberId,
                    'turn_status' => MultiplayerArbiterTurnStatusEnum::SessionComplete,
                    'decision_deadline_at' => null,
                    'decision_remaining_seconds' => null,
                ]);

                return $lockedLobby;
            }

            $lockedLobby->update([
                'current_source_prompt_id' => $responsePrompt->id,
                'current_arbiter_member_id' => $nextTurnMemberId,
                'turn_status' => $lockedLobby->mode === MultiplayerLobbyModeEnum::Roulette
                    ? MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice
                    : MultiplayerArbiterTurnStatusEnum::CollectingProposals,
                'decision_deadline_at' => null,
                'decision_remaining_seconds' => null,
            ]);

            return $lockedLobby;
        });

        if ($updatedLobby !== null) {
            MultiplayerArbiterStateChanged::dispatch($updatedLobby);
        }

        if ($retryDecisionDeadlineAt !== null) {
            ResolveMultiplayerLobbyDecisionDeadlineJob::dispatch(
                $lobby->id,
                $sourcePrompt->id,
                $retryDecisionDeadlineAt->getTimestamp(),
            )->delay($retryDecisionDeadlineAt->copy()->addSeconds(3));
        }
    }

    /**
     * @param  Collection<int, MultiplayerLobbyMember>  $members
     */
    private function nextTurnMemberId(Collection $members, ?string $currentTurnMemberId): ?string
    {
        if ($members->isEmpty()) {
            return null;
        }

        $currentIndex = $members->search(
            static fn (MultiplayerLobbyMember $member): bool => $member->id === $currentTurnMemberId,
        );

        if ($currentIndex === false) {
            return $members->first()?->id;
        }

        return $members->get(($currentIndex + 1) % $members->count())?->id;
    }

    /**
     * @return array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null
     */
    private function explicitBranch(
        MultiplayerLobby $lobby,
        Prompt $sourcePrompt,
        MultiplayerLobbyProposal $proposal,
    ): ?array {
        if ($lobby->mode !== MultiplayerLobbyModeEnum::Roulette || $proposal->choice_index === null) {
            return null;
        }

        $choice = $sourcePrompt->choices[$proposal->choice_index] ?? null;

        if (! is_array($choice)) {
            return null;
        }

        $choiceId = $choice['canonical_choice_id'] ?? null;
        $pathLabel = $choice['canonical_path_label'] ?? null;

        if (! is_string($choiceId) || $choiceId === '' || ! is_string($pathLabel)) {
            return null;
        }

        $pathLabel = mb_strtoupper($pathLabel);

        if (! in_array($pathLabel, ['A', 'B', 'C'], true)) {
            return null;
        }

        return [
            'defining_choice_id' => $choiceId,
            'defining_path_label' => $pathLabel,
            'defining_choice_line' => null,
        ];
    }
}
