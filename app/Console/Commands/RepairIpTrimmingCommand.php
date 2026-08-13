<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Adaptation\AdaptationStatusReconciliationJob;
use App\Jobs\Adaptation\RepairIpTrimmingJob;
use App\Models\Story;
use App\Support\Adaptation\IpTrimmingIntegrity;
use Illuminate\Console\Command;

final class RepairIpTrimmingCommand extends Command
{
    protected $signature = 'stories:repair-ip-trimming
                            {story : Story ID or slug}
                            {--rerun-sessions=auto : Comma-separated session numbers, "auto", or "none"}
                            {--reconcile-only : Skip repair; only run adaptation status reconciliation}';

    protected $description = 'Re-run IP trimming for stories with missing chapter_segments and optionally refresh affected sessions';

    public function handle(): int
    {
        $input = (string) $this->argument('story');

        $story = is_numeric($input)
            ? Story::find((int) $input)
            : Story::where('slug', $input)->first();

        if (! $story) {
            $this->error("Story not found: {$input}");

            return self::FAILURE;
        }

        if ((bool) $this->option('reconcile-only')) {
            AdaptationStatusReconciliationJob::dispatchSync($story);
            $this->info('Adaptation status: ' . $story->adaptation?->fresh()->adaptation_status->value);

            return self::SUCCESS;
        }

        $missing = IpTrimmingIntegrity::chaptersMissingFromIpTrimming($story);

        if ($missing->isEmpty()) {
            AdaptationStatusReconciliationJob::dispatchSync($story);
            $this->info("IP trimming already complete for {$story->slug}.");
            $this->info('Adaptation status: ' . $story->adaptation?->fresh()->adaptation_status->value);

            return self::SUCCESS;
        }

        $rerunSessions = $this->resolveRerunSessions($story, $missing);

        $this->warn("Missing IP trim for {$missing->count()} chapter(s): Ch" . $missing->pluck('position')->implode(', Ch'));
        $this->line('Sessions to refresh after merge: ' . ($rerunSessions === [] ? 'none' : implode(', ', $rerunSessions)));
        $this->info("Dispatching repair job for: {$story->title} (id={$story->id})");

        RepairIpTrimmingJob::dispatch($story, $rerunSessions)->onQueue('adaptation');

        $this->line('Job dispatched to the [adaptation] queue.');
        $this->line('After the worker finishes, verify trimmed chapter_segments match total chapters.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>|null
     */
    private function resolveRerunSessions(Story $story, $missingChapters): ?array
    {
        $option = strtolower(trim((string) $this->option('rerun-sessions')));

        return match ($option) {
            'none' => [],
            'auto' => IpTrimmingIntegrity::sessionNumbersForChapters($story, $missingChapters),
            default => array_values(array_filter(array_map(
                fn (string $value) => is_numeric(trim($value)) ? (int) trim($value) : null,
                explode(',', $option),
            ))),
        };
    }
}
