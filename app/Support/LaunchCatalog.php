<?php

declare(strict_types=1);

namespace App\Support;

final class LaunchCatalog
{
    /**
     * @return array<string, array{title: string, world_slug: string, world: string}>
     */
    public static function titles(): array
    {
        /** @var array<string, array{title: string, world_slug: string, world: string}> $titles */
        $titles = config('stories.launch_catalog.titles', []);

        return $titles;
    }

    /**
     * @return list<array{slug: string, folder: string, title: string}>
     */
    public static function worlds(): array
    {
        /** @var list<array{slug: string, folder: string, title: string}> $worlds */
        $worlds = config('stories.launch_catalog.worlds', []);

        return $worlds;
    }

    public static function displayTitle(string $slug, ?string $fallback = null): string
    {
        return self::titles()[$slug]['title'] ?? $fallback ?? $slug;
    }

    public static function worldName(string $slug): ?string
    {
        return self::titles()[$slug]['world'] ?? null;
    }

    public static function worldSlugForStory(string $storySlug): ?string
    {
        return self::titles()[$storySlug]['world_slug'] ?? null;
    }

    public static function worldTitle(string $worldSlug): ?string
    {
        foreach (self::worlds() as $world) {
            if ($world['slug'] === $worldSlug) {
                return $world['title'];
            }
        }

        return null;
    }

    public static function isLaunchTitle(string $slug): bool
    {
        return array_key_exists($slug, self::titles());
    }
}
