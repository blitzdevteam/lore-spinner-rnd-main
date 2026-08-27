<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MultiplayerLobbyChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MultiplayerLobbyChatMessageSent implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public MultiplayerLobbyChatMessage $message) {}

    public function broadcastAs(): string
    {
        return 'multiplayer.lobby.chat.message.sent';
    }

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('multiplayer.lobby.'.$this->message->lobby_id)];
    }

    /**
     * @return array{lobby_id: string, message: array{id: string, body: string, created_at: string|null, user: array{id: int, full_name: string, username: string|null, avatar: string}}}
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('user');

        return [
            'lobby_id' => (string) $this->message->lobby_id,
            'message' => [
                'id' => (string) $this->message->id,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toISOString(),
                'user' => [
                    'id' => $this->message->user->id,
                    'full_name' => $this->message->user->full_name,
                    'username' => $this->message->user->username,
                    'avatar' => $this->message->user->avatar ?? '',
                ],
            ],
        ];
    }
}
