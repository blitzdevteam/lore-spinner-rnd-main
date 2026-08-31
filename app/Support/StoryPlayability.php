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

    public static function canonicalSlug(string $slug): string
    {
        /** @var array<string, string> $aliases */
        $aliases = config('stories.playable_slug_aliases', []);

        return $aliases[$slug] ?? $slug;
    }

    public static function isLaunchSlug(string $slug): bool
    {
        $canonical = self::canonicalSlug($slug);
        $slugs = self::launchSlugs();

        return in_array($slug, $slugs, true) || in_array($canonical, $slugs, true);
    }

    /**
     * Position in the launch/boiled roster (0-based). Unknown slugs sort last.
     */
    public static function orderIndex(string $slug): int
    {
        $canonical = self::canonicalSlug($slug);
        $slugs = self::launchSlugs();
        $index = array_search($canonical, $slugs, true);

        if ($index === false) {
            $index = array_search($slug, $slugs, true);
        }

        return $index === false ? PHP_INT_MAX : $index;
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
