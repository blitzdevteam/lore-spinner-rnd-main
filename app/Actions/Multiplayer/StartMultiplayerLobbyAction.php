<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\CreateGameAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerLobbyStarted;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StartMultiplayerLobbyAction
{
    public function __construct(private readonly CreateGameAction $createGame) {}

    public function handle(User $host, MultiplayerLobby $lobby): MultiplayerLobby
    {
        $lobby = DB::transaction(function () use ($host, $lobby): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());

            if ($lockedLobby->host_user_id !== $host->id) {
                throw MultiplayerLobbyStateException::onlyHostCanStart();
            }

            if ($lockedLobby->status !== MultiplayerLobbyStatusEnum::Waiting) {
                throw MultiplayerLobbyStateException::isNotWaiting();
            }

            $members = $lockedLobby->members()
                ->lockForUpdate()
                ->get();

            if ($members->count() < 3) {
                throw MultiplayerLobbyStateException::needsMorePlayers();
            }

            if ($members->contains(static fn (MultiplayerLobbyMember $member): bool => ! $member->is_ready)) {
                throw MultiplayerLobbyStateException::playersAreNotReady();
            }

            $orderedMembers = $members
                ->sortBy(static fn (MultiplayerLobbyMember $member): string => $member->joined_at->format('Y-m-d H:i:s.u').$member->id)
                ->values();
            $initialArbiter = $orderedMembers
                ->first(static fn (MultiplayerLobbyMember $member): bool => $member->user_id !== $host->id)
                ?? $orderedMembers->first();

            $game = $this->createGame->handle(
                $host,
                $lockedLobby->story()->firstOrFail(),
            );

            $startedAt = now();

            $lockedLobby->members()->update(['last_seen_at' => $startedAt]);

            $lockedLobby->update([
                'status' => MultiplayerLobbyStatusEnum::Started,
                'game_id' => $game->id,
                'current_arbiter_member_id' => $initialArbiter?->id,
                'turn_status' => MultiplayerArbiterTurnStatusEnum::Opening,
                'decision_deadline_at' => null,
                'decision_remaining_seconds' => null,
                'last_activity_at' => $startedAt,
            ]);

            return $lockedLobby->load(['host', 'members.user', 'game']);
        });

        MultiplayerLobbyStarted::dispatch($lobby);

        return $lobby;
    }
}
