<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Adaptation\StoryRepairContext;
use App\Ai\Adaptation\StoryRepairPatchApplier;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryRepairReport;
use App\Services\Adaptation\StoryRepairService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Review a session's stored story material with the repair editor.
 *
 * A review is stored as a report and changes nothing. --apply-report stores
 * exactly what that report accepted, and --revert undoes it.
 */
final class RepairStorySessionCommand extends Command
{
    protected $signature = 'stories:repair-session
                            {story : Story ID or slug}
                            {--session=* : Session numbers. Omit to review every session}
                            {--skip=* : Targets to leave out of the review (scene_card, session_choice_design, choice_consequence_map, session_close_design, entry_point_diagnosis, previous_session_close, next_session_entry)}
                            {--note=* : Known defects (e.g. tester reports) the review must verify and fix}
                            {--dry-run : Print the gathered context without calling the model}
                            {--show-prompt : With --dry-run, print the full prompt}
                            {--apply : Review and store the accepted changes in one step}
                            {--apply-report= : Store the changes accepted by this report ID}
                            {--revert= : Undo the changes stored by this report ID}
                            {--list : List recent reports for the story}';

    protected $description = 'Review and repair stored story material (scene cards, choice binding, choices, consequences, close, cold open)';

    public function handle(StoryRepairService $repairs, StoryRepairContext $contextBuilder): int
    {
        $story = $this->resolveStory();

        if ($story?->adaptation === null) {
            $this->error('No matching story with an adaptation found.');

            return self::FAILURE;
        }

        try {
            return match (true) {
                $this->option('list') => $this->listReports($story),
                filled($this->option('apply-report')) => $this->applyReport($story, $repairs, (int) $this->option('apply-report')),
                filled($this->option('revert')) => $this->revertReport($story, $repairs, (int) $this->option('revert')),
                default => $this->review($story, $repairs, $contextBuilder),
            };
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }
    }

