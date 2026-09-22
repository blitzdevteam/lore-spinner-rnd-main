<?php

declare(strict_types=1);

namespace App\Filament\Manager\Pages;

use App\Filament\Manager\Widgets\Analytics\AnalyticsFunnelWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget;
use App\Models\Story;
use App\Models\User;
use App\Support\Analytics;
use App\Support\AnalyticsFilters;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AnalyticsDashboard extends Page
{
    /**
     * @var array{range: string, from: string|null, to: string|null, user_id: int|null, story_id: int|null}
     */
    public array $filters = [
        'range' => AnalyticsFilters::RANGE_ALL,
        'from' => null,
        'to' => null,
        'user_id' => null,
        'story_id' => null,
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.manager.pages.analytics-dashboard';

    public function mount(): void
    {
        $this->filters = AnalyticsFilters::normalize($this->filters);
        $this->populateCustomDateRangeDefaults();
    }

    public function setDateRange(string $range): void
    {
        $normalized = AnalyticsFilters::normalize(['range' => $range]);

        $this->filters['range'] = $normalized['range'];

        if ($normalized['range'] === AnalyticsFilters::RANGE_CUSTOM) {
            $this->populateCustomDateRangeDefaults();

            return;
        }

        $this->filters['from'] = null;
        $this->filters['to'] = null;
    }

    public function updatedFiltersFrom(mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $this->filters['range'] = AnalyticsFilters::RANGE_CUSTOM;
        }
    }

    public function updatedFiltersTo(mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $this->filters['range'] = AnalyticsFilters::RANGE_CUSTOM;
        }
    }

    public function resetFilters(): void
    {
        $this->filters = AnalyticsFilters::defaults();
    }

    /**
     * @return array{range: string, from: string|null, to: string|null, user_id: int|null, story_id: int|null}
     */
    public function getAnalyticsFilters(): array
    {
        return AnalyticsFilters::normalize($this->filters);
    }

    /**
     * @return array<string, string>
     */
    public function getDateRangeOptions(): array
    {
        return AnalyticsFilters::dateRangeOptions();
    }

    public function playerFilter(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->components([
                $this->scopeSelect('user_id', 'Player', 'All players', Heroicon::User, 'Search name or email', 'No players match that search')
                    ->options(fn (): array => $this->getUserOptions())
                    ->getSearchResultsUsing(fn (string $search): array => $this->getUserOptions($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => $this->userOptionLabel($value)),
            ]);
    }

    public function storyFilter(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->components([
                $this->scopeSelect('story_id', 'Story', 'All stories', Heroicon::BookOpen, 'Search stories', 'No stories match that search')
                    ->options(fn (): array => $this->getStoryOptions())
                    ->getSearchResultsUsing(fn (string $search): array => $this->getStoryOptions($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => $this->storyOptionLabel($value)),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public function getUserOptions(?string $search = null): array
    {
        $search = mb_trim((string) $search);

        return User::query()
            ->whereHas('games', static fn (Builder $query): Builder => $query->where('is_preview', false))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = $this->searchLike($search);

                $query->where(function (Builder $query) use ($like): void {
                    $query->whereLike('email', $like)
                        ->orWhereLike('first_name', $like)
                        ->orWhereLike('last_name', $like)
                        ->orWhereRaw("concat_ws(' ', first_name, last_name) ilike ?", [$like]);
                });
            })
            ->orderBy('email')
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->mapWithKeys(fn (User $user): array => [(string) $user->id => $this->formatUserOption($user)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function getStoryOptions(?string $search = null): array
    {
        $search = mb_trim((string) $search);

        return Story::query()
            ->whereHas('games', static fn (Builder $query): Builder => $query->where('is_preview', false))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereLike('title', $this->searchLike($search));
            })
            ->orderBy('title')
            ->limit(50)
            ->pluck('title', 'id')
            ->mapWithKeys(static fn (mixed $title, mixed $id): array => [(string) $id => (string) $title])
            ->all();
    }

    /**
     * @return array{date: string, user: string, story: string, from: string, to: string, min: string, max: string, is_filtered: bool}
     */
    public function getFilterSummary(): array
    {
        $filters = $this->getAnalyticsFilters();
        [$from, $to] = AnalyticsFilters::dateRange($filters);

        return [
            'date' => AnalyticsFilters::dateRangeLabel($filters),
            'user' => $this->userOptionLabel($filters['user_id']) ?? 'All users',
            'story' => $this->storyOptionLabel($filters['story_id']) ?? 'All stories',
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'min' => Analytics::baselineDateString(),
            'max' => Analytics::asOf()->toDateString(),
            'is_filtered' => $filters['range'] !== AnalyticsFilters::RANGE_ALL
                || $filters['user_id'] !== null
                || $filters['story_id'] !== null,
        ];
    }

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
            $filters = $this->getAnalyticsFilters();
            [$from, $to] = AnalyticsFilters::dateRange($filters);

            $visitsQuery = DB::table('page_views');
            AnalyticsFilters::applyToPageViewQuery($visitsQuery, $filters);
            $visits = $visitsQuery->distinct('session_id')->count('session_id');

            $signups = User::query()
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->when(
                    $filters['user_id'] !== null,
                    static fn (Builder $query): Builder => $query->whereKey($filters['user_id']),
                )
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
            $filters = $this->getAnalyticsFilters();
            [$from, $to] = AnalyticsFilters::dateRange($filters);

            $startsQuery = DB::table('games');
            AnalyticsFilters::applyToGameQuery($startsQuery, $filters);
            $starts = $startsQuery->count();

            // Count distinct games so replay cycles cannot make a nested step
            // larger than Story Starts.
            $session1Query = DB::table('game_session_completions as gsc')
                ->join('games as g', 'g.id', '=', 'gsc.game_id');
            AnalyticsFilters::applyToGameQuery($session1Query, $filters, 'g');
            $session1Completed = $session1Query
                ->whereBetween('gsc.completed_at', [$from, $to])
                ->where('gsc.session_number', 1)
                ->distinct('gsc.game_id')
                ->count('gsc.game_id');

            // Unique games that completed at least once (completion rate denominator)
            $completionQuery = DB::table('game_completions as gc')
                ->join('games as g', 'g.id', '=', 'gc.game_id');
            AnalyticsFilters::applyToGameQuery($completionQuery, $filters, 'g');
            $uniqueCompleted = $completionQuery
                ->whereBetween('gc.completed_at', [$from, $to])
                ->distinct('gc.game_id')
                ->count('gc.game_id');

            // Use distinct games here; the KPI card separately reports replay
            // events, which can legitimately exceed the number of games.
            $replayQuery = DB::table('game_resets as gr')
                ->join('games as g', 'g.id', '=', 'gr.game_id');
            AnalyticsFilters::applyToGameQuery($replayQuery, $filters, 'g');
            $replayedGames = $replayQuery
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
            $filters = $this->getAnalyticsFilters();
            $query = DB::table('stories as s')->join('games as g', 'g.story_id', '=', 's.id');
            AnalyticsFilters::applyToGameQuery($query, $filters, 'g');
            $rows = $query
                ->select('s.title', DB::raw('COUNT(g.id) AS starts'))
                ->groupBy('s.id', 's.title')
                ->orderByDesc('starts')
                ->limit(5)
                ->get()
                ->all();

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
     * @return array<int, array{tone: string, text: string, highlight: string}>
     */
    public function getKeyInsights(): array
    {
        try {
            $filters = $this->getAnalyticsFilters();
            [$from, $to] = AnalyticsFilters::dateRange($filters);

            $startsQuery = DB::table('games');
            AnalyticsFilters::applyToGameQuery($startsQuery, $filters);
            $starts = $startsQuery->count();

            $completionQuery = DB::table('game_completions as gc')
                ->join('games as g', 'g.id', '=', 'gc.game_id');
            AnalyticsFilters::applyToGameQuery($completionQuery, $filters, 'g');
            $completions = $completionQuery
                ->whereBetween('gc.completed_at', [$from, $to])
                ->distinct('gc.game_id')
                ->count('gc.game_id');

            $signups = User::query()
                ->whereBetween('created_at', [$from, $to])
                ->when(
                    $filters['user_id'] !== null,
                    static fn (Builder $query): Builder => $query->whereKey($filters['user_id']),
                )
                ->count();

            $storiesQuery = DB::table('games');
            AnalyticsFilters::applyToGameQuery($storiesQuery, $filters);
            $stories = $storiesQuery->distinct('story_id')->count('story_id');

            $abandonedSql = '
                SELECT COUNT(*) AS cnt FROM (
                    SELECT g.id, GREATEST(g.created_at, MAX(p.created_at), MAX(gsc.started_at), MAX(gsc.completed_at), MAX(gr.created_at)) AS last_active_at
                    FROM games g
                    LEFT JOIN prompts p ON p.game_id = g.id
                    LEFT JOIN game_session_completions gsc ON gsc.game_id = g.id
                    LEFT JOIN game_resets gr ON gr.game_id = g.id
                    WHERE g.is_preview = false
                      AND g.completed_at IS NULL
                      AND g.created_at >= ?
                      AND g.created_at <= ?';
            $abandonedBindings = [$from->toDateTimeString(), $to->toDateTimeString()];

            if ($filters['user_id'] !== null) {
                $abandonedSql .= ' AND g.user_id = ?';
                $abandonedBindings[] = $filters['user_id'];
            }

            if ($filters['story_id'] !== null) {
                $abandonedSql .= ' AND g.story_id = ?';
                $abandonedBindings[] = $filters['story_id'];
            }

            $abandonedSql .= '
                    GROUP BY g.id, g.created_at
                ) sub WHERE last_active_at <= ?';
            $abandonedBindings[] = Analytics::abandonedCutoff()->toDateTimeString();
            $abandoned = (int) (DB::select($abandonedSql, $abandonedBindings)[0]->cnt ?? 0);

            $insights = [];

            // Signup insight
            $insights[] = [
                'text' => 'signed up in the selected period across',
                'highlight' => number_format($signups).' users',
                'tone' => 'primary',
            ];

            // Start insight
            $perStory = $stories > 0 ? round($starts / $stories, 1) : 0;
            $insights[] = [
                'text' => "game starts across {$stories} ".($stories === 1 ? 'story' : 'stories')." ({$perStory} avg per story)",
                'highlight' => number_format($starts),
                'tone' => 'info',
            ];

            // Completion insight
            if ($starts > 0 && $completions === 0) {
                $insights[] = [
                    'text' => 'story starts recorded in the selected period — no completed games recorded yet.',
                    'highlight' => number_format($starts),
                    'tone' => 'warning',
                ];
            } elseif ($starts > 0) {
                $rate = round($completions / $starts * 100, 1);
                $insights[] = [
                    'text' => "completion rate — {$completions} unique games finished",
                    'highlight' => "{$rate}%",
                    'tone' => 'success',
                ];
            }

            // Abandoned insight
            if ($abandoned === 0 && $starts > 0) {
                $insights[] = [
                    'text' => 'abandoned games — all starts are still within the '.Analytics::abandonedLabel().' activity window.',
                    'highlight' => 'Zero',
                    'tone' => 'success',
                ];
            } elseif ($abandoned > 0) {
                $insights[] = [
                    'text' => 'games abandoned (no activity for '.Analytics::abandonedLabel().')',
                    'highlight' => number_format($abandoned),
                    'tone' => 'danger',
                ];
            }

            return $insights;
        } catch (Throwable) {
            return [];
        }
    }

    private function scopeSelect(string $name, string $label, string $placeholder, Heroicon $icon, string $searchPrompt, string $emptyMessage): Select
    {
        return Select::make($name)
            ->label($label)
            ->hiddenLabel()
            ->placeholder($placeholder)
            ->prefixIcon($icon)
            ->searchable()
            ->searchDebounce(300)
            ->searchPrompt($searchPrompt)
            ->noOptionsMessage($emptyMessage)
            ->noSearchResultsMessage($emptyMessage)
            ->preload()
            ->native(false)
            ->live();
    }

    private function userOptionLabel(mixed $userId): ?string
    {
        $id = AnalyticsFilters::normalizeId($userId);

        if ($id === null) {
            return null;
        }

        $user = User::query()->find($id, ['id', 'first_name', 'last_name', 'email']);

        return $user instanceof User ? $this->formatUserOption($user) : null;
    }

    private function storyOptionLabel(mixed $storyId): ?string
    {
        $id = AnalyticsFilters::normalizeId($storyId);

        if ($id === null) {
            return null;
        }

        $title = Story::query()->whereKey($id)->value('title');

        return is_string($title) && $title !== '' ? $title : null;
    }

    private function formatUserOption(User $user): string
    {
        $name = mb_trim($user->full_name);

        return $name !== '' ? $name.' · '.$user->email : $user->email;
    }

    private function searchLike(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }

    private function populateCustomDateRangeDefaults(): void
    {
        if ($this->filters['range'] !== AnalyticsFilters::RANGE_CUSTOM) {
            return;
        }

        [$from, $to] = AnalyticsFilters::dateRange($this->filters);

        $this->filters['from'] ??= $from->toDateString();
        $this->filters['to'] ??= $to->toDateString();
    }
}
