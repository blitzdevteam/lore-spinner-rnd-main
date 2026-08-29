<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Story;
use App\Support\StoryPlayability;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin Story
 */
final class StoryResource extends BaseResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'teaser' => $this->teaser,
            'opening' => $this->opening,
            'status' => $this->status->toResource(),
            'rating' => $this->rating->toResource(),
            'published_at' => $this->published_at,
            'updated_at' => $this->updated_at,
            'cover' => $this->getFirstMediaUrl('cover'),
            'banner' => $this->getFirstMediaUrl('banner'),
            'outro_poster' => $this->getFirstMediaUrl('outro') ?: null,

            // Relations
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'creator' => CreatorResource::make($this->whenLoaded('creator')),
            'chapters' => ChapterResource::collection($this->whenLoaded('chapters')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),

            // Counts
            'chapters_count' => $this->whenCounted('chapters'),
            'comments_count' => $this->whenCounted('comments'),

            'is_bookmarked' => false,
            'is_playable' => StoryPlayability::isPlayable($this->resource),
        ];
    }
}
