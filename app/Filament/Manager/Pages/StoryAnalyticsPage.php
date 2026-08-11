<?php

declare(strict_types=1);

namespace App\Filament\Manager\Pages;

use App\Support\Analytics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-story analytics view.
 *
 * All queries anchor to Analytics::baseline() (config: analytics.start_date).
 *
 * Source of truth mapping:
 *   Starts           → games.created_at WHERE is_preview = false
 *   Completion Events → COUNT(*) from game_completions (all story cycles)
 *   Unique Completed  → COUNT(DISTINCT game_id) — used for completion rate
 *   Replay Events     → game_resets WHERE had_prior_completion = true
 *   Unique Replayers  → COUNT(DISTINCT user_id) from replay resets
 *   Incomplete       → games WHERE completed_at IS NULL (current state)
 *   Abandoned        → incomplete + no gameplay activity for N days
 *   Replays          → game_resets WHERE had_prior_completion = true
 *   Avg Session      → game_session_completions (completed_at - started_at)
 *   Avg Completion   → game_completions.completed_at - session_1.started_at
 *   Drop-off Session → session with highest (reached - completed) per story
 *   Reached S{N}     → distinct games that started session N
 */
final class StoryAnalyticsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Story Analytics';

    protected static ?string $title = 'Story Analytics';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.manager.pages.story-analytics';

    /**
     * Per-story aggregate metrics.
     *
     * @return Collection<int, object>
     */
    public function getStoryMetrics(): Collection
    {
        $baseline = Analytics::baselineDateTimeString();
        $asOf = Analytics::asOfDateTimeString();

        $metrics = collect(DB::select('
            SELECT
                s.id,
                s.title,
                s.slug,
                COALESCE(g.starts, 0)               AS starts,
                COALESCE(gc.events, 0)               AS completion_events,
                COALESCE(gc_u.unique_completed, 0) AS unique_completed,
                COALESCE(gr.events, 0)               AS replay_events,
                COALESCE(gr_u.unique_replayers, 0) AS unique_replayers,
                COALESCE(inc.incomplete, 0)          AS incomplete,
                gsc_dur.avg_minutes,
                avg_ct.avg_completion_minutes
            FROM stories s

            LEFT JOIN (
                SELECT g.story_id, COUNT(*) AS starts
                FROM games g
                WHERE g.is_preview = false
                  AND g.created_at BETWEEN ? AND ?
                GROUP BY g.story_id
            ) g ON g.story_id = s.id

            LEFT JOIN (
                SELECT gc.story_id, COUNT(*) AS events
                FROM game_completions gc
                JOIN games g ON g.id = gc.game_id
                WHERE g.is_preview = false
                  AND gc.completed_at BETWEEN ? AND ?
                GROUP BY gc.story_id
            ) gc ON gc.story_id = s.id

            LEFT JOIN (
                SELECT gc.story_id, COUNT(DISTINCT gc.game_id) AS unique_completed
                FROM game_completions gc
                JOIN games g ON g.id = gc.game_id
                WHERE g.is_preview = false
                  AND g.created_at BETWEEN ? AND ?
                  AND gc.completed_at BETWEEN ? AND ?
                GROUP BY gc.story_id
            ) gc_u ON gc_u.story_id = s.id

            LEFT JOIN (
                SELECT gr.story_id, COUNT(*) AS events
                FROM game_resets gr
                JOIN games g ON g.id = gr.game_id
                WHERE g.is_preview = false
                  AND gr.had_prior_completion = true
                  AND gr.created_at BETWEEN ? AND ?
                GROUP BY gr.story_id
            ) gr ON gr.story_id = s.id

            LEFT JOIN (
                SELECT gr.story_id, COUNT(DISTINCT gr.user_id) AS unique_replayers
                FROM game_resets gr
                JOIN games g ON g.id = gr.game_id
                WHERE g.is_preview = false
                  AND g.created_at BETWEEN ? AND ?
                  AND gr.had_prior_completion = true
                  AND gr.created_at BETWEEN ? AND ?
                GROUP BY gr.story_id
            ) gr_u ON gr_u.story_id = s.id

            LEFT JOIN (
                SELECT g.story_id, COUNT(*) AS incomplete
                FROM games g
                WHERE g.is_preview = false
                  AND g.created_at BETWEEN ? AND ?
                  AND g.completed_at IS NULL
                GROUP BY g.story_id
            ) inc ON inc.story_id = s.id

            LEFT JOIN (
                SELECT gsc.story_id,
                    ROUND(
                        AVG(EXTRACT(EPOCH FROM (gsc.completed_at - gsc.started_at)) / 60)::numeric,
                        1
                    ) AS avg_minutes
                FROM game_session_completions gsc
                JOIN games g ON g.id = gsc.game_id
                WHERE g.is_preview = false
                  AND g.created_at BETWEEN ? AND ?
                  AND gsc.started_at BETWEEN ? AND ?
                  AND gsc.completed_at IS NOT NULL
                  AND gsc.completed_at >= gsc.started_at
                GROUP BY gsc.story_id
            ) gsc_dur ON gsc_dur.story_id = s.id

            LEFT JOIN (
                SELECT gc2.story_id,
                    ROUND(
                        AVG(
                            EXTRACT(EPOCH FROM (gc2.completed_at - s1.started_at)) / 60
                        )::numeric,
                        1
                    ) AS avg_completion_minutes
                FROM game_completions gc2
                JOIN games g2
                    ON g2.id = gc2.game_id
                JOIN game_session_completions s1
                    ON  s1.game_id            = gc2.game_id
                    AND s1.story_cycle_number = gc2.story_cycle_number
                    AND s1.session_number     = 1
                WHERE g2.is_preview = false
                  AND g2.created_at BETWEEN ? AND ?
                  AND gc2.completed_at BETWEEN ? AND ?
                  AND s1.started_at BETWEEN ? AND ?
                  AND gc2.completed_at >= s1.started_at
                GROUP BY gc2.story_id
            ) avg_ct ON avg_ct.story_id = s.id

            WHERE g.starts IS NOT NULL
            ORDER BY g.starts DESC NULLS LAST
        ', [
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
            $baseline, $asOf,
        ]));

        $dropOff = $this->dropOffSessions();
        $abandoned = $this->abandonedByStory();

        return $metrics->map(function (object $row) use ($dropOff, $abandoned): object {
            $starts = (int) $row->starts;
            $uniqueCompleted = (int) $row->unique_completed;
            $incomplete = (int) $row->incomplete;
            $uniqueReplayers = (int) $row->unique_replayers;
            $row->abandoned = $abandoned[$row->id] ?? 0;

            $row->completion_rate = $starts > 0 ? round(($uniqueCompleted / $starts) * 100, 1) : null;
            $row->incomplete_rate = $starts > 0 ? round(($incomplete / $starts) * 100, 1) : null;
            $row->abandoned_rate = $starts > 0 ? round(($row->abandoned / $starts) * 100, 1) : null;
            $row->replay_rate = $uniqueCompleted > 0
                ? round(($uniqueReplayers / $uniqueCompleted) * 100, 1)
                : null;
            $row->drop_off_session = $dropOff[$row->id] ?? null;

            return $row;
        });
    }

    /**
     * Content progression funnel per story: Started → Reached S2 → S3 → … → Completed.
     *
     * "Reached S{N}" = distinct games that started session N (started_at IS NOT NULL).
     * Aggregates across all story cycles for a game.
     *
     * @return Collection<int, object{story_id: int, title: string, starts: int, completions: int, reached: array<int, int>}>
     */
    public function getStoryProgression(): Collection
    {
        $baseline = Analytics::baselineDateTimeString();
        $asOf = Analytics::asOfDateTimeString();

        $starts = collect(DB::select('
            SELECT s.id AS story_id, s.title, COUNT(g.id) AS starts
            FROM stories s
            INNER JOIN games g ON g.story_id = s.id
            WHERE g.is_preview = false
              AND g.created_at BETWEEN ? AND ?
            GROUP BY s.id, s.title
            ORDER BY starts DESC
        ', [$baseline, $asOf]))->keyBy('story_id');

        $completions = collect(DB::select('
            SELECT gc.story_id, COUNT(DISTINCT gc.game_id) AS completions
            FROM game_completions gc
            INNER JOIN games g ON g.id = gc.game_id
            WHERE g.is_preview = false
              AND g.created_at BETWEEN ? AND ?
              AND gc.completed_at BETWEEN ? AND ?
            GROUP BY gc.story_id
        ', [$baseline, $asOf, $baseline, $asOf]))->keyBy('story_id');

        $reached = collect(DB::select('
            SELECT g.story_id, gsc.session_number, COUNT(DISTINCT g.id) AS reached
            FROM games g
            INNER JOIN game_session_completions gsc ON gsc.game_id = g.id
            WHERE g.is_preview = false
              AND g.created_at BETWEEN ? AND ?
              AND gsc.started_at IS NOT NULL
              AND gsc.started_at BETWEEN ? AND ?
              AND gsc.session_number > 1
            GROUP BY g.story_id, gsc.session_number
            ORDER BY g.story_id, gsc.session_number
        ', [$baseline, $asOf, $baseline, $asOf]));

        $reachedByStory = $reached->groupBy('story_id');

        return $starts->map(function (object $row) use ($completions, $reachedByStory): object {
            $storyId = (int) $row->story_id;
            $sessions = [];

            foreach ($reachedByStory->get($storyId, collect()) as $session) {
                $sessions[(int) $session->session_number] = (int) $session->reached;
            }

            ksort($sessions);

            $row->completions = (int) ($completions->get($storyId)?->completions ?? 0);
            $row->starts = (int) $row->starts;
            $row->reached = $sessions;

            return $row;
        })->values();
    }

    /**
     * Per-story session funnels with avg and median duration.
     * Aggregates across all story cycles.
     *
     * @return Collection<int, Collection<int, object>>
     */
    public function getSessionFunnels(): Collection
    {
        $baseline = Analytics::baselineDateTimeString();
        $asOf = Analytics::asOfDateTimeString();

        // FILTER on aggregates is avoided for broad PostgreSQL compatibility.
        // CTEs split reached/avg from median (PERCENTILE_CONT rejects FILTER on
        // ordered-set aggregates, and casts must not be placed before FILTER).
        $rows = collect(DB::select('
            WITH session_stats AS (
                SELECT
                    gsc.story_id,
                    gsc.session_number,
                    COUNT(*)                AS reached,
                    COUNT(gsc.completed_at) AS completed_cnt,
                    ROUND(
                        AVG(
                            CASE WHEN gsc.completed_at IS NOT NULL
                                 THEN EXTRACT(EPOCH FROM (gsc.completed_at - gsc.started_at)) / 60
                            END
                        )::numeric, 1
                    ) AS avg_minutes
                FROM game_session_completions gsc
                JOIN games g ON g.id = gsc.game_id
                WHERE g.is_preview = false
                  AND g.created_at BETWEEN ? AND ?
                  AND gsc.started_at BETWEEN ? AND ?
                GROUP BY gsc.story_id, gsc.session_number
            ),
            session_medians AS (
                SELECT
                    gsc.story_id,
                    gsc.session_number,
                    ROUND(
                        (PERCENTILE_CONT(0.5) WITHIN GROUP (
                            ORDER BY EXTRACT(EPOCH FROM (gsc.completed_at - gsc.started_at)) / 60
                        ))::numeric, 1
                    ) AS median_minutes
                FROM game_session_completions gsc
                JOIN games g ON g.id = gsc.game_id
                WHERE g.is_preview = false
                  AND g.created_at BETWEEN ? AND ?
                  AND gsc.started_at IS NOT NULL
                  AND gsc.completed_at IS NOT NULL
                  AND gsc.started_at BETWEEN ? AND ?
                GROUP BY gsc.story_id, gsc.session_number
            )
            SELECT
                s.id         AS story_id,
                s.title      AS story_title,
                ss.session_number,
                ss.reached,
                ss.completed_cnt,
                ss.avg_minutes,
                sm.median_minutes
            FROM stories s
            JOIN session_stats ss ON ss.story_id = s.id
            LEFT JOIN session_medians sm
                ON  sm.story_id       = ss.story_id
                AND sm.session_number = ss.session_number
            ORDER BY s.id, ss.session_number
        ', [$baseline, $asOf, $baseline, $asOf, $baseline, $asOf, $baseline, $asOf]));

        return $rows
            ->groupBy('story_id')
            ->map(fn (Collection $sessions) => $sessions->map(function (object $row): object {
                $reached = (int) $row->reached;
                $completed = (int) $row->completed_cnt;
                $row->dropped = $reached - $completed;
                $row->completion_pct = $reached > 0
                    ? round(($completed / $reached) * 100, 1)
                    : null;

                return $row;
            }));
    }

    /**
     * @return array<int, int> story_id => session_number
     */
    public function dropOffSessions(): array
    {
        $baseline = Analytics::baselineDateTimeString();
        $asOf = Analytics::asOfDateTimeString();

        $rows = DB::select('
            SELECT DISTINCT ON (story_id)
                story_id,
                session_number
            FROM (
                SELECT
                    gsc.story_id,
                    gsc.session_number,
                    COUNT(*)                       AS reached,
                    COUNT(gsc.completed_at)            AS completed_count,
                    COUNT(*) - COUNT(gsc.completed_at) AS dropped
                FROM game_session_completions gsc
                JOIN games g ON g.id = gsc.game_id
                WHERE g.is_preview = false
                  AND g.created_at BETWEEN ? AND ?
                  AND gsc.started_at IS NOT NULL
                  AND gsc.started_at BETWEEN ? AND ?
                GROUP BY gsc.story_id, gsc.session_number
            ) sub
            WHERE dropped > 0
            ORDER BY story_id, dropped DESC, session_number ASC
        ', [$baseline, $asOf, $baseline, $asOf]);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->story_id] = (int) $row->session_number;
        }

        return $result;
    }

    /**
     * @return array<int, int> story_id => abandoned_count
     */
    private function abandonedByStory(): array
    {
        $baseline = Analytics::baselineDateTimeString();
        $asOf = Analytics::asOfDateTimeString();
        $cutoff = Analytics::abandonedCutoff()->toDateTimeString();

        $rows = DB::select('
            SELECT story_id, COUNT(*) AS cnt
            FROM (
                SELECT g.id, g.story_id,
                    GREATEST(
                        g.created_at,
                        MAX(p.created_at),
                        MAX(gsc.started_at),
                        MAX(gsc.completed_at),
                        MAX(gr.created_at)
                    ) AS last_active_at
                FROM games g
                LEFT JOIN prompts p ON p.game_id = g.id
                LEFT JOIN game_session_completions gsc ON gsc.game_id = g.id
                LEFT JOIN game_resets gr ON gr.game_id = g.id
                WHERE g.is_preview = false
                  AND g.completed_at IS NULL
                  AND g.created_at BETWEEN ? AND ?
                GROUP BY g.id, g.created_at, g.story_id
            ) sub
            WHERE last_active_at <= ?
            GROUP BY story_id
        ', [$baseline, $asOf, $cutoff]);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->story_id] = (int) $row->cnt;
        }

        return $result;
    }
}
