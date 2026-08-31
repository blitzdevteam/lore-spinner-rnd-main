<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use Database\Seeders\WorldSeeder;

/**
 * Publish and world-attach launch/boiled titles once boiler adaptation completes.
 */
final class LaunchStoryActivator
{
    public static function activate(Story $story): bool
    {
        if (! StoryPlayability::isLaunchSlug($story->slug)) {
            return false;
        }

        $story->loadMissing('adaptation');

        if ($story->adaptation?->adaptation_status !== AdaptationStatusEnum::COMPLETED) {
            return false;
        }

        $entry = self::manifestEntryForStory($story);
        $changed = false;

        if ($story->status !== StoryStatusEnum::PUBLISHED) {
            $story->update([
                'status' => StoryStatusEnum::PUBLISHED,
                'published_at' => $story->published_at ?? now(),
            ]);
            $changed = true;
        }

        if ($entry !== null) {
            $worldSlug = ($entry['world_slug'] ?? null) === 'standalone'
                ? null
                : ($entry['world_slug'] ?? null);

            WorldSeeder::attachStory(
                $story->fresh(),
                $worldSlug,
                $entry['world_catalog_slug'] ?? null,
            );
            $changed = true;
        }

        return $changed;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function manifestEntryForStory(Story $story): ?array
    {
        $manifest = require database_path('stories/launch/manifest.php');

        foreach ($manifest['titles'] ?? [] as $entry) {
            $resolved = WorldSeeder::resolveStoryForEntry($entry);

            if ($resolved?->is($story)) {
                return $entry;
            }
        }

        return null;
    }
}
