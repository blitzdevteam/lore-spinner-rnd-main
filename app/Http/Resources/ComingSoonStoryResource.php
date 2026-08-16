<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ComingSoonStory;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin ComingSoonStory
 */
final class ComingSoonStoryResource extends BaseResource
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
            'section' => $this->section->value,
            'section_label' => $this->section->getLabel(),
            'teaser' => $this->teaser,
            'category' => $this->category,
            'rating' => $this->rating,
            'author' => $this->author,
            'series_label' => $this->series_label,
            'image_position' => $this->image_position,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'cover' => $this->getFirstMediaUrl('cover'),
            'updated_at' => $this->updated_at,
        ];
    }
}