    private function review(Story $story, StoryRepairService $repairs, StoryRepairContext $contextBuilder): int
    {
        $skip = array_values(array_map('strval', (array) $this->option('skip')));
        $unknown = array_diff($skip, StoryRepairPatchApplier::TARGETS);

        if ($unknown !== []) {
            $this->error('Unknown --skip target: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $requested = array_map('intval', (array) $this->option('session'));
        $sessions = $story->adaptation->sessionAdaptations()
            ->when($requested !== [], fn ($query) => $query->whereIn('session_number', $requested))
            ->orderBy('session_number')
            ->get();

        if ($sessions->isEmpty()) {
            $this->error('No matching sessions found.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($sessions as $session) {
            $this->newLine();
            $this->info("Story repair: {$story->slug}, session {$session->session_number}");

            if ($this->option('dry-run')) {
                $this->dryRun($story, $session, $contextBuilder, $skip);

                continue;
            }

            try {
                $report = $repairs->review($story, $session, 'command', $skip, $this->notes());
            } catch (Throwable $throwable) {
                $this->error($throwable->getMessage());
                $failed = true;

                continue;
            }

            $this->printReport($report);

            if ($this->option('apply')) {
                $this->printApplied($repairs->apply($story, $report), $report);
            } else {
                $this->info("Review only. Nothing was saved. To store exactly these changes: php artisan stories:repair-session {$story->slug} --apply-report={$report->id}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<string>  $skip
     */
    private function dryRun(Story $story, SessionAdaptation $session, StoryRepairContext $contextBuilder, array $skip): void
    {
        $context = [...$contextBuilder->build($story, $session, $skip), 'reviewer_notes' => $this->notes()];
        $this->line('Protagonist: '.$context['protagonist']);
        $this->line('Scene cards: '.count($context['material']['scene_cards'] ?? []).'. Source events: '.count($context['source_events']).'.');

        if ($this->option('show-prompt')) {
            $this->line(view('ai.agents.adaptation.story-repair.system-prompt', $context)->render());
            $this->newLine();
            $this->line(view('ai.agents.adaptation.story-repair.prompt', $context)->render());
        }

        $this->info('Dry run only. The model was not called and nothing was saved.');
    }

    private function applyReport(Story $story, StoryRepairService $repairs, int $reportId): int
    {
        $report = $this->reportFor($story, $reportId);
        $this->printApplied($repairs->apply($story, $report), $report);

        return self::SUCCESS;
    }

    private function revertReport(Story $story, StoryRepairService $repairs, int $reportId): int
    {
        $report = $this->reportFor($story, $reportId);
        $repairs->revert($report);
        $this->info("Reverted report {$report->id}. Rebuild the session's runtime prompt next.");

        return self::SUCCESS;
    }

    private function listReports(Story $story): int
    {
        $reports = StoryRepairReport::query()
            ->whereIn('session_adaptation_id', $story->adaptation->sessionAdaptations()->pluck('id'))
            ->with('sessionAdaptation:id,session_number')
            ->latest('id')
            ->limit(30)
            ->get();

        $this->table(
            ['Report', 'Session', 'Source', 'Status', 'Findings', 'Patches', 'Rebinds', 'Created'],
            $reports->map(fn (StoryRepairReport $report): array => [
                $report->id,
                $report->sessionAdaptation->session_number,
                $report->source,
                $report->status,
                count((array) $report->findings),
                count((array) $report->patches),
                count((array) $report->rebinds),
                $report->created_at?->toDateTimeString(),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function reportFor(Story $story, int $reportId): StoryRepairReport
    {
        return StoryRepairReport::query()
            ->whereKey($reportId)
            ->whereIn('session_adaptation_id', $story->adaptation->sessionAdaptations()->pluck('id'))
            ->firstOrFail();
    }

    private function printReport(StoryRepairReport $report): void
    {
        $this->line('Findings: '.count((array) $report->findings));

        foreach ((array) $report->findings as $finding) {
            $this->line(sprintf(
                '- [%s/%s] %s: %s',
                (string) data_get($finding, 'severity', '?'),
                (string) data_get($finding, 'category', '?'),
                (string) data_get($finding, 'location', '?'),
                (string) data_get($finding, 'issue', ''),
            ));
        }

        $this->line('Accepted rebinds: '.count((array) $report->rebinds));

        foreach ((array) $report->rebinds as $rebind) {
            $this->line("- {$rebind['choice_id']}: {$rebind['from_moment_key']} -> {$rebind['to_moment_key']} ({$rebind['reason']})");
        }

        if ((array) ($report->reorder ?? []) !== []) {
            $this->line('Accepted reorder: '.implode(' -> ', (array) $report->reorder));
        }

        $this->line('Accepted patches: '.count((array) $report->patches));

        foreach ((array) $report->patches as $patch) {
            $this->line('- '.mb_trim($patch['target'].' '.$patch['key']).' '.$patch['path']);
            $this->line('  was: '.$patch['old_value']);
            $this->line('  now: '.$patch['new_value']);
        }

        foreach ((array) $report->rejected as $rejection) {
            $this->warn('Rejected: '.json_encode($rejection['change'] ?? [], JSON_UNESCAPED_UNICODE).' ('.($rejection['reason'] ?? '').')');
        }

        $this->line("Report ID: {$report->id}");
    }

    /**
     * @param  array{applied_patches: int, applied_rebinds: int, applied_reorder: bool, stale: list<array{change: array<string, mixed>, reason: string}>}  $result
     */
    private function printApplied(array $result, StoryRepairReport $report): void
    {
        foreach ($result['stale'] as $stale) {
            $this->warn('Skipped: '.($stale['reason'] ?? ''));
        }

        $reordered = $result['applied_reorder'] ? ', and reordered the cards' : '';
        $number = (int) $report->sessionAdaptation->session_number;
        $rebuild = collect((array) $report->patches)
            ->pluck('target')
            ->map(fn (string $target): ?int => match ($target) {
                'previous_session_close' => $number - 1,
                'next_session_entry' => $number + 1,
                default => $number,
            })
            ->push($number)
            ->unique()
            ->sort()
            ->implode(', ');
        $this->info("Stored {$result['applied_patches']} patches and {$result['applied_rebinds']} rebinds{$reordered} from report {$report->id}. Undo with --revert={$report->id}. Rebuild the runtime prompt for session(s) {$rebuild} next.");
    }

    /**
     * @return list<string>
     */
    private function notes(): array
    {
        return array_values(array_filter(array_map(
            static fn ($note): string => mb_trim((string) $note),
            (array) $this->option('note'),
        )));
    }

    private function resolveStory(): ?Story
    {
        $identifier = (string) $this->argument('story');
        $query = Story::query()->with('adaptation');

        return is_numeric($identifier)
            ? $query->find((int) $identifier)
            : $query->where('slug', $identifier)->first();
    }
}
