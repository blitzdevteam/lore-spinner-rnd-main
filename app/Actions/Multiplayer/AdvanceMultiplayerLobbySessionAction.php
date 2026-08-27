<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Models\Game;
use App\Models\GameCompletion;
use App\Models\GameSessionCompletion;
use App\Models\MultiplayerLobby;
use App\Models\User;
use App\Models\UserActivityDay;
use Illuminate\Support\Facades\DB;

final class AdvanceMultiplayerLobbySessionAction
{
    public function handle(User $user, MultiplayerLobby $lobby): MultiplayerLobby
    {
        $shouldRecordActivity = false;

        $lobby = DB::transaction(function () use ($user, $lobby, &$shouldRecordActivity): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());

            if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Completed) {
                throw MultiplayerLobbyStateException::storyIsComplete();
            }

            if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Paused) {
                throw MultiplayerLobbyStateException::isPaused();
            }

            if ($lockedLobby->status !== MultiplayerLobbyStatusEnum::Started) {
                throw MultiplayerLobbyStateException::isNotStarted();
            }

            if ($lockedLobby->host_user_id !== $user->id) {
                throw MultiplayerLobbyStateException::onlyHostCanAdvanceSession();
            }

            if ($lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::SessionComplete) {
                throw MultiplayerLobbyStateException::sessionIsNotComplete();
            }

            $game = Game::query()
                ->lockForUpdate()
                ->find($lockedLobby->game_id);

            if ($game === null || ! $game->current_session_complete) {
                throw MultiplayerLobbyStateException::sessionIsNotComplete();
            }

            $story = $game->story()
                ->with(['adaptation', 'adaptation.sessionAdaptations'])
                ->firstOrFail();
            $currentSessionNumber = (int) $game->current_session_number;
            $nextSessionNumber = $currentSessionNumber + 1;
            $totalSessions = (int) ($story->adaptation?->sessionAdaptations?->count() ?? 0);

            if ($nextSessionNumber > $totalSessions) {
                $now = now();

                GameSessionCompletion::query()
                    ->where('game_id', $game->id)
                    ->where('story_cycle_number', $game->current_story_cycle_number)
                    ->where('session_number', $currentSessionNumber)
                    ->update(['completed_at' => $now]);

                $game->update(['completed_at' => $now]);

                GameCompletion::query()->updateOrCreate(
                    [
                        'game_id' => $game->id,
                        'story_cycle_number' => $game->current_story_cycle_number,
                    ],
                    [
                        'user_id' => $game->user_id,
                        'story_id' => $game->story_id,
                        'completed_at' => $now,
                    ],
                );

                $lockedLobby->update([
                    'status' => MultiplayerLobbyStatusEnum::Completed,
                    'turn_status' => MultiplayerArbiterTurnStatusEnum::SessionComplete,
                ]);
                $shouldRecordActivity = true;

                return $lockedLobby->load(['host', 'members.user', 'game']);
            }

            $nextSession = $story->adaptation?->sessionAdaptations
                ?->firstWhere('session_number', $nextSessionNumber);

            if ($story->adaptation?->boiler_enabled && $nextSession === null) {
                throw MultiplayerLobbyStateException::nextSessionIsNotAvailable();
            }

            $lockedLobby->update([
                'current_source_prompt_id' => null,
                'turn_status' => MultiplayerArbiterTurnStatusEnum::Opening,
            ]);

            return $lockedLobby->load(['host', 'members.user', 'game']);
        });

        if ($shouldRecordActivity) {
            UserActivityDay::record($user->id);
        }

        MultiplayerArbiterStateChanged::dispatch($lobby);

        return $lobby;
    }
}
