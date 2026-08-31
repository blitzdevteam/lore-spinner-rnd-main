<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\World;
use Database\Seeders\WorldSeeder;

/**
 * Publish and world-attach launch/boiled titles once boiler adaptation completes.
 */
final class LaunchStoryActivator
{
    public static function activate(Story $story): bool
    {
        if (self::activationBlockReason($story) !== null) {
            return false;
        }

        return self::sync($story);
    }

    public static function activationBlockReason(Story $story): ?string
    {
        if (! StoryPlayability::isLaunchSlug($story->slug)) {
            return 'slug is not on the launch/boiled playable allowlist';
        }

        $story->loadMissing('adaptation');

        if ($story->adaptation?->adaptation_status !== AdaptationStatusEnum::COMPLETED) {
            return 'adaptation status is not completed (current: '
                .($story->adaptation?->adaptation_status->value ?? 'none').')';
        }

        return null;
    }

    public static function sync(Story $story): bool
    {
        if (self::activationBlockReason($story) !== null) {
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

        if ($changed) {
            return true;
        }

        return self::isAlreadySynced($story, $entry);
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

    /**
     * @param  array<string, mixed>|null  $entry
     */
    private static function isAlreadySynced(Story $story, ?array $entry): bool
    {
        if ($story->status !== StoryStatusEnum::PUBLISHED) {
            return false;
        }

        $catalogSlug = $entry['world_catalog_slug'] ?? null;

        if ($catalogSlug === null) {
            return true;
        }

        $world = World::query()->where('slug', $catalogSlug)->first();

        return $world?->stories()->whereKey($story->id)->exists() ?? false;
    }
}
