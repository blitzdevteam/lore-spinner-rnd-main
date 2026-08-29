<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;

final class StoryPlayability
{
    /**
     * @return list<string>
     */
    public static function reviewSlugs(): array
    {
        /** @var list<string> $slugs */
        $slugs = config('stories.review_playable_slugs', []);

        return $slugs;
    }

    public static function isReviewSlug(string $slug): bool
    {
        return in_array($slug, self::reviewSlugs(), true);
    }

    public static function isPlayable(Story $story): bool
    {
        if ($story->status !== StoryStatusEnum::PUBLISHED) {
            return false;
        }

        if (! self::isReviewSlug($story->slug)) {
            return false;
        }

        $adaptation = $story->relationLoaded('adaptation')
            ? $story->adaptation
            : $story->adaptation()->first(['id', 'story_id', 'adaptation_status']);

        return $adaptation?->adaptation_status === AdaptationStatusEnum::COMPLETED;
    }
}
