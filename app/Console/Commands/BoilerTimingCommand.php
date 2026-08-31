<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Support\Adaptation\BoilerTimingReport;
use Illuminate\Console\Command;

final class BoilerTimingCommand extends Command
{
    protected $signature = 'stories:boiler-timing
                            {story? : Story ID or slug (omit for all boiler-enabled titles)}
                            {--json : Output machine-readable JSON}
                            {--sessions : Include per-session timing rows in table output}';

    protected $description = 'Report boil duration for boiler-enabled stories using persisted session timestamps';

    public function handle(BoilerTimingReport $report): int
    {
        $adaptations = $this->resolveAdaptations();
        if ($adaptations->isEmpty()) {
            $this->error('No boiler-enabled story adaptations found.');

            return self::FAILURE;
        }

        $reports = $report->forAdaptations($adaptations);

        if ($this->option('json')) {
            $this->line(json_encode(
                $reports,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        $this->line('Boil timing uses session timestamps. Wall clock spans first boil start to last runtime assembly.');
        $this->line('Sessions boiled in parallel: wall clock is authoritative; sum is informational only.');
        $this->newLine();

        $this->table(
            ['Title', 'Slug', 'Adaptation', 'Sessions', 'Done', 'Wall clock', 'Avg / session', 'Coverage'],
            collect($reports)->map(function (array $row): array {
                $summary = $row['summary'];

                return [
                    $row['story_title'],
                    $row['story_slug'],
                    $row['adaptation_status'],
                    $summary['session_count'],
                    $summary['completed_session_count'],
                    $summary['boil_wall_clock_human'] ?? '—',
                    $summary['avg_session_human'] ?? '—',
                    $this->shortCoverage((string) $summary['coverage_note']),
                ];
            })->all(),
        );

        if ($this->option('sessions') || $this->argument('story') !== null) {
            foreach ($reports as $row) {
                $this->newLine();
                $this->line(sprintf(
                    '%s (%s) — %s',
                    $row['story_title'],
                    $row['story_slug'],
                    $row['summary']['coverage_note'],
                ));

                $this->table(
                    ['Session', 'Boiler', 'Draft', 'Post-draft', 'Total', 'Coverage', 'Moments'],
                    collect($row['sessions'])->map(function (array $session): array {
                        return [
                            $session['session_number'],
                            $session['boiler_status'],
                            $session['draft_human'] ?? '—',
                            $session['post_draft_human'] ?? '—',
                            $session['total_human'] ?? '—',
                            $session['coverage'],
                            $session['moments'],
                        ];
                    })->all(),
                );
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, StoryAdaptation>
     */
    private function resolveAdaptations()
    {
        $query = StoryAdaptation::query()
            ->where('boiler_enabled', true)
            ->with(['story', 'sessionAdaptations.playableCut'])
            ->orderBy('id');

        $identifier = $this->argument('story');
        if ($identifier !== null) {
            $story = is_numeric($identifier)
                ? Story::find((int) $identifier)
                : Story::where('slug', $identifier)->first();

            if ($story === null) {
                return collect();
            }

            $query->where('story_id', $story->id);
        }

        return $query->get();
    }

    private function shortCoverage(string $note): string
    {
        if (str_contains($note, 'Full boil timing')) {
            return 'full';
        }

        if (str_contains($note, 'post-draft')) {
            return 'post-draft only';
        }

        if (str_contains($note, 'No completed')) {
            return 'none yet';
        }

        return 'mixed';
    }
}
