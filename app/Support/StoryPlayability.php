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
    public static function launchSlugs(): array
    {
        /** @var list<string> $launch */
        $launch = config('stories.launch_playable_slugs', []);
        /** @var list<string> $boiled */
        $boiled = config('stories.boiled_playable_slugs', []);

        return array_values(array_unique([...$launch, ...$boiled]));
    }

    public static function isLaunchSlug(string $slug): bool
    {
        return in_array($slug, self::launchSlugs(), true);
    }

    /** @deprecated Use isLaunchSlug() */
    public static function isReviewSlug(string $slug): bool
    {
        return self::isLaunchSlug($slug);
    }

    public static function isPlayable(Story $story): bool
    {
        if ($story->status !== StoryStatusEnum::PUBLISHED) {
            return false;
        }

        if (! self::isLaunchSlug($story->slug)) {
            return false;
        }

        $adaptation = $story->relationLoaded('adaptation')
            ? $story->adaptation
            : $story->adaptation()->first(['id', 'story_id', 'adaptation_status']);

        return $adaptation?->adaptation_status === AdaptationStatusEnum::COMPLETED;
    }
}
