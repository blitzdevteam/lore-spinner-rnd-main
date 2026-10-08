<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\VoiceTextMetrics;
use App\Ai\Agents\Adaptation\VoiceLockBookAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Chapter;
use App\Models\Story;
use App\Support\Adaptation\GeneratesValidatedArtifact;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Voice Lock for novels in one pass: the whole book read once by a long-context
 * model, with exact prose statistics measured in PHP. Replaces one call per
 * chapter plus a merge, and judges every ratio and exemplar against the whole
 * book instead of stitching chapter-level estimates.
 *
 * Any failure falls back to the per-chapter Voice Lock, so a boil never stalls.
 */
final class VoiceLockBookJob implements ShouldQueue
{
    use GeneratesValidatedArtifact, Queueable;

    /** Opus 5.5 reads 1M tokens at standard pricing; leave room for instructions and output. */
    public const int MAX_BOOK_CHARS = 2_800_000;

    /** A whole-book read is too expensive to repeat blindly; failure falls back instead. */
    public int $tries = 1;

    /** Stays under the database queue's retry_after (1200 s). */
    public int $timeout = 1_100;

    public function __construct(private Story $story)
    {
        $this->onQueue('adaptation');
    }

    public static function canRead(Story $story): bool
    {
        return (int) $story->chapters()->sum(DB::raw('length(content)')) <= self::MAX_BOOK_CHARS;
    }

    /**
     * The runtime-critical parts every later phase needs, at the sizes the
     * system prompt requires.
     *
     * @param  array<string, mixed>  $profile
     *
     * @throws RuntimeException
     */
    public static function assertComplete(array $profile): void
    {
        $failures = array_filter([
            ($profile['profile_type'] ?? null) !== 'NOVELIST' ? 'profile_type is not NOVELIST' : null,
            count((array) ($profile['voice_anchor'] ?? [])) < 6 ? 'fewer than 6 voice_anchor exemplars' : null,
            count((array) ($profile['anchor_card'] ?? [])) < 8 ? 'fewer than 8 anchor_card commands' : null,
            count((array) ($profile['runtime_self_check'] ?? [])) < 8 ? 'fewer than 8 runtime_self_check steps' : null,
            count((array) data_get($profile, 'author_voice_dna_profile.signature_writing_techniques', [])) < 8 ? 'fewer than 8 signature techniques' : null,
            count((array) data_get($profile, 'author_voice_dna_profile.dialogue_fingerprint_per_character', [])) < 1 ? 'no dialogue fingerprints' : null,
        ]);

        if ($failures !== []) {
            throw new RuntimeException('Whole-book voice profile is incomplete: '.implode('; ', $failures).'.');
        }
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;
        $adaptation->update(['adaptation_status' => AdaptationStatusEnum::VOICE_LOCK]);

        $chapters = $this->story->chapters()->orderBy('position')->get(['id', 'position', 'title', 'content']);
        $formatDetection = (array) ($adaptation->format_detection ?? []);
        $ipAudit = (array) ($adaptation->ip_audit ?? []);
        $metrics = VoiceTextMetrics::measure($chapters->pluck('content')->implode("\n\n"));

        Log::info('voice_lock.book_start', [
            'story_id' => $this->story->id,
            'chapters' => $chapters->count(),
            'words' => $metrics['words_total'],
        ]);

        $response = (new VoiceLockBookAgent(
            detectedFormat: $formatDetection['detected_format'] ?? null,
            formatDetection: $formatDetection,
            ipAudit: $ipAudit,
        ))->prompt(view('ai.agents.adaptation.voice-lock.book-prompt', [
            'title' => $this->story->title,
            'author' => $this->story->creator?->full_name ?? 'Unknown Author',
            'year' => optional($this->story->published_at)->year ?? 'Unknown Year',
            'format' => $formatDetection['detected_format'] ?? 'UNKNOWN',
            'formatDetection' => $formatDetection,
            'ipAudit' => $ipAudit,
            'metrics' => $metrics,
            'chapterCount' => $chapters->count(),
            'chapters' => $chapters->map(fn (Chapter $chapter): array => [
                'position' => $chapter->position,
                'title' => $chapter->title,
                'content' => (string) $chapter->content,
            ])->all(),
        ])->render());

        $profile = $this->completeArtifact($response, 'voice lock');
        self::assertComplete($profile);

        $adaptation->update(['voice_profile' => $profile]);

        Log::info('voice_lock.book_complete', [
            'story_id' => $this->story->id,
            'voice_anchor' => count((array) ($profile['voice_anchor'] ?? [])),
            'anchor_card' => count((array) ($profile['anchor_card'] ?? [])),
            'characters' => count((array) data_get($profile, 'author_voice_dna_profile.dialogue_fingerprint_per_character', [])),
        ]);

        StorySessionMapJob::dispatch($this->story)->onQueue('adaptation');
    }

    public function failed(?Throwable $throwable): void
    {
        Log::warning('voice_lock.book_failed_falling_back_to_chapters', [
            'story_id' => $this->story->id,
            'error' => $throwable?->getMessage(),
        ]);

        VoiceLockChapterJob::dispatchBatch($this->story->fresh());
    }
}
