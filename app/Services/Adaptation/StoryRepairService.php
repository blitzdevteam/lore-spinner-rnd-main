<?php

declare(strict_types=1);

namespace App\Services\Adaptation;

use App\Ai\Adaptation\StoryRepairContext;
use App\Ai\Adaptation\StoryRepairPatchApplier;
use App\Ai\Agents\Adaptation\StoryRepairAgent;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryRepairReport;
use RuntimeException;

/**
 * Runs a story repair review for one session, stores it as a report, and
 * applies or reverts exactly what that report accepted.
 */
final readonly class StoryRepairService
{
    public function __construct(
        private StoryRepairContext $contextBuilder,
        private StoryRepairPatchApplier $applier,
    ) {}

    /**
     * @param  list<string>  $skipTargets
     * @param  list<string>  $notes  Known defects the review must verify and fix.
     */
    public function review(Story $story, SessionAdaptation $session, string $source, array $skipTargets = [], array $notes = []): StoryRepairReport
    {
        $context = [...$this->contextBuilder->build($story, $session, $skipTargets), 'reviewer_notes' => $notes];

        if ($this->contextBuilder->moments($session)->isEmpty()) {
            throw new RuntimeException("Session {$session->session_number} has no finalized standard cut to review.");
        }

        $response = (new StoryRepairAgent($context))->prompt(
            view('ai.agents.adaptation.story-repair.prompt', $context)->render(),
            timeout: 840,
        );

        $review = $this->applier->validate(
            $session,
            array_values((array) ($response['patches'] ?? [])),
            array_values((array) ($response['rebinds'] ?? [])),
            $context,
            $skipTargets,
            array_values((array) ($response['reorder'] ?? [])),
        );

        return StoryRepairReport::create([
            'session_adaptation_id' => $session->id,
            'source' => $source,
            'status' => StoryRepairReport::STATUS_REVIEWED,
            'findings' => array_values((array) ($response['findings'] ?? [])),
            'patches' => $review['patches'],
            'rebinds' => $review['rebinds'],
            'reorder' => $review['reorder'],
            'rejected' => $review['rejected'],
        ]);
    }

    /**
     * Apply the changes a report accepted, re-checked against the material as
     * it stands now. Anything that changed since the review is skipped.
     *
     * @return array{applied_patches: int, applied_rebinds: int, applied_reorder: bool, stale: list<array{change: array<string, mixed>, reason: string}>}
     */
    public function apply(Story $story, StoryRepairReport $report): array
    {
        if ($report->status !== StoryRepairReport::STATUS_REVIEWED) {
            throw new RuntimeException("Report {$report->id} is {$report->status}; only a reviewed report can be applied.");
        }

        $session = $report->sessionAdaptation;
        $context = $this->contextBuilder->build($story, $session);
        $current = $this->applier->validate($session, (array) $report->patches, (array) $report->rebinds, $context, [], (array) ($report->reorder ?? []));

        if ((array) ($report->reorder ?? []) !== [] && $current['reorder'] === []) {
            throw new RuntimeException("Report {$report->id} reorders the cards, but the reorder no longer validates against the stored material. Nothing was applied. Run a fresh review.");
        }

        $backup = $this->applier->apply($session, $current['patches'], $current['rebinds'], $current['reorder']);

        $report->update([
            'status' => StoryRepairReport::STATUS_APPLIED,
            'backup' => $backup,
            'applied_at' => now(),
        ]);

        return [
            'applied_patches' => count($current['patches']),
            'applied_rebinds' => count($current['rebinds']),
            'applied_reorder' => $current['reorder'] !== [],
            'stale' => $current['rejected'],
        ];
    }

    public function revert(StoryRepairReport $report): void
    {
        if ($report->status !== StoryRepairReport::STATUS_APPLIED || $report->backup === null) {
            throw new RuntimeException("Report {$report->id} is {$report->status}; only an applied report can be reverted.");
        }

        $touched = [$report->session_adaptation_id, ...array_keys((array) ($report->backup['neighbours'] ?? []))];
        $newer = StoryRepairReport::query()
            ->whereIn('session_adaptation_id', $touched)
            ->where('status', StoryRepairReport::STATUS_APPLIED)
            ->where('applied_at', '>', $report->applied_at)
            ->exists();

        if ($newer) {
            throw new RuntimeException("A later repair was applied to a session this report touched after report {$report->id}. Revert that one first.");
        }

        $this->applier->revert($report->sessionAdaptation, $report->backup);
        $report->update(['status' => StoryRepairReport::STATUS_REVERTED, 'reverted_at' => now()]);
    }
}
