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
 * IP Trimming per-chapter pass.
 *
 * The chapter is split into numbered paragraphs and sent whole (or in a few
 * large slices for very long chapters). The model returns triage decisions as
 * paragraph ranges, never the text itself, and the trimmed chapter is rebuilt
 * here from the original paragraphs: preserved prose is verbatim by
 * construction and the response stays small and fast.
 *
 * The fragment is cached under `ip_trimming_fragment:{story_id}:{chapter_id}`
 * for IpTrimmingMergeJob, which writes story_adaptations.ip_trimming.
 */
final class IpTrimmingChapterJob implements ShouldQueue
{
    use Batchable, Queueable;

    /** Characters of chapter text per model call. Most chapters fit in one. */
    private const int SLICE_CHARS = 60_000;

    /** Seconds budgeted per model call, with room for a slow response. */
    private const int SECONDS_PER_CALL = 240;

    /** Stay under the database queue's retry_after (1200 s) so a running job is never handed out twice. */
    private const int MAX_TIMEOUT = 1_100;

    public int $tries = 2;

    public int $timeout = 300;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private Chapter $chapter,
    ) {
        $this->onQueue('adaptation');
        $this->timeout = self::resolveTimeout($chapter);
    }

    public static function resolveTimeout(Chapter $chapter): int
    {
        $calls = (int) max(1, ceil(mb_strlen($chapter->content ?? '') / self::SLICE_CHARS));

        return min(self::MAX_TIMEOUT, $calls * self::SECONDS_PER_CALL + 60);
    }

    /**
     * Paragraphs keyed from 1, split on blank lines, or on single newlines when
     * the text has no blank lines.
     *
     * @return array<int, string>
     */
    public static function paragraphs(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $separator = preg_match('/\n\s*\n/', $content) === 1 ? '/\n\s*\n/' : '/\n/';
        $paragraphs = array_values(array_filter(array_map('rtrim', preg_split($separator, $content) ?: []), static fn (string $p): bool => mb_trim($p) !== ''));

        return $paragraphs === [] ? [] : array_combine(range(1, count($paragraphs)), $paragraphs);
    }

    /**
     * Keep every paragraph not inside a TRIM range verbatim, and replace each
     * run of trimmed paragraphs with one marker. A paragraph the model failed
     * to classify is kept, never lost.
     *
     * @param  array<int, string>  $paragraphs
     * @param  list<array<string, mixed>>  $triage
     * @param  list<array<string, mixed>>  $conversionNotes
     */
    public static function assembleTrimmedText(array $paragraphs, array $triage, array $conversionNotes): string
    {
        $types = collect($conversionNotes)->mapWithKeys(fn ($note): array => [(string) data_get($note, 'reference', '') => (string) data_get($note, 'conversion_type', '')]);
        $trimmedBy = [];

        foreach ($triage as $entry) {
            if (mb_strtoupper((string) data_get($entry, 'classification', '')) !== 'TRIM') {
                continue;
            }

            for ($number = (int) data_get($entry, 'from_paragraph', 0); $number <= (int) data_get($entry, 'to_paragraph', -1); $number++) {
                if (isset($paragraphs[$number]) && ! isset($trimmedBy[$number])) {
                    $trimmedBy[$number] = (string) data_get($entry, 'reference', '');
                }
            }
        }

        $output = [];
        $run = null;

        foreach ($paragraphs as $number => $text) {
            $reference = $trimmedBy[$number] ?? null;

            if ($reference === null || ($run !== null && $run['reference'] !== $reference)) {
                if ($run !== null) {
                    $output[] = self::marker($run['words'], $types[$run['reference']] ?? '', $run['reference']);
                    $run = null;
                }
            }

            if ($reference === null) {
                $output[] = $text;

                continue;
            }

            $run ??= ['reference' => $reference, 'words' => 0];
            $run['words'] += str_word_count($text);
        }

        if ($run !== null) {
            $output[] = self::marker($run['words'], $types[$run['reference']] ?? '', $run['reference']);
        }

        return implode("\n\n", $output);
    }

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

        $paragraphs = self::paragraphs($this->chapter->content ?? '');
        $slices = self::slices($paragraphs);

        Log::info('ip_trimming.chapter_start', [
            'story_id' => $this->story->id,
            'chapter_id' => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'paragraphs' => count($paragraphs),
            'calls' => count($slices),
        ]);

        $baseViewData = [
            'title' => $this->story->title,
            'author' => $this->story->creator?->full_name ?? 'Unknown Author',
            'format' => $this->story->adaptation?->format_detection['detected_format'] ?? 'UNKNOWN',
            'chapterId' => $this->chapter->id,
            'chapterPosition' => $this->chapter->position,
            'chapterTitle' => $this->chapter->title,
            'totalChapters' => $totalChapters,
            'previousChapterTitle' => $prevChapter?->title ?? '',
            'nextChapterTitle' => $nextChapter?->title ?? '',
        ];

        $parts = [];
        foreach ($slices as $index => $slice) {
            $response = (new IpTrimmingChapterAgent)->prompt(
                view('ai.agents.adaptation.ip-trimming.chapter-prompt', [
                    ...$baseViewData,
                    'chapterContent' => collect($slice)->map(fn (string $text, int $number): string => "[P{$number}] {$text}")->implode("\n\n"),
                    'firstParagraph' => array_key_first($slice),
                    'lastParagraph' => array_key_last($slice),
                    'chunkContext' => count($slices) > 1 ? 'PART '.($index + 1).' OF '.count($slices) : null,
                ])->render(),
            );

            $part = $response->toArray();
            $part['trimmed_chapter_text'] = self::assembleTrimmedText($slice, (array) ($part['content_triage_log'] ?? []), (array) ($part['interactive_conversion_notes'] ?? []));
            $parts[] = $part;
        }

        $fragment = $this->mergeChunkParts($parts, $baseViewData);

        Cache::put("ip_trimming_fragment:{$this->story->id}:{$this->chapter->id}", $fragment, now()->addHours(24));

        Log::info('ip_trimming.chapter_complete', [
            'story_id' => $this->story->id,
            'chapter_id' => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'triage_entries' => count($fragment['content_triage_log']),
            'trimmed_chars' => mb_strlen($fragment['trimmed_chapter_text']),
        ]);
    }

    /**
     * @param  array<int, string>  $paragraphs
     * @return list<array<int, string>>
     */
    private static function slices(array $paragraphs): array
    {
        $slices = [];
        $current = [];
        $size = 0;

        foreach ($paragraphs as $number => $text) {
            if ($current !== [] && $size + mb_strlen($text) > self::SLICE_CHARS) {
                $slices[] = $current;
                $current = [];
                $size = 0;
            }

            $current[$number] = $text;
            $size += mb_strlen($text);
        }

        return $current === [] ? $slices : [...$slices, $current];
    }

    private static function marker(int $words, string $type, string $reference): string
    {
        $type = mb_trim($type) !== '' ? mb_strtolower($type) : 'description';

        return "[TRIMMED: {$words} words of {$type}. See Conversion Notes, {$reference}.]";
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
            'chapter_id' => $baseViewData['chapterId'],
            'chapter_position' => $baseViewData['chapterPosition'],

            'story_spine_fragment' => [
                'protagonist' => '',
                'dramatic_question' => '',
                'major_turning_points' => [],
                'irreversible_events' => [],
                'climax_fragment' => '',
                'resolution_fragment' => '',
            ],

            'world_rules_fragments' => [
                'physics_technology' => [],
                'creatures_entities' => [],
                'geography_locations' => [],
                'social_systems' => [],
                'what_cannot_exist' => [],
            ],

            'content_triage_log' => [],
            'interactive_conversion_notes' => [],
            'trimmed_chapter_text' => '',
        ];

        foreach ($parts as $part) {
            $spine = $part['story_spine_fragment'] ?? [];

            // Protagonist: first non-empty wins
            if ($merged['story_spine_fragment']['protagonist'] === '' && ! empty($spine['protagonist'])) {
                $merged['story_spine_fragment']['protagonist'] = $spine['protagonist'];
            }

            // Dramatic question: later chunks have more context, so last non-empty wins
            if (! empty($spine['dramatic_question'])) {
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
            if (! empty($spine['climax_fragment'])) {
                $merged['story_spine_fragment']['climax_fragment'] = $spine['climax_fragment'];
            }
            if (! empty($spine['resolution_fragment'])) {
                $merged['story_spine_fragment']['resolution_fragment'] = $spine['resolution_fragment'];
            }

            // World rules: union de-duplicated by lowercase rule/thing text
            $rules = $part['world_rules_fragments'] ?? [];

            foreach (['physics_technology', 'creatures_entities', 'geography_locations', 'social_systems'] as $cat) {
                $existingKeys = array_map(
                    fn ($r) => mb_strtolower(mb_trim((string) ($r['rule'] ?? ''))),
                    $merged['world_rules_fragments'][$cat]
                );
                foreach ((array) ($rules[$cat] ?? []) as $rule) {
                    $key = mb_strtolower(mb_trim((string) ($rule['rule'] ?? '')));
                    if ($key !== '' && ! in_array($key, $existingKeys, true)) {
                        $merged['world_rules_fragments'][$cat][] = $rule;
                        $existingKeys[] = $key;
                    }
                }
            }

            $existingThings = array_map(
                fn ($r) => mb_strtolower(mb_trim((string) ($r['thing'] ?? ''))),
                $merged['world_rules_fragments']['what_cannot_exist']
            );
            foreach ((array) ($rules['what_cannot_exist'] ?? []) as $item) {
                $thing = mb_strtolower(mb_trim((string) ($item['thing'] ?? '')));
                if ($thing !== '' && ! in_array($thing, $existingThings, true)) {
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
                $merged['trimmed_chapter_text'] .= ($merged['trimmed_chapter_text'] !== '' ? "\n\n" : '').$trimmed;
            }
        }

        return $merged;
    }
}
