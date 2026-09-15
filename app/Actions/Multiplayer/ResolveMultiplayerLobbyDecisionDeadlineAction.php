<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyProposal;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;

final class ResolveMultiplayerLobbyDecisionDeadlineAction
{
    /**
     * @return array{lobby: MultiplayerLobby, proposal_id: string}|null
     */
    public function handle(string $lobbyId, string $sourcePromptId, int $deadlineTimestamp): ?array
    {
        $resolvedSelection = DB::transaction(function () use ($lobbyId, $sourcePromptId, $deadlineTimestamp): ?array {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobbyId);

            $isArbiterDecision = $lockedLobby?->mode === MultiplayerLobbyModeEnum::Arbiter
                && in_array($lockedLobby->turn_status, [
                    MultiplayerArbiterTurnStatusEnum::CollectingProposals,
                    MultiplayerArbiterTurnStatusEnum::AwaitingArbiter,
                ], true);
            $isRouletteDecision = $lockedLobby?->mode === MultiplayerLobbyModeEnum::Roulette
                && $lockedLobby->turn_status === MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice;

            if (
                $lockedLobby === null
                || $lockedLobby->status !== MultiplayerLobbyStatusEnum::Started
                || (! $isArbiterDecision && ! $isRouletteDecision)
                || $lockedLobby->current_source_prompt_id !== $sourcePromptId
                || $lockedLobby->current_arbiter_member_id === null
                || $lockedLobby->decision_deadline_at === null
                || $lockedLobby->decision_deadline_at->getTimestamp() !== $deadlineTimestamp
                || $lockedLobby->decision_deadline_at->isAfter(now())
            ) {
                return null;
            }

            $sourcePrompt = Prompt::query()
                ->whereKey($sourcePromptId)
                ->where('game_id', $lockedLobby->game_id)
                ->lockForUpdate()
                ->first();

            if ($sourcePrompt === null || $sourcePrompt->prompt !== null) {
                return null;
            }

            $proposal = $isRouletteDecision
                ? $this->selectRandomRouletteChoice($lockedLobby, $sourcePrompt)
                : MultiplayerLobbyProposal::query()
                    ->where('lobby_id', $lockedLobby->id)
                    ->where('source_prompt_id', $sourcePrompt->id)
                    ->where('member_id', '!=', $lockedLobby->current_arbiter_member_id)
                    ->whereNull('selected_at')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

            if ($proposal === null) {
                return null;
            }

            if ($isArbiterDecision) {
                $proposal->update(['selected_at' => now()]);
            }

            $lockedLobby->update([
                'turn_status' => MultiplayerArbiterTurnStatusEnum::Narrating,
                'decision_deadline_at' => null,
                'decision_remaining_seconds' => null,
            ]);

            return [
                'lobby' => $lockedLobby->load(['host', 'members.user', 'game']),
                'proposal_id' => (string) $proposal->id,
            ];
        });

        if ($resolvedSelection !== null) {
            MultiplayerArbiterStateChanged::dispatch($resolvedSelection['lobby']);
        }

        return $resolvedSelection;
    }

    private function selectRandomRouletteChoice(MultiplayerLobby $lobby, Prompt $sourcePrompt): ?MultiplayerLobbyProposal
    {
        $choice = collect((array) $sourcePrompt->choices)
            ->map(function (mixed $choice, mixed $index): ?array {
                $text = match (true) {
                    is_string($choice) => mb_trim($choice),
                    is_array($choice) && is_string($choice['text'] ?? null) => mb_trim($choice['text']),
                    default => '',
                };

                return $text === ''
                    ? null
                    : [
                        'choice_index' => (int) $index,
                        'text' => $text,
                    ];
            })
            ->filter()
            ->values();

        if ($choice->isEmpty()) {
            return null;
        }

        /** @var array{choice_index: int, text: string} $randomChoice */
        $randomChoice = $choice->random();
        $selection = MultiplayerLobbyProposal::query()
            ->where('lobby_id', $lobby->id)
            ->where('source_prompt_id', $sourcePrompt->id)
            ->where('member_id', $lobby->current_arbiter_member_id)
            ->lockForUpdate()
            ->first();

        if ($selection === null) {
            return $lobby->proposals()->create([
                'source_prompt_id' => $sourcePrompt->id,
                'member_id' => $lobby->current_arbiter_member_id,
                'text' => $randomChoice['text'],
                'choice_index' => $randomChoice['choice_index'],
                'selected_at' => now(),
            ]);
        }

        $selection->update([
            'text' => $randomChoice['text'],
            'choice_index' => $randomChoice['choice_index'],
            'selected_at' => now(),
        ]);

        return $selection;
    }
}
