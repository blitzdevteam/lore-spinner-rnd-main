<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Adaptation\AdaptationStatusReconciliationJob;
use App\Jobs\Adaptation\RuntimeNarratorAssemblyJob;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Re-assemble the cached runtime narrator prompt for already-adapted stories.
 *
 * Every edit to the runtime narrator template only reaches players once the
 * cached prompt on `session_adaptations.runtime_narrator_prompt` is rebuilt.
 * This runs that rebuild without re-running any part of the adaptation pipeline,
 * so authored content is untouched.
 *
 * A session that fails is reported and skipped rather than aborting the run —
 * one over-cap or malformed session must not block every other story.
 */
final class RebuildRuntimePromptsCommand extends Command
{
    protected $signature = 'stories:rebuild-runtime-prompts
                            {story?* : Story IDs or slugs. Omit to rebuild every Boiler story}
                            {--session= : Limit to one session number}
                            {--dry-run : List what would be rebuilt without touching anything}
                            {--no-reconcile : Skip adaptation status reconciliation after a successful rebuild}';

    protected $description = 'Rebuild cached runtime narrator prompts after a prompt template change';

    public function handle(): int
    {
        $stories = $this->resolveStories();

        if ($stories->isEmpty()) {
            $this->error('No matching stories with an adaptation found.');

            return self::FAILURE;
        }

        $rows = [];
        $failures = 0;
        $reconciledStories = [];

        foreach ($stories as $story) {
            $storyFailuresBefore = $failures;
            $sessions = $story->adaptation->sessionAdaptations()
                ->when(
                    $this->option('session') !== null,
                    fn ($query) => $query->where('session_number', (int) $this->option('session')),
                )
                ->orderBy('session_number')
                ->get();

            if ($sessions->isEmpty()) {
                $rows[] = [$story->slug, '—', '—', 'no sessions'];

                continue;
            }

            foreach ($sessions as $session) {
                $before = mb_strlen((string) $session->runtime_narrator_prompt);

                if ($this->option('dry-run')) {
                    $rows[] = [$story->slug, $session->session_number, $before, 'would rebuild'];

                    continue;
                }

                try {
                    RuntimeNarratorAssemblyJob::dispatchSync($story, $session->session_number);
                    $fresh = $session->fresh();
                    $after = mb_strlen((string) $fresh->runtime_narrator_prompt);
                    $rows[] = [
                        $story->slug,
                        $session->session_number,
                        $before.' → '.$after,
                        $fresh->session_status->value,
                    ];
                } catch (Throwable $throwable) {
                    $failures++;
                    $rows[] = [
                        $story->slug,
                        $session->session_number,
                        $before.' → unchanged',
                        'FAILED: '.$throwable->getMessage(),
                    ];
                }
            }

            if (
                ! $this->option('dry-run')
                && ! $this->option('no-reconcile')
                && $failures === $storyFailuresBefore
                && $sessions->isNotEmpty()
            ) {
                AdaptationStatusReconciliationJob::dispatchSync($story);
                $reconciledStories[] = $story->slug.' → '.$story->adaptation?->fresh()->adaptation_status->value;
            }
        }

        $this->table(['Story', 'Session', 'Prompt chars', 'Result'], $rows);

        if ($failures > 0) {
            $this->error($failures.' session(s) failed. Their previous prompt is untouched and still in use.');

            return self::FAILURE;
        }

        $this->info('All runtime prompts rebuilt.');

        if ($reconciledStories !== []) {
            $this->info('Adaptation status reconciled:');
            foreach ($reconciledStories as $line) {
                $this->line('  '.$line);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Story>
     */
    private function resolveStories(): Collection
    {
        $identifiers = (array) $this->argument('story');

        if ($identifiers === []) {
            return Story::query()
                ->whereHas('adaptation', fn ($query) => $query->where('boiler_enabled', true))
                ->with('adaptation')
                ->orderBy('id')
                ->get();
        }

        return collect($identifiers)
            ->map(fn (string $identifier): ?Story => is_numeric($identifier)
                ? Story::with('adaptation')->find((int) $identifier)
                : Story::with('adaptation')->where('slug', $identifier)->first())
            ->filter(fn (?Story $story): bool => $story?->adaptation !== null)
            ->values();
    }
}
