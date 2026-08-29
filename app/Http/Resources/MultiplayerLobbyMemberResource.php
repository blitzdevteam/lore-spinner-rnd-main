<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MultiplayerLobbyMember;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin MultiplayerLobbyMember
 */
final class MultiplayerLobbyMemberResource extends BaseResource
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
            'role' => $this->role->value,
            'is_ready' => $this->is_ready,
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
            'last_seen_at' => $this->last_seen_at,

            'user' => $this->whenLoaded('user', function (): array {
                return [
                    'id' => $this->user->id,
                    'full_name' => $this->user->full_name,
                    'username' => $this->user->username,
                    'avatar' => $this->user->avatar,
                ];
            }),
        ];
    }
}
