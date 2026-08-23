<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MultiplayerLobbyDeleted implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<int, int>  $memberUserIds
     */
    public function __construct(
        public string $lobbyId,
        public array $memberUserIds,
    ) {}

    public function broadcastAs(): string
    {
        return 'multiplayer.lobby.deleted';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PresenceChannel('multiplayer.lobby.'.$this->lobbyId),
            new PrivateChannel('multiplayer.lobbies'),
        ];

        foreach (array_unique($this->memberUserIds) as $memberUserId) {
            $channels[] = new PrivateChannel('App.Models.User.'.$memberUserId);
        }

        return $channels;
    }

    /**
     * @return array{lobby_id: string}
     */
    public function broadcastWith(): array
    {
        return [
            'lobby_id' => $this->lobbyId,
        ];
    }
}
