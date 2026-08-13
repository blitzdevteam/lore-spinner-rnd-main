<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\IpTrimmingChapterAgent;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline Upgrade V2 — IP Trimming per-chapter pass.
 *
 * Processes one chapter at a time and stores its fragment in the Laravel cache
 * under the key `ip_trimming_fragment:{story_id}:{chapter_id}`.
 *
 * For chapters whose content exceeds CHUNK_SIZE characters the text is split
 * at line boundaries into overlapping chunks. Each chunk is run through the
 * agent independently (using gpt-5.4 for safety), and the resulting fragments
 * are PHP-merged into a single chapter fragment before being stored. This
 * ensures no screenplay content is lost regardless of chapter length.
 *
 * IpTrimmingMergeJob collects all fragments, PHP-merges world_rules /
 * triage_log / conversion_notes / trimmed_text, makes a single small synthesis
 * call for the story_spine, and writes the final package to
 * story_adaptations.ip_trimming.
 *
 * Fragment cache TTL: 24 hours (enough to survive any queue backpressure).
 */
final class IpTrimmingChapterJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /**
     * Default for single-pass chapters. Chunked chapters override this in
     * {@see resolveTimeout()} (~90 s per chunk + headroom).
     *
     * Cloud worker command must use --timeout >= longest job timeout here.
     */
    public int $timeout = 900;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private Chapter $chapter,
    ) {
        $this->onQueue('adaptation');
        $this->timeout = self::resolveTimeout($chapter);
    }

    /**
     * Treasure Island Ch4 (~100 k chars) splits into ~25 chunks; at ~40–50 s
     * per agent call that exceeds a flat 900 s ceiling. Scale timeout to chunk
     * count so long novel chapters can finish in one job.
     */
    public static function resolveTimeout(Chapter $chapter): int
    {
        $chapterLength = mb_strlen($chapter->content ?? '');

        if ($chapterLength <= self::CHUNK_SIZE) {
            return 900;
        }

        $step = self::CHUNK_SIZE - self::CHUNK_OVERLAP;
        $estimatedChunks = (int) max(1, ceil($chapterLength / $step));

        return min(7200, max(900, ($estimatedChunks * 90) + 180));
    }

    /**
     * Maximum characters to send to the model in a single call.
     * Structured output for a 4 k-char chunk peaks around 8 k chars of JSON
     * (~2 k tokens), well within every model's real output ceiling.
     * Chapters shorter than this threshold are processed in a single pass.
     */
    private const int CHUNK_SIZE = 4_000;

    /**
     * Overlap between consecutive chunks, in characters.
     * Keeps scenes that straddle a split boundary visible to both chunks so
     * triage entries are not dropped at the seam.
     */
    private const int CHUNK_OVERLAP = 300;

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $totalChapters = $this->story->chapters()->count();
        $prevChapter = $this->story->chapters()->where('position', $this->chapter->position - 1)->first();
        $nextChapter = $this->story->chapters()->where('position', $this->chapter->position + 1)->first();

        $rawContent = $this->chapter->content ?? '';
        $chapterLength = mb_strlen($rawContent);

        Log::info('ip_trimming.chapter_start', [
            'story_id'         => $this->story->id,
            'chapter_id'       => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'chapter_title'    => $this->chapter->title,
            'chapter_chars'    => $chapterLength,
            'mode'             => $chapterLength > self::CHUNK_SIZE ? 'chunked' : 'single-pass',
        ]);

        $baseViewData = [
            'title'                => $this->story->title,
            'author'               => $this->story->creator?->full_name ?? 'Unknown Author',
            'format'               => $this->story->adaptation?->format_detection['detected_format'] ?? 'UNKNOWN',
            'chapterId'            => $this->chapter->id,
            'chapterPosition'      => $this->chapter->position,
            'chapterTitle'         => $this->chapter->title,
            'totalChapters'        => $totalChapters,
            'previousChapterTitle' => $prevChapter?->title ?? '',
            'nextChapterTitle'     => $nextChapter?->title ?? '',
        ];

        $fragment = $chapterLength > self::CHUNK_SIZE
            ? $this->processInChunks($rawContent, $chapterLength, $baseViewData)
            : $this->processSinglePass($rawContent, $baseViewData);

        Cache::put(
            "ip_trimming_fragment:{$this->story->id}:{$this->chapter->id}",
            $fragment,
            now()->addHours(24)
        );

        Log::info('ip_trimming.chapter_complete', [
            'story_id'         => $this->story->id,
            'chapter_id'       => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'chapter_chars'    => $chapterLength,
            'triage_entries'   => count($fragment['content_triage_log'] ?? []),
            'trimmed_chars'    => mb_strlen($fragment['trimmed_chapter_text'] ?? ''),
        ]);
    }

    // -------------------------------------------------------------------------
    // Processing paths
    // -------------------------------------------------------------------------

    /** @throws Throwable */
    private function processSinglePass(string $content, array $baseViewData): array
    {
        $response = (new IpTrimmingChapterAgent)->prompt(
            view('ai.agents.adaptation.ip-trimming.chapter-prompt', array_merge($baseViewData, [
                'chapterContent' => $content,
            ]))->render(),
        );

        return $response->toArray();
    }

    /** @throws Throwable */
    private function processInChunks(string $rawContent, int $chapterLength, array $baseViewData): array
    {
        $chunks = $this->splitIntoChunks($rawContent);
        $totalChunks = count($chunks);

        Log::info('ip_trimming.chapter_chunked', [
            'story_id'         => $this->story->id,
            'chapter_id'       => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'chapter_chars'    => $chapterLength,
            'total_chunks'     => $totalChunks,
            'chunk_size'       => self::CHUNK_SIZE,
        ]);

        $parts = [];

        foreach ($chunks as $i => $chunk) {
            $chunkNum = $i + 1;

            $response = (new IpTrimmingChapterAgent)->prompt(
                view('ai.agents.adaptation.ip-trimming.chapter-prompt', array_merge($baseViewData, [
                    'chapterContent' => $chunk,
                    'chunkContext'   => "PART {$chunkNum} OF {$totalChunks}",
                ]))->render(),
            );

            $parts[] = $response->toArray();

            Log::info('ip_trimming.chapter_chunk_complete', [
                'story_id'         => $this->story->id,
                'chapter_id'       => $this->chapter->id,
                'chunk'            => "{$chunkNum}/{$totalChunks}",
            ]);
        }

        return $this->mergeChunkParts($parts, $baseViewData);
    }

    // -------------------------------------------------------------------------
    // Chunking helpers
    // -------------------------------------------------------------------------

    /**
     * Split a chapter into overlapping chunks at line boundaries.
     * Splitting on newlines keeps scene headers and dialogue blocks intact so
     * the model never sees a mid-line boundary.
     *
     * @return string[]
     */
    private function splitIntoChunks(string $content): array
    {
        $lines   = explode("\n", $content);
        $chunks  = [];
        $current = '';

        foreach ($lines as $line) {
            $candidate = $current === '' ? $line : ($current . "\n" . $line);

            if (mb_strlen($candidate) > self::CHUNK_SIZE && $current !== '') {
                $chunks[] = $current;
                // Start next chunk with the tail of the current one for context overlap.
                $overlap = mb_substr($current, -self::CHUNK_OVERLAP);
                $current = $overlap . "\n" . $line;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * PHP-merge all per-chunk fragments into a single chapter fragment.
     *
     *   - chapter_id / chapter_position : taken from $baseViewData (authoritative)
     *   - story_spine_fragment.protagonist : first non-empty value across chunks
     *   - story_spine_fragment.dramatic_question : last non-empty value (later
     *     chunks have the most complete picture)
     *   - story_spine_fragment.major_turning_points / irreversible_events :
     *     concatenated across all chunks
     *   - story_spine_fragment.climax_fragment / resolution_fragment : last
     *     non-empty value
     *   - world_rules_fragments.* : union de-duplicated by rule/thing text
     *   - content_triage_log : concatenated in chunk order
     *   - interactive_conversion_notes : concatenated in chunk order
     *   - trimmed_chapter_text : concatenated with a blank-line separator
     */
    private function mergeChunkParts(array $parts, array $baseViewData): array
    {
        $merged = [
            'chapter_id'       => $baseViewData['chapterId'],
            'chapter_position' => $baseViewData['chapterPosition'],

            'story_spine_fragment' => [
                'protagonist'          => '',
                'dramatic_question'    => '',
                'major_turning_points' => [],
                'irreversible_events'  => [],
                'climax_fragment'      => '',
                'resolution_fragment'  => '',
            ],

            'world_rules_fragments' => [
                'physics_technology' => [],
                'creatures_entities' => [],
                'geography_locations' => [],
                'social_systems'      => [],
                'what_cannot_exist'   => [],
            ],

            'content_triage_log'          => [],
            'interactive_conversion_notes' => [],
            'trimmed_chapter_text'         => '',
        ];

        foreach ($parts as $part) {
            $spine = $part['story_spine_fragment'] ?? [];

            // Protagonist: first non-empty wins
            if ($merged['story_spine_fragment']['protagonist'] === '' && !empty($spine['protagonist'])) {
                $merged['story_spine_fragment']['protagonist'] = $spine['protagonist'];
            }

            // Dramatic question: later chunks have more context, so last non-empty wins
            if (!empty($spine['dramatic_question'])) {
                $merged['story_spine_fragment']['dramatic_question'] = $spine['dramatic_question'];
            }

            // Lists: concatenate
            foreach (['major_turning_points', 'irreversible_events'] as $listKey) {
                $merged['story_spine_fragment'][$listKey] = array_merge(
                    $merged['story_spine_fragment'][$listKey],
                    (array) ($spine[$listKey] ?? [])
                );
            }

            // Climax / resolution: last non-empty wins
            if (!empty($spine['climax_fragment'])) {
                $merged['story_spine_fragment']['climax_fragment'] = $spine['climax_fragment'];
            }
            if (!empty($spine['resolution_fragment'])) {
                $merged['story_spine_fragment']['resolution_fragment'] = $spine['resolution_fragment'];
            }

            // World rules: union de-duplicated by lowercase rule/thing text
            $rules = $part['world_rules_fragments'] ?? [];

            foreach (['physics_technology', 'creatures_entities', 'geography_locations', 'social_systems'] as $cat) {
                $existingKeys = array_map(
                    fn ($r) => mb_strtolower(trim((string) ($r['rule'] ?? ''))),
                    $merged['world_rules_fragments'][$cat]
                );
                foreach ((array) ($rules[$cat] ?? []) as $rule) {
                    $key = mb_strtolower(trim((string) ($rule['rule'] ?? '')));
                    if ($key !== '' && !in_array($key, $existingKeys, true)) {
                        $merged['world_rules_fragments'][$cat][] = $rule;
                        $existingKeys[] = $key;
                    }
                }
            }

            $existingThings = array_map(
                fn ($r) => mb_strtolower(trim((string) ($r['thing'] ?? ''))),
                $merged['world_rules_fragments']['what_cannot_exist']
            );
            foreach ((array) ($rules['what_cannot_exist'] ?? []) as $item) {
                $thing = mb_strtolower(trim((string) ($item['thing'] ?? '')));
                if ($thing !== '' && !in_array($thing, $existingThings, true)) {
                    $merged['world_rules_fragments']['what_cannot_exist'][] = $item;
                    $existingThings[] = $thing;
                }
            }

            // Triage log and conversion notes: concatenate in chunk order
            $merged['content_triage_log'] = array_merge(
                $merged['content_triage_log'],
                (array) ($part['content_triage_log'] ?? [])
            );
            $merged['interactive_conversion_notes'] = array_merge(
                $merged['interactive_conversion_notes'],
                (array) ($part['interactive_conversion_notes'] ?? [])
            );

            // Trimmed text: concatenate with blank-line separator
            $trimmed = (string) ($part['trimmed_chapter_text'] ?? '');
            if ($trimmed !== '') {
                $merged['trimmed_chapter_text'] .= ($merged['trimmed_chapter_text'] !== '' ? "\n\n" : '') . $trimmed;
            }
        }

        return $merged;
    }
}
