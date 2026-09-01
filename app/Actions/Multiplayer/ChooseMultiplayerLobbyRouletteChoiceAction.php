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

final class ChooseMultiplayerLobbyRouletteChoiceAction
{
    public function handle(User $user, MultiplayerLobby $lobby, ?int $choiceIndex, ?string $customChoice = null): MultiplayerLobby
    {
        $lobby = DB::transaction(function () use ($user, $lobby, $choiceIndex, $customChoice): MultiplayerLobby {
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
                $lockedLobby->mode !== MultiplayerLobbyModeEnum::Roulette
                || $lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice
            ) {
                throw MultiplayerLobbyStateException::rouletteChoicesAreNotReady();
            }

            $members = $lockedLobby->members()
                ->orderBy('joined_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $currentChooser = $members->firstWhere('id', $lockedLobby->current_arbiter_member_id);

            if (! $currentChooser instanceof MultiplayerLobbyMember || $currentChooser->user_id !== $user->id) {
                throw MultiplayerLobbyStateException::onlyCurrentRoulettePlayerCanChoose();
            }

            $sourcePrompt = Prompt::query()
                ->whereKey($lockedLobby->current_source_prompt_id)
                ->where('game_id', $lockedLobby->game_id)
                ->lockForUpdate()
                ->first();

            if ($sourcePrompt === null || $sourcePrompt->prompt !== null) {
                throw MultiplayerLobbyStateException::rouletteChoicesAreNotReady();
            }

            if ($choiceIndex === null && ! $lockedLobby->settings['custom_input_enabled']) {
                throw MultiplayerLobbyStateException::rouletteCustomInputIsDisabled();
            }

            $choice = $choiceIndex === null
                ? mb_trim((string) $customChoice)
                : $this->choiceAt($sourcePrompt, $choiceIndex);

            if ($choice === null || $choice === '') {
                throw MultiplayerLobbyStateException::rouletteChoiceIsNotAvailable();
            }

            $selection = MultiplayerLobbyProposal::query()
                ->where('lobby_id', $lockedLobby->id)
                ->where('source_prompt_id', $sourcePrompt->id)
                ->where('member_id', $currentChooser->id)
                ->lockForUpdate()
                ->first();

            if ($selection === null) {
                $selection = $lockedLobby->proposals()->create([
                    'source_prompt_id' => $sourcePrompt->id,
                    'member_id' => $currentChooser->id,
                    'text' => $choice,
                    'choice_index' => $choiceIndex,
                    'selected_at' => now(),
                ]);
            } else {
                $selection->update([
                    'text' => $choice,
                    'choice_index' => $choiceIndex,
                    'selected_at' => now(),
                ]);
            }

            $lockedLobby->update([
                'turn_status' => MultiplayerArbiterTurnStatusEnum::Narrating,
                'decision_deadline_at' => null,
                'decision_remaining_seconds' => null,
            ]);

            return $lockedLobby->load(['host', 'members.user', 'game']);
        });

        MultiplayerArbiterStateChanged::dispatch($lobby);

        return $lobby;
    }

    private function choiceAt(Prompt $sourcePrompt, int $choiceIndex): ?string
    {
        $choice = $sourcePrompt->choices[$choiceIndex] ?? null;

        if (is_string($choice)) {
            $text = mb_trim($choice);

            return $text === '' ? null : $text;
        }

        if (! is_array($choice) || ! is_string($choice['text'] ?? null)) {
            return null;
        }

        $text = mb_trim($choice['text']);

        return $text === '' ? null : $text;
    }
}
