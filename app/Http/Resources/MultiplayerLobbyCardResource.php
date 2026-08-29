<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MultiplayerLobby;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin MultiplayerLobby
 */
final class MultiplayerLobbyCardResource extends BaseResource
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
            'mode' => $this->mode->value,
            'max_players' => $this->max_players,
            'players_count' => (int) $this->getAttribute('members_count'),
            'story' => [
                'title' => $this->story->title,
                'slug' => $this->story->slug,
                'cover' => $this->story->getFirstMediaUrl('cover'),
            ],
            'is_host' => (string) $request->user('user')?->getAuthIdentifier() === (string) $this->host_user_id,
        ];
    }
}
