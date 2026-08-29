<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MultiplayerLobbyChatMessage;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin MultiplayerLobbyChatMessage
 */
final class MultiplayerLobbyChatMessageResource extends BaseResource
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
            'body' => $this->body,
            'created_at' => $this->created_at?->toISOString(),

            'user' => $this->whenLoaded('user', function (): array {
                return [
                    'id' => $this->user->id,
                    'full_name' => $this->user->full_name,
                    'username' => $this->user->username,
                    'avatar' => $this->user->avatar ?? '',
                ];
            }),
        ];
    }
}
