<?php

declare(strict_types=1);

namespace App\Filament\Manager\Widgets\Analytics;

use App\Models\GameCompletion;
use App\Models\GameReset;
use App\Models\GameSessionCompletion;
use App\Models\User;
use App\Support\Analytics;
use App\Support\AnalyticsFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Platform KPI cards.
 *
 * All metrics are bounded by the configured analytics baseline. No data
 * before that date is included regardless of the date filter selected.
 *
 * Story Completions come from game_completions (append-only history).
 * games.completed_at is current/mutable state and is NOT used here.
 *
 * Abandoned = incomplete game with no gameplay activity for the configured
 * inactivity window.
 * Incomplete = started but not yet completed (may still be in-progress).
 * These are two separate metrics with different business meanings.
 */
final class AnalyticsKpiWidget extends StatsOverviewWidget
{
    /**
     * @var array<string, mixed>
     */
    public array $pageFilters = [];

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Platform KPIs';

    protected ?string $description = null;

    protected function getHeading(): ?string
    {
        return 'Platform KPIs ('.AnalyticsFilters::dateRangeLabel($this->pageFilters).')';
    }

    protected function getDescription(): ?string
    {
        $filters = AnalyticsFilters::normalize($this->pageFilters);
        $scope = [];

        if ($filters['user_id'] !== null) {
            $scope[] = 'selected player';
        }

        if ($filters['story_id'] !== null) {
            $scope[] = 'selected story';
        }

        return 'No data before the '.Analytics::baselineLabel().' baseline is included.'
            .($scope === [] ? '' : ' Scope: '.implode(' + ', $scope).'.');
    }

    protected function getStats(): array
    {
        try {
            return $this->buildStats();
        } catch (Throwable) {
            return [
                Stat::make('Analytics unavailable', 'Run migrations')
                    ->description('One or more analytics tables are missing. Run: php artisan migrate')
                    ->color('warning'),
            ];
        }
    }

    private function buildStats(): array
    {
        [$from, $to] = $this->dateRange();
        $filters = AnalyticsFilters::normalize($this->pageFilters);

        $visits = $this->visits($filters);
        $signups = $this->signups($filters, $from, $to);
        $starts = $this->storyStarts($filters);
        $s1 = $this->firstSessionCompletions($filters, $from, $to);
        $completionEvents = $this->completionEvents($filters, $from, $to);
        $uniqueCompleted = $this->uniqueGamesCompleted($filters, $from, $to);
        $incomplete = $this->incompleteStories($filters);
        $abandoned = $this->abandonedStories($filters);
        $returns = $this->returns($filters, $from, $to);
        $replayEvents = $this->replayEvents($filters, $from, $to);
        $uniqueReplayers = $this->uniqueReplayers($filters, $from, $to);

        $incompletePct = $starts > 0
            ? number_format(($incomplete / $starts) * 100, 1)
            : '0.0';

        $abandonedPct = $starts > 0
            ? number_format(($abandoned / $starts) * 100, 1)
            : '0.0';

        $completionRate = $starts > 0
            ? number_format(($uniqueCompleted / $starts) * 100, 1)
            : '0.0';

        return [
            Stat::make('Visits', number_format($visits))
                ->description('Unique browsing windows on public pages (anonymous cookie, not account-based)')
                ->color('gray'),

            Stat::make('Signups', number_format($signups))
                ->description('New user accounts created in the selected period')
                ->color('primary'),

            Stat::make('Story Starts', number_format($starts))
                ->description('Total game plays created (one per play-through, excl. previews). A user may have multiple.')
                ->color('info'),

            Stat::make('Session 1 Completions', number_format($s1))
                ->description('Games where the player finished Session 1 and advanced to Session 2')
                ->color('warning'),

            Stat::make('Story Completions', number_format($uniqueCompleted))
                ->description("{$completionRate}% completion rate · {$completionEvents} completion events · replays counted separately")
                ->color('success'),

            Stat::make('Incomplete Stories', number_format($incomplete))
                ->description("{$incompletePct}% of starts. No completion on record yet. The user may still be actively reading.")
                ->color('warning'),

            Stat::make('Abandoned Stories', number_format($abandoned))
                ->description("{$abandonedPct}% of starts. Incomplete with no gameplay activity for ".Analytics::abandonedLabel().'.')
                ->color('danger'),

            Stat::make('Returning Users', number_format($returns))
                ->description('Users active in this period who also had prior activity before it')
                ->color('primary'),

            Stat::make('Replay Events', number_format($replayEvents))
                ->description(number_format($uniqueReplayers).' unique users replayed (reset after completing a story)')
                ->color('info'),
        ];
    }

