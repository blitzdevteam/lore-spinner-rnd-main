<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Story;
use App\Models\World;
use Illuminate\Database\Seeder;

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
    public function run(): void
    {
        $manifest = require database_path('stories/launch/manifest.php');
        $worlds = $manifest['worlds'] ?? [];
        $titles = $manifest['titles'] ?? [];

        foreach ($worlds as $definition) {
            $world = self::upsertWorld($definition);
            $slugs = [];

            foreach ($titles as $entry) {
                $catalogSlug = $entry['world_catalog_slug'] ?? null;
                $folder = $definition['folder'] ?? null;
                $matchesCatalog = $catalogSlug !== null && $catalogSlug === ($definition['slug'] ?? null);
                $matchesFolder = ($entry['world_slug'] ?? null) === $folder;

                if ($matchesCatalog || ($catalogSlug === null && $matchesFolder)) {
                    $slugs[] = $entry['slug'];
                }
            }

            $stories = Story::query()->whereIn('slug', $slugs)->get()->keyBy('slug');
            $sync = [];

            foreach ($slugs as $position => $slug) {
                $story = $stories->get($slug);

                if ($story === null) {
                    continue;
                }

                $sync[$story->id] = ['position' => $position];
            }

            $world->stories()->syncWithoutDetaching($sync);

            $this->command?->info(sprintf(
                'World %s: %d/%d titles attached.',
                $world->title,
                count($sync),
                count($slugs),
            ));
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
}
