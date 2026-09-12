<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyMemberRoleEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Events\MultiplayerLobbyMemberJoined;
use App\Exceptions\Multiplayer\MultiplayerLobbyJoinException;
use App\Models\MultiplayerLobby;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class JoinMultiplayerLobbyAction
{
    public function handle(User $user, MultiplayerLobby $lobby): MultiplayerLobby
    {
        $joined = false;
        $memberRejoined = false;
        $gameResumed = false;
        $lobbyReopened = false;

        $lobby = DB::transaction(function () use ($user, $lobby, &$joined, &$memberRejoined, &$gameResumed, &$lobbyReopened): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());
            $now = now();

            if ($lockedLobby->status === MultiplayerLobbyStatusEnum::Closed) {
                if ($lockedLobby->game_id === null) {
                    $lockedLobby->members()
                        ->whereNotNull('left_at')
                        ->where('user_id', '!=', $user->id)
                        ->delete();
                }

                $lockedLobby->update([
                    'status' => $this->reopenedStatus($lockedLobby),
                    'closed_at' => null,
                    'closed_reason' => null,
                    'closed_status' => null,
                    'last_activity_at' => $now,
                ]);
                $lobbyReopened = true;
            }

            $member = $lockedLobby->members()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if ($member !== null) {
                $wasAway = $member->left_at !== null;

                $member->update([
                    'left_at' => $wasAway ? null : $member->left_at,
                    'last_seen_at' => $now,
                ]);

                if ($wasAway) {
                    $memberRejoined = true;
                    $lockedLobby->update(['last_activity_at' => $now]);
                }

                if (
                    $lockedLobby->status === MultiplayerLobbyStatusEnum::Paused
                    && ! $lockedLobby->members()->whereNotNull('left_at')->exists()
                ) {
                    $lockedLobby->update(['status' => MultiplayerLobbyStatusEnum::Started]);
                    $gameResumed = true;
                }

                return $lockedLobby
                    ->refresh()
                    ->load(['host', 'members.user']);
            }

            if ($lockedLobby->status !== MultiplayerLobbyStatusEnum::Waiting) {
                throw MultiplayerLobbyJoinException::isNotWaiting();
            }

            if ($lockedLobby->members()->whereNull('left_at')->count() >= $lockedLobby->max_players) {
                throw MultiplayerLobbyJoinException::isFull();
            }

            $lockedLobby->members()->create([
                'user_id' => $user->id,
                'role' => $lockedLobby->host_user_id === $user->id
                    ? MultiplayerLobbyMemberRoleEnum::Host
                    : MultiplayerLobbyMemberRoleEnum::Player,
                'joined_at' => $now,
                'last_seen_at' => $now,
            ]);
            $lockedLobby->update(['last_activity_at' => $now]);

            $joined = true;

            return $lockedLobby->load(['host', 'members.user']);
        });

        if ($joined || $memberRejoined || $lobbyReopened) {
            MultiplayerLobbyMemberJoined::dispatch($lobby);
        }

        if ($memberRejoined || $gameResumed) {
            MultiplayerArbiterStateChanged::dispatch($lobby);
        }

        if ($gameResumed) {
            $lobbyId = $lobby->id;

            dispatch(static function () use ($lobbyId): void {
                app(ResumeMultiplayerLobbyAction::class)->handle($lobbyId);
            })->afterResponse();
        }

        return $lobby;
    }

    private function reopenedStatus(MultiplayerLobby $lobby): MultiplayerLobbyStatusEnum
    {
        if ($lobby->game_id === null) {
            return MultiplayerLobbyStatusEnum::Waiting;
        }

        if ($lobby->closed_status === MultiplayerLobbyStatusEnum::Completed->value) {
            return MultiplayerLobbyStatusEnum::Completed;
        }

        return MultiplayerLobbyStatusEnum::Paused;
    }
}
