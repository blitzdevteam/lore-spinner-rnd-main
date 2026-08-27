<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\ProcessGameTurnAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
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
                    explicitBranch: null,
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
            if ($responsePrompt->choices !== []) {
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
                $decisionWindowSeconds = (int) $lockedLobby->settings['decision_window_seconds'];
                $attributes = [
                    'turn_status' => MultiplayerArbiterTurnStatusEnum::AwaitingArbiter,
                ];

                if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Started) {
                    $retryDecisionDeadlineAt = now()->addSeconds($decisionWindowSeconds);
                    $attributes['decision_deadline_at'] = $retryDecisionDeadlineAt;
                    $attributes['decision_remaining_seconds'] = null;
                } else {
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
            $nextArbiterMemberId = $this->nextArbiterMemberId(
                $members,
                $lockedLobby->current_arbiter_member_id,
            );

            if ($game?->current_session_complete) {
                $lockedLobby->update([
                    'current_source_prompt_id' => $responsePrompt->id,
                    'current_arbiter_member_id' => $nextArbiterMemberId,
                    'turn_status' => MultiplayerArbiterTurnStatusEnum::SessionComplete,
                    'decision_deadline_at' => null,
                    'decision_remaining_seconds' => null,
                ]);

                return $lockedLobby;
            }

            $lockedLobby->update([
                'current_source_prompt_id' => $responsePrompt->id,
                'current_arbiter_member_id' => $nextArbiterMemberId,
                'turn_status' => MultiplayerArbiterTurnStatusEnum::CollectingProposals,
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
