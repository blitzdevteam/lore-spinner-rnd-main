<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Events\MultiplayerLobbyMemberLeft;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\User;
use App\Support\Multiplayer\MultiplayerLobbyPauseState;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RecordMultiplayerLobbyHeartbeatAction
{
    private const int STALE_AFTER_SECONDS = 12;

    public function handle(User $user, MultiplayerLobby $lobby): void
    {
        $departedLobby = DB::transaction(function () use ($user, $lobby): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobby->id);

            if ($lockedLobby === null) {
                return null;
            }

            $member = $lockedLobby->members()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if (! $member instanceof MultiplayerLobbyMember || $member->left_at !== null) {
                return null;
            }

            $now = now();
            $member->update(['last_seen_at' => $now]);

            return $this->markStaleMembersAsLeft($lockedLobby, $now);
        });

        $this->broadcastDeparture($departedLobby);
    }

    public function detectDepartures(string $lobbyId): bool
    {
        $departedLobby = DB::transaction(function () use ($lobbyId): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobbyId);

            if ($lockedLobby === null) {
                return null;
            }

            return $this->markStaleMembersAsLeft($lockedLobby, now());
        });

        $this->broadcastDeparture($departedLobby);

        return $departedLobby !== null;
    }

    private function markStaleMembersAsLeft(MultiplayerLobby $lobby, Carbon $now): ?MultiplayerLobby
    {
        if (! in_array($lobby->status, [
            MultiplayerLobbyStatusEnum::Started,
            MultiplayerLobbyStatusEnum::Paused,
        ], true)) {
            return null;
        }

        $staleMembers = $lobby->members()
            ->whereNull('left_at')
            ->where(function ($query) use ($now): void {
                $query
                    ->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<=', $now->copy()->subSeconds(self::STALE_AFTER_SECONDS));
            })
            ->lockForUpdate()
            ->get();

        if ($staleMembers->isEmpty()) {
            return null;
        }

        $this->markMembersAsLeft($staleMembers, $now);
        MultiplayerLobbyPauseState::apply($lobby, $now);

        return $lobby->load(['host', 'members.user']);
    }

    /**
     * @param  Collection<int, MultiplayerLobbyMember>  $members
     */
    private function markMembersAsLeft(Collection $members, Carbon $leftAt): void
    {
        foreach ($members as $member) {
            $member->update(['left_at' => $leftAt]);
        }
    }

    private function broadcastDeparture(?MultiplayerLobby $lobby): void
    {
        if ($lobby === null) {
            return;
        }

        MultiplayerLobbyMemberLeft::dispatch($lobby);
        MultiplayerArbiterStateChanged::dispatch($lobby);
    }
}
