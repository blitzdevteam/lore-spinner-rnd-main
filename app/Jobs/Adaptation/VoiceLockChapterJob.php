<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\VoiceLockChapterAgent;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline Upgrade V2 — Voice Lock per-chapter pass.
 *
 * Reads the FULL ORIGINAL chapter content (not the trimmed version) and
 * extracts a compact voice observation fragment. Stores the fragment in
 * the Laravel cache under `voice_lock_fragment:{story_id}:{chapter_id}`.
 *
 * VoiceLockMergeJob collects all fragments and makes a single full synthesis
 * API call to produce the complete Author Voice DNA Profile (Deliverable 1).
 *
 * IMPORTANT: Uses chapter.content (original text), not the ip_trimming output.
 * A spread of up to MAX_SAMPLED_CHAPTERS chapters is analysed (see sample()).
 */
final class VoiceLockChapterJob implements ShouldQueue
{
    use Batchable, Queueable;

    /** An author's voice is consistent across a book; a spread of chapters captures it. */
    public const int MAX_SAMPLED_CHAPTERS = 8;

    public int $tries = 3;

    public int $timeout = 420;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private Chapter $chapter,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * Evenly spaced chapters, always including the first and the last.
     *
     * @param  Collection<int, Chapter>  $chapters
     * @return Collection<int, Chapter>
     */
    public static function sample(Collection $chapters): Collection
    {
        $chapters = $chapters->values();
        $count = $chapters->count();

        if ($count <= self::MAX_SAMPLED_CHAPTERS) {
            return $chapters;
        }

        $step = ($count - 1) / (self::MAX_SAMPLED_CHAPTERS - 1);

        return collect(range(0, self::MAX_SAMPLED_CHAPTERS - 1))
            ->map(fn (int $index): Chapter => $chapters[(int) round($index * $step)])
            ->unique('id')
            ->values();
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $adaptation = $this->story->adaptation;
        $totalChapters = $this->story->chapters()->count();

        Log::info('voice_lock.chapter_start', [
            'story_id' => $this->story->id,
            'chapter_id' => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'chapter_title' => $this->chapter->title,
        ]);

        $detectedFormat = $adaptation?->format_detection['detected_format'] ?? null;

        $response = (new VoiceLockChapterAgent($detectedFormat))->prompt(
            view('ai.agents.adaptation.voice-lock.chapter-prompt', [
                'title' => $this->story->title,
                'author' => $this->story->creator?->full_name ?? 'Unknown Author',
                'year' => optional($this->story->published_at)->year ?? 'Unknown Year',
                'format' => $adaptation?->format_detection['detected_format'] ?? 'UNKNOWN',
                'chapterId' => $this->chapter->id,
                'chapterPosition' => $this->chapter->position,
                'chapterTitle' => $this->chapter->title,
                'totalChapters' => $totalChapters,
                'chapterContent' => $this->chapter->content ?? '',
            ])->render()
        );

        $fragment = $response->toArray();

        Cache::put(
            "voice_lock_fragment:{$this->story->id}:{$this->chapter->id}",
            $fragment,
            now()->addHours(24)
        );

        Log::info('voice_lock.chapter_complete', [
            'story_id' => $this->story->id,
            'chapter_id' => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'techniques_observed' => count($fragment['voice_observations']['signature_techniques_observed'] ?? []),
            'characters_observed' => count($fragment['character_dialogue_observations'] ?? []),
            'ban_candidates' => count($fragment['ip_specific_ban_candidates'] ?? []),
        ]);
    }
}
