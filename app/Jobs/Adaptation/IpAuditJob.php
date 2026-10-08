<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\IpAuditAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Pipeline Upgrade V2.2 — IP Audit runs after Format Detection and before Voice Lock.
 * Voice Lock merge prompt requires the Phase 1 scorecard from this job.
 */
final class IpAuditJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 420;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;

        try {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::IP_AUDIT,
            ]);

            $ipTrimming = $adaptation->ip_trimming ?? [];
            $source = ! empty($ipTrimming['trimmed_source_text']['text'])
                ? $ipTrimming['trimmed_source_text']['text']
                : $this->story->getScriptContent();

            $totalLength = mb_strlen($source);
            $pageSize = 8000;

            $openingPages = mb_substr($source, 0, $pageSize);
            $midpoint = (int) ($totalLength / 2) - (int) ($pageSize / 2);
            $midpointPages = mb_substr($source, max(0, $midpoint), $pageSize);
            $closingPages = mb_substr($source, max(0, $totalLength - $pageSize));

            $formatDetection = $adaptation->format_detection;

            $response = (new IpAuditAgent)->prompt(
                view('ai.agents.adaptation.ip-audit.prompt', [
                    'title' => $this->story->title,
                    'format' => $formatDetection['detected_format'] ?? 'UNKNOWN',
                    'openingPages' => $openingPages,
                    'midpointPages' => $midpointPages,
                    'closingPages' => $closingPages,
                    'storySpine' => $ipTrimming['story_spine'] ?? null,
                    'worldRules' => $ipTrimming['world_rules'] ?? null,
                ])->render()
            );

            $adaptation->update([
                'ip_audit' => $response->toArray(),
            ]);

            $this->dispatchVoiceLockChapterBatch();
        } catch (Throwable $throwable) {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::FAILED,
            ]);

            throw $throwable;
        }
    }

    private function dispatchVoiceLockChapterBatch(): void
    {
        $chapters = $this->story->chapters()->orderBy('position')->get();
        $storyId = $this->story->id;

        if ($chapters->isEmpty()) {
            VoiceLockMergeJob::dispatch($this->story)->onQueue('adaptation');

            return;
        }

        Bus::batch(
            $chapters->map(fn ($ch) => new VoiceLockChapterJob($this->story, $ch))->all()
        )->onQueue('adaptation')
            ->finally(function () use ($storyId): void {
                $story = Story::findOrFail($storyId);
                VoiceLockMergeJob::dispatch($story)->onQueue('adaptation');
            })
            ->dispatch();
    }
}
