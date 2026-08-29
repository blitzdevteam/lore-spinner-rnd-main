<?php

declare(strict_types=1);

namespace App\Support\Multiplayer;

use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;

final class MultiplayerLobbyBroadcastPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forLobby(MultiplayerLobby $lobby): array
    {
        $lobby->loadMissing('members.user');

        return [
            'id' => $lobby->id,
            'room_code' => $lobby->room_code,
            'mode' => $lobby->mode->value,
            'status' => $lobby->status->value,
            'max_players' => $lobby->max_players,
            'settings' => $lobby->settings,
            'members' => $lobby->members
                ->sortBy('joined_at')
                ->values()
                ->map(static function (MultiplayerLobbyMember $member): array {
                    return [
                        'id' => $member->id,
                        'role' => $member->role->value,
                        'is_ready' => $member->is_ready,
                        'joined_at' => $member->joined_at?->toISOString(),
                        'left_at' => $member->left_at?->toISOString(),
                        'last_seen_at' => $member->last_seen_at?->toISOString(),
                        'user' => [
                            'id' => $member->user->id,
                            'full_name' => $member->user->full_name,
                            'username' => $member->user->username,
                            'avatar' => $member->user->avatar,
                        ],
                    ];
                })
                ->all(),
        ];
    }
}
