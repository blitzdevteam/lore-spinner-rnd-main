<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\World;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin World
 */
final class WorldResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'tags' => $this->tags ?? [],
            'position' => $this->position,
            'is_active' => $this->is_active,
            'background' => $this->getFirstMediaUrl('background'),
            'stories' => StoryResource::collection($this->whenLoaded('stories')),
            'stories_count' => $this->whenCounted('stories'),
            'updated_at' => $this->updated_at,
        ];
    }
}
