<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerLobbyClosed;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Support\Multiplayer\MultiplayerLobbyPauseState;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CloseInactiveMultiplayerLobbiesAction
{
    private const int EMPTY_LOBBY_CLOSE_AFTER_HOURS = 6;

    private const int INACTIVE_LOBBY_CLOSE_AFTER_HOURS = 12;

    private const string EMPTY_CLOSE_REASON = 'empty';

    private const string INACTIVE_CLOSE_REASON = 'inactive';

    private const string EMPTY_CLOSE_MESSAGE = 'This lobby was closed after everyone left. Rejoin with the room code to open it again.';

    private const string INACTIVE_CLOSE_MESSAGE = 'This lobby has been inactive for 12 hours and is now closed. You have been removed from the room. Rejoin with the room code to open it again.';

    public function handle(): int
    {
        $now = now();
        $emptyLobbyCutoff = $now->copy()->subHours(self::EMPTY_LOBBY_CLOSE_AFTER_HOURS);

        $lobbyIds = MultiplayerLobby::query()
            ->whereIn('status', [
                MultiplayerLobbyStatusEnum::Waiting,
                MultiplayerLobbyStatusEnum::Started,
                MultiplayerLobbyStatusEnum::Paused,
                MultiplayerLobbyStatusEnum::Completed,
            ])
            ->where(function ($query) use ($emptyLobbyCutoff): void {
                $query
                    ->where('last_activity_at', '<=', $emptyLobbyCutoff)
                    ->orWhere(function ($fallbackQuery) use ($emptyLobbyCutoff): void {
                        $fallbackQuery
                            ->whereNull('last_activity_at')
                            ->where('updated_at', '<=', $emptyLobbyCutoff);
                    });
            })
            ->pluck('id');
        $closedLobbies = 0;

        foreach ($lobbyIds as $lobbyId) {
            $closure = $this->close((string) $lobbyId, $now);

            if ($closure === null) {
                continue;
            }

            MultiplayerLobbyClosed::dispatch(
                $closure['lobby_id'],
                $closure['member_user_ids'],
                $closure['message'],
            );
            $closedLobbies++;
        }

        return $closedLobbies;
    }

    /**
     * @return array{lobby_id: string, member_user_ids: array<int, int>, message: string}|null
     */
    private function close(string $lobbyId, Carbon $now): ?array
    {
        return DB::transaction(function () use ($lobbyId, $now): ?array {
            $lobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobbyId);

            if (
                $lobby === null
                || ! in_array($lobby->status, [
                    MultiplayerLobbyStatusEnum::Waiting,
                    MultiplayerLobbyStatusEnum::Started,
                    MultiplayerLobbyStatusEnum::Paused,
                    MultiplayerLobbyStatusEnum::Completed,
                ], true)
            ) {
                return null;
            }

            $activeMembers = $lobby->members()
                ->whereNull('left_at')
                ->lockForUpdate()
                ->get();
            $statusBeforeClosing = $lobby->status;
            $lastActivityAt = $lobby->last_activity_at ?? $lobby->updated_at ?? $now;
            $closeReason = null;

            /** @var array<int, int> $memberUserIds */
            $memberUserIds = $activeMembers
                ->pluck('user_id')
                ->map(static fn (mixed $memberUserId): int => (int) $memberUserId)
                ->all();

            if ($activeMembers->isEmpty()) {
                if ($lastActivityAt->isAfter($now->copy()->subHours(self::EMPTY_LOBBY_CLOSE_AFTER_HOURS))) {
                    return null;
                }

                $closeReason = self::EMPTY_CLOSE_REASON;
            } elseif ($this->allMembersHaveBeenAbsentForSixHours($activeMembers, $now)) {
                $this->markMembersAsLeft($activeMembers, $now);
                $closeReason = self::EMPTY_CLOSE_REASON;
            } elseif ($lastActivityAt->isAfter($now->copy()->subHours(self::INACTIVE_LOBBY_CLOSE_AFTER_HOURS))) {
                return null;
            } else {
                $closeReason = self::INACTIVE_CLOSE_REASON;
            }

            if ($lobby->status === MultiplayerLobbyStatusEnum::Started) {
                MultiplayerLobbyPauseState::apply($lobby, $now);
            }

            if ($closeReason === self::INACTIVE_CLOSE_REASON) {
                $this->markMembersAsLeft($activeMembers, $now);
            }

            $lobby->update([
                'status' => MultiplayerLobbyStatusEnum::Closed,
                'closed_at' => $now,
                'closed_reason' => $closeReason,
                'closed_status' => $statusBeforeClosing->value,
                'last_activity_at' => $now,
            ]);

            return [
                'lobby_id' => $lobby->id,
                'member_user_ids' => $memberUserIds,
                'message' => $closeReason === self::INACTIVE_CLOSE_REASON
                    ? self::INACTIVE_CLOSE_MESSAGE
                    : self::EMPTY_CLOSE_MESSAGE,
            ];
        });
    }

    /**
     * @param  Collection<int, MultiplayerLobbyMember>  $members
     */
    private function allMembersHaveBeenAbsentForSixHours(Collection $members, Carbon $now): bool
    {
        $absenceCutoff = $now->copy()->subHours(self::EMPTY_LOBBY_CLOSE_AFTER_HOURS);

        return $members->every(static fn (MultiplayerLobbyMember $member): bool => $member->last_seen_at === null
            || ! $member->last_seen_at->isAfter($absenceCutoff));
    }

    /**
     * @param  Collection<int, MultiplayerLobbyMember>  $members
     */
    private function markMembersAsLeft(Collection $members, Carbon $leftAt): void
    {
        foreach ($members as $member) {
            $member->update([
                'left_at' => $leftAt,
                'last_seen_at' => $leftAt,
                'is_ready' => false,
            ]);
        }
    }
}
