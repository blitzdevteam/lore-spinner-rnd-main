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
use App\Services\ChaosEngineService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessMultiplayerLobbyTurnAction
{
    public function __construct(
        private readonly ProcessGameTurnAction $processGameTurn,
        private readonly ChaosEngineService $engine,
    ) {}

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

        $decisionDeadlineAt = null;
        $decisionSourcePromptId = null;
        $decisionDeadlineGraceSeconds = 3;

        $updatedLobby = DB::transaction(function () use (
            $lobby,
            $proposal,
            $sourcePrompt,
            $responsePrompt,
            &$decisionDeadlineAt,
            &$decisionSourcePromptId,
            &$decisionDeadlineGraceSeconds,
        ): ?MultiplayerLobby {
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
                $decisionWindowSeconds = $lockedLobby->decisionWindowSeconds();

                if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Started) {
                    $decisionDeadlineAt = now()->addSeconds($decisionWindowSeconds);
                    $decisionSourcePromptId = (string) $sourcePrompt->id;
                    $decisionDeadlineGraceSeconds = $lockedLobby->mode === MultiplayerLobbyModeEnum::Roulette ? 1 : 3;
                    $attributes['decision_deadline_at'] = $decisionDeadlineAt;
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

            $isAwaitingRouletteChoice = $lockedLobby->mode === MultiplayerLobbyModeEnum::Roulette;
            $nextDecisionDeadlineAt = null;
            $nextDecisionRemainingSeconds = null;

            if ($isAwaitingRouletteChoice) {
                if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Started) {
                    $nextDecisionDeadlineAt = now()->addSeconds($lockedLobby->decisionWindowSeconds());
                    $decisionDeadlineAt = $nextDecisionDeadlineAt;
                    $decisionSourcePromptId = (string) $responsePrompt->id;
                    $decisionDeadlineGraceSeconds = 1;
                } else {
                    $nextDecisionRemainingSeconds = $lockedLobby->decisionWindowSeconds();
                }
            }

            $lockedLobby->update([
                'current_source_prompt_id' => $responsePrompt->id,
                'current_arbiter_member_id' => $nextTurnMemberId,
                'turn_status' => $isAwaitingRouletteChoice
                    ? MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice
                    : MultiplayerArbiterTurnStatusEnum::CollectingProposals,
                'decision_deadline_at' => $nextDecisionDeadlineAt,
                'decision_remaining_seconds' => $nextDecisionRemainingSeconds,
            ]);

            return $lockedLobby;
        });

        if ($updatedLobby !== null) {
            MultiplayerArbiterStateChanged::dispatch($updatedLobby);
        }

        if ($updatedLobby !== null && $decisionDeadlineAt !== null && $decisionSourcePromptId !== null) {
            ResolveMultiplayerLobbyDecisionDeadlineJob::dispatch(
                $updatedLobby->id,
                $decisionSourcePromptId,
                $decisionDeadlineAt->getTimestamp(),
            )->delay($decisionDeadlineAt->copy()->addSeconds($decisionDeadlineGraceSeconds));
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

        $game = Game::query()
            ->with(['story.adaptation.sessionAdaptations', 'currentPlayableMoment'])
            ->find($lobby->game_id);
        $momentChoiceIds = (array) data_get($game?->currentPlayableMoment?->scene_card, 'choice_ids', []);

        if ($game === null || ! in_array($choiceId, $momentChoiceIds, true)) {
            return null;
        }

        $choiceSession = $this->engine->extractSessionNumber($choiceId);
        $session = $game->story?->adaptation?->sessionAdaptations?->firstWhere('session_number', $choiceSession);
        $choiceDesign = (array) ($session?->session_choice_design ?? []);
        $branchingChoice = collect((array) ($choiceDesign['branching_choices'] ?? []))
            ->firstWhere('choice_id', $choiceId);

        if (! is_array($branchingChoice)) {
            return null;
        }

        $option = collect((array) ($branchingChoice['options'] ?? []))
            ->first(fn (mixed $option): bool => mb_strtoupper((string) ($option['label'] ?? '')) === $pathLabel);

        if (! is_array($option)) {
            return null;
        }

        return [
            'defining_choice_id' => $choiceId,
            'defining_path_label' => $pathLabel,
            'defining_choice_line' => ($option['defining_line'] ?? '') !== ''
                ? (string) $option['defining_line']
                : null,
        ];
    }
}
