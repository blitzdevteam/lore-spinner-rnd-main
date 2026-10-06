<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Enums\BoilerStatus;
use App\Models\Story;
use App\Services\Adaptation\StoryRepairService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The pipeline's story repair pass for one finalized Boiler session.
 *
 * Reviews the stored material against its neighbouring sessions, the source
 * and the author's voice, and applies the validated changes. It is a safety
 * net, never a gate: a failed review is logged and the pipeline carries on
 * with the material as finalized.
 */
final class StoryRepairJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
        private bool $rebuildRuntimePrompt = false,
    ) {
        $this->onQueue('adaptation');
    }

    public function handle(StoryRepairService $repairs): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $session = $this->story->adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->first();

        if ($session === null || ! $this->story->adaptation->boiler_enabled || $session->boiler_status !== BoilerStatus::FINALIZED) {
            return;
        }

        try {
            $report = $repairs->review($this->story, $session, 'pipeline');
            $applied = $repairs->apply($this->story, $report);
        } catch (Throwable $throwable) {
            Log::warning('story_repair.pipeline_review_failed', [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
                'error' => $throwable->getMessage(),
            ]);

            return;
        }

        Log::info('story_repair.pipeline_applied', [
            'story_id' => $this->story->id,
            'session_number' => $this->sessionNumber,
            'report_id' => $report->id,
            'findings' => count((array) $report->findings),
            ...$applied,
            'stale' => count($applied['stale']),
        ]);

        if ($this->rebuildRuntimePrompt && $applied['applied_patches'] + $applied['applied_rebinds'] > 0) {
            RuntimeNarratorAssemblyJob::dispatchSync($this->story, $this->sessionNumber);
        }
    }
}
