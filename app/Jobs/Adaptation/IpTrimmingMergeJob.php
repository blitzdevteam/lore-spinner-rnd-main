<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\IpTrimmingMergeAgent;
use App\ChaosMode\ChaosStoryConfig;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use App\Support\Adaptation\SessionAdaptationChain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline Upgrade V2 — IP Trimming merge pass.
 *
 * Normal mode (continuePipeline=true):
 *   Collects ALL chapter fragments from the Laravel cache and rebuilds
 *   story_adaptations.ip_trimming from scratch, then dispatches FormatDetectionJob.
 *
 * Repair mode (continuePipeline=false):
 *   Performs an ADDITIVE merge — new chapter fragments (for the chapters that were
 *   just re-processed) are merged INTO the existing ip_trimming DB record instead
 *   of rebuilding from scratch.  This preserves the world_rules, triage_log, and
 *   conversion_notes contributions from already-complete chapters, and only re-runs
 *   the story_spine synthesis with the new chapter spine fragments appended to the
 *   existing story_spine (represented as a pre-merged fragment so the agent sees the
 *   full picture).
 */
final class IpTrimmingMergeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private bool $continuePipeline = true,
        /** @var array<int, int> */
        private array $rerunSessionNumbers = [],
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

        // Collect new fragments from cache (in repair mode these are only the
        // newly re-processed chapters; in normal mode these are all chapters).
        $newFragments = [];
        foreach ($chapters as $chapter) {
            $key = "ip_trimming_fragment:{$this->story->id}:{$chapter->id}";
            $fragment = Cache::get($key);
            if ($fragment !== null) {
                $newFragments[] = $fragment;
                Cache::forget($key);
            }
        }

        Log::info('ip_trimming.merge_start', [
            'story_id' => $this->story->id,
            'fragments_collected' => count($newFragments),
            'chapters_total' => $chapters->count(),
            'repair_mode' => ! $this->continuePipeline,
        ]);

        if (empty($newFragments)) {
            Log::error('ip_trimming.merge_failed: no fragments in cache', ['story_id' => $this->story->id]);
            $adaptation->update(['adaptation_status' => AdaptationStatusEnum::FAILED]);

            return;
        }

        // In repair mode, merge new fragments additively into the existing DB record
        // so that already-complete chapters' world_rules / triage / conversion data
        // is preserved. In normal pipeline mode, rebuild from scratch (all chapters
        // must be in cache).
        if (! $this->continuePipeline) {
            $this->handleRepairMerge($adaptation, $chapters, $newFragments);

            return;
        }

        // --- Normal pipeline mode: rebuild from scratch ---
        if (count($newFragments) < $chapters->count()) {
            Log::error('ip_trimming.pipeline_blocked', [
                'story_id' => $this->story->id,
                'fragments_collected' => count($newFragments),
                'chapters_total' => $chapters->count(),
                'note' => 'IP trimming incomplete — pipeline halted. Run repair to retry missing chapters.',
            ]);
            $adaptation->update(['adaptation_status' => AdaptationStatusEnum::FAILED]);

            return;
        }

        $ipTrimming = $this->buildIpTrimming($newFragments, baseWorldRules: null, baseTriageLog: [], baseConversionNotes: [], baseChapterSegments: [], existingSpine: null);
        $adaptation->update(['ip_trimming' => $ipTrimming]);

        Log::info('ip_trimming.merge_complete', [
            'story_id' => $this->story->id,
            'reduction' => $ipTrimming['trimmed_source_text']['reduction_percentage'],
            'world_rules_count' => array_sum(array_map('count', $ipTrimming['world_rules'])),
            'triage_entries' => count($ipTrimming['content_triage_log']),
            'conversion_notes' => count($ipTrimming['interactive_conversion_notes']),
            'chapter_segments' => count($ipTrimming['trimmed_source_text']['chapter_segments']),
            'repair_mode' => false,
        ]);

        FormatDetectionJob::dispatch($this->story)->onQueue('adaptation');
    }

    /**
     * Repair mode: merge new chapter fragments INTO the existing ip_trimming record.
     * Existing world_rules / triage / conversion data from already-complete chapters
     * is preserved; new chapter data is added on top.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $chapters
     * @param  array<int, array<string, mixed>>  $newFragments
     */
    private function handleRepairMerge($adaptation, $chapters, array $newFragments): void
    {
        $existing = $adaptation->ip_trimming ?? [];

        if (empty($existing)) {
            // No existing data — treat as a fresh (possibly partial) merge.
            Log::warning('ip_trimming.repair_merge_no_existing', [
                'story_id' => $this->story->id,
                'note' => 'No existing ip_trimming found; building from available fragments only.',
            ]);
            $ipTrimming = $this->buildIpTrimming($newFragments, baseWorldRules: null, baseTriageLog: [], baseConversionNotes: [], baseChapterSegments: [], existingSpine: null);
            $adaptation->update(['ip_trimming' => $ipTrimming]);
            $this->logMergeComplete($ipTrimming, repair: true);
            $this->dispatchAfterRepair();

            return;
        }

        // Seed accumulators from the existing DB record.
        $baseWorldRules = (array) ($existing['world_rules'] ?? []);
        $baseTriageLog = (array) ($existing['content_triage_log'] ?? []);
        $baseConversionNotes = (array) ($existing['interactive_conversion_notes'] ?? []);
        $baseChapterSegments = (array) ($existing['trimmed_source_text']['chapter_segments'] ?? []);
        $existingSpine = $existing['story_spine'] ?? null;

        // Identify which chapter IDs are already present so we don't double-count.
        $existingSegmentChapterIds = array_map(fn ($s) => (int) ($s['chapter_id'] ?? 0), $baseChapterSegments);

        // Only include new fragments for chapters not already represented.
        $trulyNewFragments = array_filter(
            $newFragments,
            fn ($f) => ! in_array((int) ($f['chapter_id'] ?? 0), $existingSegmentChapterIds, true)
        );
        $trulyNewFragments = array_values($trulyNewFragments);

        if (empty($trulyNewFragments)) {
            Log::info('ip_trimming.repair_merge_nothing_new', [
                'story_id' => $this->story->id,
                'note' => 'All cache fragments were already present in ip_trimming — skipping update.',
            ]);
            $this->dispatchAfterRepair();

            return;
        }

        Log::info('ip_trimming.repair_merge_additive', [
            'story_id' => $this->story->id,
            'existing_segments' => count($baseChapterSegments),
            'new_fragments_to_add' => count($trulyNewFragments),
        ]);

        $ipTrimming = $this->buildIpTrimming(
            $trulyNewFragments,
            baseWorldRules: $baseWorldRules,
            baseTriageLog: $baseTriageLog,
            baseConversionNotes: $baseConversionNotes,
            baseChapterSegments: $baseChapterSegments,
            existingSpine: $existingSpine,
        );

        $adaptation->update(['ip_trimming' => $ipTrimming]);
        $this->logMergeComplete($ipTrimming, repair: true);
        $this->dispatchAfterRepair();
    }

    /**
     * Build (or rebuild) the ip_trimming payload by merging new fragments into
     * optional base accumulators seeded from the existing DB record.
     *
     * @param  array<int, array<string, mixed>>  $newFragments
     * @param  array<string, list<array<string, mixed>>>|null  $baseWorldRules
     * @param  list<array<string, mixed>>  $baseTriageLog
     * @param  list<array<string, mixed>>  $baseConversionNotes
     * @param  list<array<string, mixed>>  $baseChapterSegments
     * @param  array<string, mixed>|null  $existingSpine
     * @return array<string, mixed>
     */
    private function buildIpTrimming(
        array $newFragments,
        ?array $baseWorldRules,
        array $baseTriageLog,
        array $baseConversionNotes,
        array $baseChapterSegments,
        ?array $existingSpine,
    ): array {
        // Sort new fragments by chapter_position.
        usort($newFragments, fn ($a, $b) => ($a['chapter_position'] ?? 0) <=> ($b['chapter_position'] ?? 0));

        // --- World rules: union (case-insensitive de-dup) into base ---
        $worldRules = $baseWorldRules ?? [
            'physics_technology' => [],
            'creatures_entities' => [],
            'geography_locations' => [],
            'social_systems' => [],
            'what_cannot_exist' => [],
        ];
        foreach ($newFragments as $fragment) {
            foreach (array_keys($worldRules) as $category) {
                $dedupeKey = $category === 'what_cannot_exist' ? 'thing' : 'rule';
                foreach ((array) ($fragment['world_rules_fragments'][$category] ?? []) as $entry) {
                    $text = mb_strtolower(mb_trim((string) ($entry[$dedupeKey] ?? '')));
                    if ($text === '') {
                        continue;
                    }
                    $isDuplicate = false;
                    foreach ($worldRules[$category] as $existing) {
                        if (mb_strtolower(mb_trim((string) ($existing[$dedupeKey] ?? ''))) === $text) {
                            $isDuplicate = true;
                            break;
                        }
                    }
                    if (! $isDuplicate) {
                        $worldRules[$category][] = $entry;
                    }
                }
            }
        }

        // --- Triage log + conversion notes: append new entries ---
        $triageLog = array_merge($baseTriageLog, ...array_map(
            fn ($f) => (array) ($f['content_triage_log'] ?? []),
            $newFragments
        ));
        $conversionNotes = array_merge($baseConversionNotes, ...array_map(
            fn ($f) => (array) ($f['interactive_conversion_notes'] ?? []),
            $newFragments
        ));

        // --- Chapter segments: add new segments, sort all by position ---
        $newSegments = array_map(fn ($f) => [
            'chapter_id' => (int) ($f['chapter_id'] ?? 0),
            'chapter_position' => (int) ($f['chapter_position'] ?? 0),
            'text' => (string) ($f['trimmed_chapter_text'] ?? ''),
        ], $newFragments);

        $allSegments = array_merge($baseChapterSegments, $newSegments);
        usort($allSegments, fn ($a, $b) => ($a['chapter_position'] ?? 0) <=> ($b['chapter_position'] ?? 0));

        $mergedTrimmedText = implode("\n\n", array_map(
            fn ($s) => "--- [CHAPTER {$s['chapter_position']}] ---\n{$s['text']}",
            $allSegments
        ));

        $originalLength = mb_strlen($this->story->getScriptContent());
        $trimmedLength = mb_strlen($mergedTrimmedText);
        $reductionPct = $originalLength > 0
            ? round((1 - $trimmedLength / $originalLength) * 100).'%'
            : '0%';

        // --- Story spine: synthesize from new chapter fragments (+ existing spine
        //     passed as a pre-merged context fragment so the agent sees the full picture) ---
        $spineFragments = array_map(fn ($f) => $f['story_spine_fragment'] ?? [], $newFragments);

        if ($existingSpine !== null) {
            // Prepend existing spine as a synthetic "already-merged" fragment so the
            // agent incorporates it when producing the updated unified spine.
            array_unshift($spineFragments, $existingSpine);
        }

        $chaosConfig = ChaosStoryConfig::find($this->story->slug);

        $spineResponse = (new IpTrimmingMergeAgent)->prompt(
            view('ai.agents.adaptation.ip-trimming.merge-prompt', [
                'title' => $this->story->title,
                'author' => $this->story->creator?->full_name ?? 'Unknown Author',
                'totalChapters' => count($allSegments),
                'spineFragments' => $spineFragments,
                'playableProtagonist' => $chaosConfig['protagonist'] ?? null,
            ])->render()
        );

        $unifiedSpine = $spineResponse->toArray();

        if (! empty($chaosConfig['protagonist'])) {
            $unifiedSpine['protagonist'] = $chaosConfig['protagonist'];
        }

        return [
            'story_spine' => $unifiedSpine,
            'world_rules' => $worldRules,
            'content_triage_log' => $triageLog,
            'interactive_conversion_notes' => $conversionNotes,
            'trimmed_source_text' => [
                'original_length_estimate' => number_format($originalLength).' chars',
                'trimmed_length_estimate' => number_format($trimmedLength).' chars',
                'reduction_percentage' => $reductionPct,
                'text' => $mergedTrimmedText,
                'chapter_segments' => $allSegments,
            ],
        ];
    }

    /** @param  array<string, mixed>  $ipTrimming */
    private function logMergeComplete(array $ipTrimming, bool $repair): void
    {
        Log::info('ip_trimming.merge_complete', [
            'story_id' => $this->story->id,
            'reduction' => $ipTrimming['trimmed_source_text']['reduction_percentage'],
            'world_rules_count' => array_sum(array_map('count', $ipTrimming['world_rules'])),
            'triage_entries' => count($ipTrimming['content_triage_log']),
            'conversion_notes' => count($ipTrimming['interactive_conversion_notes']),
            'chapter_segments' => count($ipTrimming['trimmed_source_text']['chapter_segments']),
            'repair_mode' => $repair,
        ]);
    }

    private function dispatchAfterRepair(): void
    {
        if ($this->rerunSessionNumbers !== []) {
            $storyId = $this->story->id;

            SessionAdaptationChain::dispatchBatch(
                $this->story,
                $this->rerunSessionNumbers,
                fn () => AdaptationStatusReconciliationJob::dispatch(Story::findOrFail($storyId))->onQueue('adaptation'),
            );

            return;
        }

        AdaptationStatusReconciliationJob::dispatch($this->story)->onQueue('adaptation');
    }
}
