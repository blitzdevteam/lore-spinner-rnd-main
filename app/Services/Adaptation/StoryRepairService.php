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
     */
    public function review(Story $story, SessionAdaptation $session, string $source, array $skipTargets = []): StoryRepairReport
    {
        $context = $this->contextBuilder->build($story, $session, $skipTargets);

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
        );

        return StoryRepairReport::create([
            'session_adaptation_id' => $session->id,
            'source' => $source,
            'status' => StoryRepairReport::STATUS_REVIEWED,
            'findings' => array_values((array) ($response['findings'] ?? [])),
            'patches' => $review['patches'],
            'rebinds' => $review['rebinds'],
            'rejected' => $review['rejected'],
        ]);
    }

    /**
     * Apply the changes a report accepted, re-checked against the material as
     * it stands now. Anything that changed since the review is skipped.
     *
     * @return array{applied_patches: int, applied_rebinds: int, stale: list<array{change: array<string, mixed>, reason: string}>}
     */
    public function apply(Story $story, StoryRepairReport $report): array
    {
        if ($report->status !== StoryRepairReport::STATUS_REVIEWED) {
            throw new RuntimeException("Report {$report->id} is {$report->status}; only a reviewed report can be applied.");
        }

        $session = $report->sessionAdaptation;
        $context = $this->contextBuilder->build($story, $session);
        $current = $this->applier->validate($session, (array) $report->patches, (array) $report->rebinds, $context);

        $backup = $this->applier->apply($session, $current['patches'], $current['rebinds']);

        $report->update([
            'status' => StoryRepairReport::STATUS_APPLIED,
            'backup' => $backup,
            'applied_at' => now(),
        ]);

        return [
            'applied_patches' => count($current['patches']),
            'applied_rebinds' => count($current['rebinds']),
            'stale' => $current['rejected'],
        ];
    }

    public function revert(StoryRepairReport $report): void
    {
        if ($report->status !== StoryRepairReport::STATUS_APPLIED || $report->backup === null) {
            throw new RuntimeException("Report {$report->id} is {$report->status}; only an applied report can be reverted.");
        }

        $newer = StoryRepairReport::query()
            ->where('session_adaptation_id', $report->session_adaptation_id)
            ->where('status', StoryRepairReport::STATUS_APPLIED)
            ->where('applied_at', '>', $report->applied_at)
            ->exists();

        if ($newer) {
            throw new RuntimeException("A later repair was applied to this session after report {$report->id}. Revert that one first.");
        }

        $this->applier->revert($report->sessionAdaptation, $report->backup);
        $report->update(['status' => StoryRepairReport::STATUS_REVERTED, 'reverted_at' => now()]);
    }
}
