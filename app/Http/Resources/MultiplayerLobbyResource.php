<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MultiplayerLobby;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin MultiplayerLobby
 */
final class MultiplayerLobbyResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_code' => $this->room_code,
            'invite_url' => $this->whenLoaded('story', fn (): string => route('stories.multiplayer', [
                'story' => $this->story,
                'room' => $this->room_code,
            ])),
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'max_players' => $this->max_players,
            'settings' => [
                ...$this->settings,
                'decision_window_seconds' => $this->decisionWindowSeconds(),
            ],
            'created_at' => $this->created_at,

            'story' => StoryResource::make($this->whenLoaded('story')),
            'host' => $this->whenLoaded('host', function (): array {
                return [
                    'id' => $this->host->id,
                    'full_name' => $this->host->full_name,
                    'username' => $this->host->username,
                    'avatar' => $this->host->avatar,
                ];
            }),
            'members' => MultiplayerLobbyMemberResource::collection($this->whenLoaded('members')),

            'is_host' => (string) $request->user('user')?->getAuthIdentifier() === (string) $this->host_user_id,
        ];
    }
}
