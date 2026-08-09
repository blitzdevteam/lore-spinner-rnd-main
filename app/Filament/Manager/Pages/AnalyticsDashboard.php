<?php

declare(strict_types=1);

namespace App\Filament\Manager\Pages;

use App\Filament\Manager\Widgets\Analytics\AnalyticsFunnelWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget;
use App\Models\User;
use App\Support\Analytics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AnalyticsDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.manager.pages.analytics-dashboard';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            AnalyticsKpiWidget::class,
            AnalyticsFunnelWidget::class,
            AnalyticsRetentionWidget::class,
        ];
    }

    /**
     * Platform acquisition metrics — Visits and Signups.
     *
     * These are INDEPENDENT counts, NOT a linear conversion funnel.
     *
     * - Visits: unique sessions on tracked public pages (/, /stories/*, /creators/*).
     *   Registration page is not tracked, so users can sign up without creating a visit.
     * - Signups: all new user accounts since the baseline, from any route.
     *
     * Because the populations don't nest, no cross-conversion % is shown.
     * Bar widths are relative to the larger of the two.
     *
     * @return array<int, array{label: string, value: int, bar_width: int, color: string, note: string}>
     */
    public function getAcquisitionMetrics(): array
    {
        try {
            $from = Analytics::baseline();
            $to = Analytics::asOf();

            $visits = DB::table('page_views')
                ->where('view_date', '>=', $from->toDateString())
                ->where('view_date', '<=', $to->toDateString())
                ->distinct('session_id')
                ->count('session_id');

            $signups = User::query()
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->count();

            $max = max($visits, $signups, 1);
            $barWidth = static fn (int $value): int => $value === 0
                ? 0
                : max(4, (int) round($value / $max * 100));

            return [
                [
                    'label' => 'Visits',
                    'value' => $visits,
                    'bar_width' => $barWidth($visits),
                    'color' => '#6366f1',
                    'note' => 'Unique browser sessions on /, /stories/*, /creators/*',
                ],
                [
                    'label' => 'Signups',
                    'value' => $signups,
                    'bar_width' => $barWidth($signups),
                    'color' => '#8b5cf6',
                    'note' => 'New registered accounts (includes direct /register arrivals)',
                ],
            ];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Engagement funnel — strictly nested steps anchored on Story Starts.
     *
     * Every step is a provable subset of Story Starts:
     *   Session 1 Completed ⊆ Starts
     *   Story Completions ⊆ Starts
     *   Replayed Games ⊆ Completions
     *
     * Conversion percentages are calculated against Story Starts (the funnel anchor)
     * for Session 1 Completed, Story Completions, and against unique completions for Replays.
     * Bar widths are relative to Story Starts (= 100%).
     *
     * NOTE: Story Starts counts all non-preview games since the baseline, including games
     * by users who registered before the baseline. This is intentional per the spec —
     * Story Starts is a platform-wide metric, not a cohort of the Signups above.
     *
     * @return array<int, array{label: string, value: int, bar_width: int, pct_starts: float|null, pct_prev: float|null, color: string, note: string}>
     */
    public function getEngagementFunnel(): array
    {
        try {
            $from = Analytics::baseline();
            $to = Analytics::asOf();

            $starts = DB::table('games')
                ->where('is_preview', false)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->count();

            // Count distinct games so replay cycles cannot make a nested step
            // larger than Story Starts.
            $session1Completed = DB::table('game_session_completions as gsc')
                ->join('games as g', 'g.id', '=', 'gsc.game_id')
                ->where('g.is_preview', false)
                ->whereBetween('g.created_at', [$from, $to])
                ->whereBetween('gsc.completed_at', [$from, $to])
                ->where('gsc.session_number', 1)
                ->distinct('gsc.game_id')
                ->count('gsc.game_id');

            // Unique games that completed at least once (completion rate denominator)
            $uniqueCompleted = DB::table('game_completions as gc')
                ->join('games as g', 'g.id', '=', 'gc.game_id')
                ->where('g.is_preview', false)
                ->whereBetween('g.created_at', [$from, $to])
                ->whereBetween('gc.completed_at', [$from, $to])
                ->distinct('gc.game_id')
                ->count('gc.game_id');

            // Use distinct games here; the KPI card separately reports replay
            // events, which can legitimately exceed the number of games.
            $replayedGames = DB::table('game_resets as gr')
                ->join('games as g', 'g.id', '=', 'gr.game_id')
                ->where('g.is_preview', false)
                ->whereBetween('g.created_at', [$from, $to])
                ->whereBetween('gr.created_at', [$from, $to])
                ->where('gr.had_prior_completion', true)
                ->distinct('gr.game_id')
                ->count('gr.game_id');

            $pct = static fn (int $value, int $denominator): ?float => $denominator > 0
                ? round($value / $denominator * 100, 1)
                : null;
            $bar = static fn (?float $value): int => $value === null
                ? 0
                : min(100, max(0, (int) round($value)));

            $steps = [
                [
                    'label' => 'Story Starts',
                    'value' => $starts,
                    'bar_width' => $starts > 0 ? 100 : 0,
                    'pct_starts' => null,
                    'pct_prev' => null,
                    'color' => '#3b82f6',
                    'note' => 'Non-preview games created since baseline (includes pre-baseline users)',
                ],
                [
                    'label' => 'Session 1 Completed',
                    'value' => $session1Completed,
                    'bar_width' => $bar($pct($session1Completed, $starts)),
                    'pct_starts' => $pct($session1Completed, $starts),
                    'pct_prev' => $pct($session1Completed, $starts),
                    'color' => '#f59e0b',
                    'note' => 'Games where the player finished Session 1 and advanced to Session 2',
                ],
                [
                    'label' => 'Story Completions',
                    'value' => $uniqueCompleted,
                    'bar_width' => $bar($pct($uniqueCompleted, $starts)),
                    'pct_starts' => $pct($uniqueCompleted, $starts),
                    'pct_prev' => $pct($uniqueCompleted, $session1Completed),
                    'color' => '#22c55e',
                    'note' => 'Distinct games that reached the story end state (game_completions)',
                ],
                [
                    'label' => 'Replayed Games',
                    'value' => $replayedGames,
                    'bar_width' => $bar($pct($replayedGames, $starts)),
                    'pct_starts' => $pct($replayedGames, $starts),
                    'pct_prev' => $pct($replayedGames, $uniqueCompleted),
                    'color' => '#ec4899',
                    'note' => 'Distinct games reset after a prior completion (had_prior_completion=true)',
                ],
            ];

            return $steps;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Top 5 stories by starts, with bar width relative to the leader.
     *
     * @return array<int, array{title: string, starts: int, pct: int}>
     */
    public function getTopStories(): array
    {
        try {
            $rows = DB::select('
                SELECT s.title, COUNT(g.id) AS starts
                FROM stories s
                JOIN games g ON g.story_id = s.id
                WHERE g.is_preview = false
                  AND g.created_at >= ?
                  AND g.created_at <= ?
                GROUP BY s.id, s.title
                ORDER BY starts DESC
                LIMIT 5
            ', [Analytics::baselineDateTimeString(), Analytics::asOfDateTimeString()]);

            if (empty($rows)) {
                return [];
            }

            $max = max(array_column((array) $rows, 'starts')) ?: 1;

            return array_map(fn ($r) => [
                'title' => $r->title,
                'starts' => (int) $r->starts,
                'pct' => max(4, (int) round($r->starts / $max * 100)),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * 3–4 computed insight strings for the dashboard callout panel.
     *
     * @return array<int, array{icon: string, text: string, highlight: string, color: string}>
     */
    public function getKeyInsights(): array
    {
        try {
            $from = Analytics::baseline();
            $to = Analytics::asOf();

            $starts = DB::table('games')->where('is_preview', false)->whereBetween('created_at', [$from, $to])->count();
            $completions = DB::table('game_completions as gc')
                ->join('games as g', 'g.id', '=', 'gc.game_id')
                ->where('g.is_preview', false)
                ->whereBetween('g.created_at', [$from, $to])
                ->whereBetween('gc.completed_at', [$from, $to])
                ->distinct('gc.game_id')
                ->count('gc.game_id');
            $signups = User::query()->where('created_at', '>=', $from)->where('created_at', '<=', $to)->count();
            $stories = DB::table('games')->where('is_preview', false)->whereBetween('created_at', [$from, $to])->distinct('story_id')->count('story_id');
            $abandoned = (int) (DB::select('
                SELECT COUNT(*) AS cnt FROM (
                    SELECT g.id, GREATEST(g.created_at, MAX(p.created_at), MAX(gsc.started_at), MAX(gsc.completed_at), MAX(gr.created_at)) AS last_active_at
                    FROM games g
                    LEFT JOIN prompts p ON p.game_id = g.id
                    LEFT JOIN game_session_completions gsc ON gsc.game_id = g.id
                    LEFT JOIN game_resets gr ON gr.game_id = g.id
                    WHERE g.is_preview = false
                      AND g.completed_at IS NULL
                      AND g.created_at >= ?
                      AND g.created_at <= ?
                    GROUP BY g.id, g.created_at
                ) sub WHERE last_active_at <= ?
            ', [$from->toDateTimeString(), $to->toDateTimeString(), Analytics::abandonedCutoff()->toDateTimeString()])[0]->cnt ?? 0);

            $insights = [];

            // Signup insight
            $insights[] = [
                'icon' => '👥',
                'text' => 'signed up since the baseline across',
                'highlight' => number_format($signups).' users',
                'suffix' => null,
                'color' => '#8b5cf6',
            ];

            // Start insight
            $perStory = $stories > 0 ? round($starts / $stories, 1) : 0;
            $insights[] = [
                'icon' => '▶',
                'text' => "game starts across {$stories} ".($stories === 1 ? 'story' : 'stories')." ({$perStory} avg per story)",
                'highlight' => number_format($starts),
                'suffix' => null,
                'color' => '#3b82f6',
            ];

            // Completion insight
            if ($starts > 0 && $completions === 0) {
                $insights[] = [
                    'icon' => '📖',
                    'text' => 'story starts recorded since the baseline — no completed games recorded yet.',
                    'highlight' => number_format($starts),
                    'suffix' => null,
                    'color' => '#f59e0b',
                ];
            } elseif ($starts > 0) {
                $rate = round($completions / $starts * 100, 1);
                $insights[] = [
                    'icon' => '✅',
                    'text' => "completion rate — {$completions} unique games finished",
                    'highlight' => "{$rate}%",
                    'suffix' => null,
                    'color' => '#22c55e',
                ];
            }

            // Abandoned insight
            if ($abandoned === 0 && $starts > 0) {
                $insights[] = [
                    'icon' => '🟢',
                    'text' => 'abandoned games — all starts are still within the '.Analytics::abandonedLabel().' activity window.',
                    'highlight' => 'Zero',
                    'suffix' => null,
                    'color' => '#22c55e',
                ];
            } elseif ($abandoned > 0) {
                $insights[] = [
                    'icon' => '⚠️',
                    'text' => 'games abandoned (no activity for '.Analytics::abandonedLabel().')',
                    'highlight' => number_format($abandoned),
                    'suffix' => null,
                    'color' => '#ef4444',
                ];
            }

            return $insights;
        } catch (Throwable) {
            return [];
        }
    }
}
