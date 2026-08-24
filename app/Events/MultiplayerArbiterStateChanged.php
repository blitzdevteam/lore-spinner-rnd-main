<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MultiplayerLobby;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MultiplayerArbiterStateChanged implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public MultiplayerLobby $lobby) {}

    public function broadcastAs(): string
    {
        return 'multiplayer.arbiter.state.changed';
    }

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('multiplayer.lobby.'.$this->lobby->id)];
    }

    /**
     * The clients reload their role-filtered game state after this signal. In
     * particular, player proposals stay private until the Arbiter may see them.
     *
     * @return array{lobby_id: string}
     */
    public function broadcastWith(): array
    {
        return [
            'lobby_id' => (string) $this->lobby->id,
        ];
    }
}
