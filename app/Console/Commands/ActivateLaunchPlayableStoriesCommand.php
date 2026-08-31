<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use App\Support\LaunchStoryActivator;
use App\Support\StoryPlayability;
use Illuminate\Console\Command;

final class ActivateLaunchPlayableStoriesCommand extends Command
{
    protected $signature = 'stories:activate-launch-playable
                            {story? : Story ID or slug. Omit to sync every completed launch/boiled title}';

    protected $description = 'Publish completed launch/boiled titles and attach them to their worlds';

    public function handle(): int
    {
        $stories = $this->resolveStories();

        if ($stories->isEmpty()) {
            $this->error('No matching stories found.');

            return self::FAILURE;
        }

        $activated = 0;

        foreach ($stories as $story) {
            $blockReason = LaunchStoryActivator::activationBlockReason($story);

            if ($blockReason !== null) {
                $this->line("Skipped {$story->slug}: {$blockReason}.");

                continue;
            }

            if (! LaunchStoryActivator::sync($story)) {
                $this->line("Skipped {$story->slug}: already published and attached.");

                continue;
            }

            $activated++;
            $this->info("Activated {$story->title} ({$story->slug}).");
        }

        $this->info("Done. {$activated}/{$stories->count()} title(s) activated.");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Story>
     */
    private function resolveStories()
    {
        $identifier = $this->argument('story');

        if ($identifier !== null) {
            $story = is_numeric($identifier)
                ? Story::query()->with('adaptation')->find((int) $identifier)
                : Story::query()->with('adaptation')->where('slug', (string) $identifier)->first();

            return $story ? collect([$story]) : collect();
        }

        return Story::query()
            ->with('adaptation')
            ->whereHas('adaptation', fn ($query) => $query->where(
                'adaptation_status',
                AdaptationStatusEnum::COMPLETED,
            ))
            ->get()
            ->filter(fn (Story $story): bool => StoryPlayability::isLaunchSlug($story->slug))
            ->values();
    }
}
