<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Story;
use App\Models\World;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Upsert launch worlds and attach published launch titles.
 *
 * Worlds are created from database/stories/launch/manifest.php. Standalone
 * titles are not attached. Background images stay in Filament — this seeder
 * only owns the catalog rows and story_world pivots.
 *
 * Usage: php artisan db:seed --class=WorldSeeder --force
 */
final class WorldSeeder extends Seeder
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public static function resolveStoryForEntry(array $entry): ?Story
    {
        $candidates = array_values(array_unique(array_filter([
            $entry['slug'] ?? null,
            ...($entry['seed_keys'] ?? []),
        ])));

        foreach ($candidates as $candidate) {
            $slug = Str::slug((string) $candidate);
            $story = Story::query()->where('slug', $slug)->first();

            if ($story !== null) {
                return $story;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function retireLegacyWorlds(World $canonical, array $definition): void
    {
        foreach ($definition['legacy_slugs'] ?? [] as $legacySlug) {
            $legacy = World::query()->where('slug', $legacySlug)->first();

            if ($legacy === null) {
                continue;
            }

            $legacy->stories()->pluck('stories.id')->each(function (int $storyId) use ($canonical): void {
                if (! $canonical->stories()->whereKey($storyId)->exists()) {
                    $canonical->stories()->attach($storyId, [
                        'position' => $canonical->stories()->count(),
                    ]);
                }
            });

            $legacy->stories()->detach();
            $legacy->update(['is_active' => false]);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function upsertWorld(array $definition): World
    {
        return World::query()->updateOrCreate(
            ['slug' => $definition['slug']],
            [
                'title' => $definition['title'],
                'tagline' => $definition['tagline'] ?? null,
                'description' => $definition['description'] ?? null,
                'tags' => $definition['tags'] ?? [],
                'position' => $definition['position'] ?? 0,
                'is_active' => true,
            ],
        );
    }

    /**
     * Attach one launch title to its world. No-op for standalones or unknown folders.
     */
    public static function attachStory(Story $story, ?string $folder, ?string $worldCatalogSlug = null): ?World
    {
        $manifest = require database_path('stories/launch/manifest.php');

        if ($worldCatalogSlug !== null) {
            foreach (($manifest['worlds'] ?? []) as $definition) {
                if (($definition['slug'] ?? null) !== $worldCatalogSlug) {
                    continue;
                }

                $world = self::upsertWorld($definition);

                if (! $world->stories()->whereKey($story->id)->exists()) {
                    $world->stories()->attach($story->id, [
                        'position' => $world->stories()->count(),
                    ]);
                }

                return $world;
            }
        }

        if ($folder === null || $folder === '' || $folder === 'standalone') {
            return null;
        }

        foreach (($manifest['worlds'] ?? []) as $definition) {
            if (($definition['folder'] ?? null) !== $folder) {
                continue;
            }

            $world = self::upsertWorld($definition);

            if (! $world->stories()->whereKey($story->id)->exists()) {
                $world->stories()->attach($story->id, [
                    'position' => $world->stories()->count(),
                ]);
            }

            return $world;
        }

        return null;
    }

    public function run(): void
    {
        $manifest = require database_path('stories/launch/manifest.php');
        $worlds = $manifest['worlds'] ?? [];
        $titles = $manifest['titles'] ?? [];

        foreach ($worlds as $definition) {
            $world = self::upsertWorld($definition);
            $entries = [];

            foreach ($titles as $entry) {
                $catalogSlug = $entry['world_catalog_slug'] ?? null;
                $folder = $definition['folder'] ?? null;
                $matchesCatalog = $catalogSlug !== null && $catalogSlug === ($definition['slug'] ?? null);
                $matchesFolder = ($entry['world_slug'] ?? null) === $folder;

                if ($matchesCatalog || ($catalogSlug === null && $matchesFolder)) {
                    $entries[] = $entry;
                }
            }

            $sync = [];

            foreach ($entries as $position => $entry) {
                $story = self::resolveStoryForEntry($entry);

                if ($story === null) {
                    continue;
                }

                $sync[$story->id] = ['position' => $position];
            }

            $world->stories()->syncWithoutDetaching($sync);

            self::retireLegacyWorlds($world, $definition);

            $this->command?->info(sprintf(
                'World %s: %d/%d titles attached.',
                $world->title,
                count($sync),
                count($entries),
            ));
        }
    }
}
