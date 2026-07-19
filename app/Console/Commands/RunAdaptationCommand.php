<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Adaptation\RunAdaptationPipelineJob;
use App\Models\Story;
use Illuminate\Console\Command;

final class RunAdaptationCommand extends Command
{
    protected $signature = 'stories:run-adaptation
                            {story : Story ID or slug}
                            {--force : Re-run even if adaptation is already completed}
                            {--boiler : Enable Boiler pacing for this adaptation}';

    protected $description = 'Dispatch the adaptation pipeline for a story';

    public function handle(): int
    {
        $input = $this->argument('story');
        $force = (bool) $this->option('force');
        $boilerEnabled = (bool) $this->option('boiler');

        $story = is_numeric($input)
            ? Story::find((int) $input)
            : Story::where('slug', $input)->first();

        if (! $story) {
            $this->error("Story not found: {$input}");

            return self::FAILURE;
        }

        $this->info("Dispatching adaptation pipeline for: {$story->title}");
        $this->info("Story ID: {$story->id} | Slug: {$story->slug}");

        RunAdaptationPipelineJob::dispatch($story, $force, $boilerEnabled)->onQueue('adaptation');

        $this->info('Job dispatched to the [adaptation] queue.');
        $this->line('Boiler pacing: ' . ($boilerEnabled ? 'enabled' : 'disabled'));
        $this->line('Monitor progress: php artisan adaptation:export ' . $story->slug);

        return self::SUCCESS;
    }
}
