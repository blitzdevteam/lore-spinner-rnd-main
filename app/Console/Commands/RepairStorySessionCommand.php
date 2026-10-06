<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Adaptation\StoryRepairContext;
use App\Ai\Adaptation\StoryRepairPatchApplier;
use App\Ai\Agents\Adaptation\StoryRepairAgent;
use App\Models\SessionAdaptation;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Review a session's stored story material with the repair editor and,
 * with --apply, store the validated text patches.
 *
 * Every run writes its findings and patches to the local disk under
 * story-repairs/, so a review can be read in full before anything is applied.
 */
final class RepairStorySessionCommand extends Command
{
    protected $signature = 'stories:repair-session
                            {story : Story ID or slug}
                            {--session=* : Session numbers. Omit to review every session}
                            {--dry-run : Print the gathered context size without calling the model}
                            {--show-prompt : With --dry-run, print the full prompt}
                            {--apply : Store the validated patches}';

    protected $description = 'Review and repair stored story material (scene cards, choices, consequences, close, cold open) for a session';

    public function handle(StoryRepairContext $contextBuilder, StoryRepairPatchApplier $applier): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Choose either --dry-run or --apply.');

            return self::FAILURE;
        }

        $identifier = (string) $this->argument('story');
        $story = Story::query()
            ->with('adaptation')
            ->when(
                is_numeric($identifier),
                fn ($query) => $query->whereKey((int) $identifier),
                fn ($query) => $query->where('slug', $identifier),
            )
            ->first();

        if ($story?->adaptation === null) {
            $this->error('No matching story with an adaptation found.');

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
            $failed = ! $this->repairSession($story, $session, $contextBuilder, $applier) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function repairSession(
        Story $story,
        SessionAdaptation $session,
        StoryRepairContext $contextBuilder,
        StoryRepairPatchApplier $applier,
    ): bool {
        $context = $contextBuilder->build($story, $session);
        $sessionNumber = (int) $session->session_number;

        $this->newLine();
        $this->info("Story repair: {$story->slug}, session {$sessionNumber}");
        $this->line('Protagonist: '.$context['protagonist']);
        $this->line('Scene cards: '.count($context['material']['scene_cards']).'. Source events: '.count($context['source_events']).'.');

        if ($context['material']['scene_cards'] === []) {
            $this->error('This session has no finalized standard cut to review.');

            return false;
        }

        $prompt = view('ai.agents.adaptation.story-repair.prompt', $context)->render();

        if ($this->option('dry-run')) {
            if ($this->option('show-prompt')) {
                $this->line(view('ai.agents.adaptation.story-repair.system-prompt', $context)->render());
                $this->newLine();
                $this->line($prompt);
            }
            $this->info('Dry run only. The model was not called and nothing was saved.');

            return true;
        }

        try {
            $response = (new StoryRepairAgent($context))->prompt($prompt, timeout: 840);
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return false;
        }

        $findings = array_values((array) ($response['findings'] ?? []));
        $review = $applier->validate(
            $session,
            array_values((array) ($response['patches'] ?? [])),
            $context['voice_profile'],
            $contextBuilder,
        );

        $this->report($findings, $review);

        $reportPath = sprintf('story-repairs/%s/session-%d-%s.json', $story->slug, $sessionNumber, now()->format('Ymd-His'));
        Storage::disk('local')->put($reportPath, (string) json_encode([
            'story' => $story->slug,
            'session_number' => $sessionNumber,
            'applied' => (bool) $this->option('apply'),
            'findings' => $findings,
            'accepted_patches' => $review['accepted'],
            'rejected_patches' => $review['rejected'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line("Full report: storage/app/private/{$reportPath}");

        if (! $this->option('apply')) {
            $this->info('Review only. Nothing was saved. Pass --apply to store the accepted patches.');

            return true;
        }

        if ($review['accepted'] === []) {
            $this->info('No accepted patches to store.');

            return true;
        }

        $applier->apply($session, $review['accepted'], $contextBuilder);
        $this->info('Stored '.count($review['accepted'])." patches on session {$sessionNumber}. Rebuild its runtime prompt next.");

        return true;
    }

    /**
     * @param  list<mixed>  $findings
     * @param  array{accepted: list<array<string, string>>, rejected: list<array{patch: array<string, mixed>, reason: string}>}  $review
     */
    private function report(array $findings, array $review): void
    {
        $this->line('Findings: '.count($findings));

        foreach ($findings as $finding) {
            $this->line(sprintf(
                '- [%s/%s] %s: %s',
                (string) data_get($finding, 'severity', '?'),
                (string) data_get($finding, 'category', '?'),
                (string) data_get($finding, 'location', '?'),
                (string) data_get($finding, 'issue', ''),
            ));
        }

        $this->line('Accepted patches: '.count($review['accepted']));

        foreach ($review['accepted'] as $patch) {
            $location = mb_trim($patch['target'].' '.$patch['key']).' '.$patch['path'];
            $this->line("- {$location}");
            $this->line('  was: '.$patch['old_value']);
            $this->line('  now: '.$patch['new_value']);
        }

        foreach ($review['rejected'] as $rejection) {
            $this->warn(sprintf(
                'Rejected %s %s: %s',
                (string) data_get($rejection, 'patch.target', '?'),
                (string) data_get($rejection, 'patch.path', '?'),
                $rejection['reason'],
            ));
        }
    }
}
