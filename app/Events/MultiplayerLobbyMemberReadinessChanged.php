<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MultiplayerLobby;
use App\Support\Multiplayer\MultiplayerLobbyBroadcastPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MultiplayerLobbyMemberReadinessChanged implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public MultiplayerLobby $lobby) {}

    public function broadcastAs(): string
    {
        return 'multiplayer.lobby.member.readiness.changed';
    }

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('multiplayer.lobby.'.$this->lobby->id)];
    }

    /**
     * @return array{lobby: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return [
            'lobby' => MultiplayerLobbyBroadcastPayload::forLobby($this->lobby),
        ];
    }
}
