<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\VoiceLockMergeAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline Upgrade V2 — Voice Lock merge pass.
 *
 * Runs after all VoiceLockChapterJob instances complete. Collects chapter
 * voice fragments from the Laravel cache and makes a single synthesis API
 * call to produce the complete Author Voice DNA Profile (Deliverable 1 schema).
 *
 * After writing voice_profile, dispatches StorySessionMapJob.
 *
 * V2.2 order: FormatDetection → IpAudit → VoiceLock (this job) → StorySessionMap.
 */
final class VoiceLockMergeJob implements ShouldQueue
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
        $chapters = $this->story->chapters()->orderBy('position')->get();

        $adaptation->update(['adaptation_status' => AdaptationStatusEnum::VOICE_LOCK]);

        $voiceFragments = [];
        foreach ($chapters as $chapter) {
            $key = "voice_lock_fragment:{$this->story->id}:{$chapter->id}";
            $fragment = Cache::get($key);
            if ($fragment !== null) {
                $voiceFragments[] = $fragment;
            }
        }

        Log::info('voice_lock.merge_start', [
            'story_id' => $this->story->id,
            'fragments_collected' => count($voiceFragments),
            'chapters_total' => $chapters->count(),
        ]);

        if (empty($voiceFragments)) {
            Log::error('voice_lock.merge_failed: no fragments in cache', ['story_id' => $this->story->id]);
            $adaptation->update(['adaptation_status' => AdaptationStatusEnum::FAILED]);

            throw new \RuntimeException("Voice lock merge: no chapter fragments in cache for story {$this->story->id}");
        }

        usort($voiceFragments, fn ($a, $b) => ($a['chapter_position'] ?? 0) <=> ($b['chapter_position'] ?? 0));

        $ipTrimming = $adaptation->ip_trimming ?? [];
        $formatDetection = $adaptation->format_detection ?? [];
        $ipAudit = $adaptation->ip_audit ?? [];

        $response = (new VoiceLockMergeAgent(
            detectedFormat: $formatDetection['detected_format'] ?? null,
            formatDetection: $formatDetection,
            ipAudit: $ipAudit,
        ))->prompt(
            view('ai.agents.adaptation.voice-lock.merge-prompt', [
                'title' => $this->story->title,
                'author' => $this->story->creator?->full_name ?? 'Unknown Author',
                'year' => optional($this->story->published_at)->year ?? 'Unknown Year',
                'format' => $formatDetection['detected_format'] ?? 'UNKNOWN',
                'formatDetection' => $formatDetection,
                'ipAudit' => $ipAudit,
                'totalChapters' => count($voiceFragments),
                'voiceFragments' => $voiceFragments,
            ])->render()
        );

        $adaptation->update([
            'voice_profile' => $response->toArray(),
        ]);

        foreach ($chapters as $chapter) {
            Cache::forget("voice_lock_fragment:{$this->story->id}:{$chapter->id}");
        }

        Log::info('voice_lock.merge_complete', [
            'story_id' => $this->story->id,
            'profile_type' => $response->toArray()['profile_type'] ?? 'UNKNOWN',
            'techniques' => count($response->toArray()['author_voice_dna_profile']['signature_writing_techniques'] ?? []),
            'ip_specific_bans' => count($response->toArray()['master_rule_1_hard_bans']['ip_specific_bans'] ?? []),
        ]);

        // Continue the adaptation pipeline (V2.2: VoiceLock follows FormatDetection + IpAudit).
        StorySessionMapJob::dispatch($this->story)->onQueue('adaptation');
    }
}
