<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

final class RunAdaptationPipelineJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        private Story $story,
        private bool $force = false,
        private bool $boilerEnabled = false,
    ) {
        $this->onQueue('adaptation');
    }

    public function handle(): void
    {
        $adaptation = $this->story->adaptation;

        if ($adaptation && $adaptation->adaptation_status === AdaptationStatusEnum::COMPLETED) {
            if (! $this->force) {
                return;
            }
        }

        if ($adaptation) {
            DB::transaction(function () use ($adaptation): void {
                $adaptation->sessionAdaptations()->delete();

                // Story already has events() defined as hasManyThrough(Event::class, Chapter::class)
                // in the existing codebase — no new relationship needed.
                $this->story->events()->update(['session_number' => null]);

                $adaptation->update([
                    'ip_trimming' => null,
                    'format_detection' => null,
                    'ip_audit' => null,
                    'voice_profile' => null,
                    'story_session_map' => null,
                    'boiler_enabled' => $this->boilerEnabled,
                    'adaptation_status' => AdaptationStatusEnum::PENDING,
                ]);
            });
        } else {
            $adaptation = StoryAdaptation::create([
                'story_id' => $this->story->id,
                'boiler_enabled' => $this->boilerEnabled,
                'adaptation_status' => AdaptationStatusEnum::PENDING,
            ]);
        }

        // Pipeline Upgrade V2.2 — map/merge architecture:
        //
        //  [IpTrimmingChapterJob × N chapters]   (parallel batch, gpt-5.4-mini per chapter)
        //    → IpTrimmingMergeJob                 (PHP merge + small spine synthesis call)
        //      → FormatDetectionJob
        //        → IpAuditJob
        //          → [VoiceLockChapterJob × N]    (parallel batch, 1A or 1B per chapter)
        //            → VoiceLockMergeJob          (full synthesis call, gpt-5.4)
        //              → StorySessionMapJob       (kicks off per-session batch internally)
        //
        // Each batch step flows to the next via ->finally() or explicit dispatch.
        // The per-session adaptation jobs (entry-point, architecture, choices, consequences,
        // editorial) are dispatched by StorySessionMapJob as a separate batch.

        $chapters = $this->story->chapters()->orderBy('position')->get();

        if ($chapters->isEmpty()) {
            // Chapters not yet extracted — dispatch legacy single-pass chain as fallback.
            // In practice this should not happen for chaos-mode stories.
            Bus::chain([
                new FormatDetectionJob($this->story),
                new IpAuditJob($this->story),
                new StorySessionMapJob($this->story),
            ])->onQueue('adaptation')->dispatch();

            return;
        }

        $adaptation->update(['adaptation_status' => AdaptationStatusEnum::IP_TRIMMING]);

        $storyId = $this->story->id;

        Bus::batch(
            $chapters->map(fn ($ch) => new IpTrimmingChapterJob($this->story, $ch))->all()
        )->onQueue('adaptation')
            ->finally(function () use ($storyId): void {
                $story = Story::findOrFail($storyId);
                IpTrimmingMergeJob::dispatch($story)->onQueue('adaptation');
            })
            ->dispatch();
    }
}
