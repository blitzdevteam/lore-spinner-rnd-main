<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Story;
use App\Support\LaunchCatalog;
use Illuminate\Database\Seeder;

/**
 * Sync stored story titles to the published launch catalog titles.
 *
 * Usage: php artisan db:seed --class=UpdateStoryTitlesSeeder --force
 */
final class UpdateStoryTitlesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LaunchCatalog::titles() as $slug => $entry) {
            $this->rename($slug, $entry['title']);
        }

        foreach (config('stories.playable_slug_aliases', []) as $alias => $canonical) {
            $this->rename($alias, LaunchCatalog::displayTitle($canonical));
        }

        $this->command?->info('Story title update complete.');
    }

    private function rename(string $slug, string $title): void
    {
        $updated = Story::query()->where('slug', $slug)->update(['title' => $title]);

        if ($updated > 0) {
            $this->command?->info("Updated title: {$slug} → {$title}");
        }
    }
}