    /**
     * Returns the selected range, capped at the analytics baseline and current as-of time.
     *
     * @return array{Carbon, Carbon}
     */
    private function dateRange(): array
    {
        return AnalyticsFilters::dateRange($this->pageFilters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function visits(array $filters): int
    {
        $query = DB::table('page_views');
        AnalyticsFilters::applyToPageViewQuery($query, $filters);

        return $query
            ->distinct('session_id')
            ->count('session_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function signups(array $filters, Carbon $from, Carbon $to): int
    {
        return User::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->when(
                $filters['user_id'] !== null,
                fn ($query) => $query->whereKey($filters['user_id']),
            )
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function storyStarts(array $filters): int
    {
        $query = DB::table('games');
        AnalyticsFilters::applyToGameQuery($query, $filters);

        return $query->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function firstSessionCompletions(array $filters, Carbon $from, Carbon $to): int
    {
        return (int) GameSessionCompletion::query()
            ->whereHas('game', function ($query) use ($filters): void {
                $query
                    ->where('is_preview', false)
                    ->when($filters['user_id'] !== null, fn ($query) => $query->where('user_id', $filters['user_id']))
                    ->when($filters['story_id'] !== null, fn ($query) => $query->where('story_id', $filters['story_id']));
            })
            ->where('session_number', 1)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->distinct('game_id')
            ->count('game_id');
    }

    /**
     * Total completion events — one per completed story cycle (can exceed starts).
     *
     * @param  array<string, mixed>  $filters
     */
    private function completionEvents(array $filters, Carbon $from, Carbon $to): int
    {
        return GameCompletion::query()
            ->whereHas('game', fn ($query) => $query->where('is_preview', false))
            ->when($filters['user_id'] !== null, fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['story_id'] !== null, fn ($query) => $query->where('story_id', $filters['story_id']))
            ->whereBetween('completed_at', [$from, $to])
            ->count();
    }

    /**
     * Distinct games that completed at least once in the period. Used for completion rate.
     *
     * @param  array<string, mixed>  $filters
     */
    private function uniqueGamesCompleted(array $filters, Carbon $from, Carbon $to): int
    {
        return (int) GameCompletion::query()
            ->whereHas('game', fn ($query) => $query
                ->where('is_preview', false)
                ->whereBetween('created_at', [$from, $to]))
            ->when($filters['user_id'] !== null, fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['story_id'] !== null, fn ($query) => $query->where('story_id', $filters['story_id']))
            ->whereBetween('completed_at', [$from, $to])
            ->distinct('game_id')
            ->count('game_id');
    }

    /**
     * Games created since the baseline (within the period) that are still
     * incomplete. A user may still be actively playing — this is current
     * state, not a permanent label.
     *
     * @param  array<string, mixed>  $filters
     */
    private function incompleteStories(array $filters): int
    {
        $query = DB::table('games')->whereNull('completed_at');
        AnalyticsFilters::applyToGameQuery($query, $filters);

        return $query->count();
    }

    /**
     * Incomplete games whose latest gameplay activity is older than the
     * configured inactivity window.
     *
     * Activity sources (all gameplay, no page views):
     *   - games.created_at
     *   - prompts.created_at
     *   - game_session_completions.started_at / completed_at
     *   - game_resets.created_at
     *
     * @param  array<string, mixed>  $filters
     */
    private function abandonedStories(array $filters): int
    {
        [$from, $to] = AnalyticsFilters::dateRange($filters);
        $cutoff = Analytics::abandonedCutoff()->toDateTimeString();

        $sql = '
            SELECT COUNT(*) AS cnt
            FROM (
                SELECT g.id,
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
                  AND g.created_at >= ?
                  AND g.created_at <= ?';
        $bindings = [$from->toDateTimeString(), $to->toDateTimeString()];

        if ($filters['user_id'] !== null) {
            $sql .= ' AND g.user_id = ?';
            $bindings[] = $filters['user_id'];
        }

        if ($filters['story_id'] !== null) {
            $sql .= ' AND g.story_id = ?';
            $bindings[] = $filters['story_id'];
        }

        $sql .= '
                GROUP BY g.id, g.created_at
            ) sub
            WHERE last_active_at <= ?
        ';
        $bindings[] = $cutoff;
        $result = DB::select($sql, $bindings);

        return (int) ($result[0]->cnt ?? 0);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function returns(array $filters, Carbon $from, Carbon $to): int
    {
        $query = DB::table('user_activity_days as a')
            ->where('a.activity_date', '>=', $from->toDateString())
            ->where('a.activity_date', '<=', $to->toDateString())
            ->when($filters['user_id'] !== null, fn ($query) => $query->where('a.user_id', $filters['user_id']))
            ->whereExists(function ($q) use ($filters, $from, $to): void {
                $q->from('user_activity_days as b')
                    ->whereColumn('b.user_id', 'a.user_id')
                    ->where('b.activity_date', '<', $from->toDateString());

                if ($filters['story_id'] !== null) {
                    $q->whereExists(function ($storyQuery) use ($filters, $from, $to): void {
                        $storyQuery->from('games as filtered_games')
                            ->whereColumn('filtered_games.user_id', 'a.user_id')
                            ->where('filtered_games.story_id', $filters['story_id'])
                            ->where('filtered_games.is_preview', false)
                            ->whereBetween('filtered_games.created_at', [$from, $to]);
                    });
                }
            })
            ->distinct('a.user_id')
            ->count('a.user_id');

        return (int) $query;
    }

    /**
     * Total replay events — resets after a prior completion.
     *
     * @param  array<string, mixed>  $filters
     */
    private function replayEvents(array $filters, Carbon $from, Carbon $to): int
    {
        return GameReset::query()
            ->whereHas('game', fn ($query) => $query->where('is_preview', false))
            ->when($filters['user_id'] !== null, fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['story_id'] !== null, fn ($query) => $query->where('story_id', $filters['story_id']))
            ->where('had_prior_completion', true)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    /**
     * Distinct users who replayed at least once in the period.
     *
     * @param  array<string, mixed>  $filters
     */
    private function uniqueReplayers(array $filters, Carbon $from, Carbon $to): int
    {
        return (int) GameReset::query()
            ->whereHas('game', fn ($query) => $query->where('is_preview', false))
            ->when($filters['user_id'] !== null, fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['story_id'] !== null, fn ($query) => $query->where('story_id', $filters['story_id']))
            ->where('had_prior_completion', true)
            ->whereBetween('created_at', [$from, $to])
            ->distinct('user_id')
            ->count('user_id');
    }
}
