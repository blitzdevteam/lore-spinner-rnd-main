<?php

declare(strict_types=1);

namespace App\Filament\Manager\Widgets\Analytics;

use App\Support\Analytics;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Conversion funnel bar chart.
 *
 * This chart contains only nested gameplay metrics. Visits and signups are
 * intentionally kept out because they are independent populations, not
 * provable subsets of story starts.
 */
final class AnalyticsFunnelWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Engagement Funnel';

    protected ?string $description = null;

    protected ?string $maxHeight = '340px';

    public function getDescription(): string
    {
        return 'Nested gameplay progression from story start to replayed game. Date range is capped at the '
            .Analytics::baselineLabel()
            .' baseline.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            'all' => 'All time (since '.Analytics::baselineLabel().')',
        ];
    }

    protected function getData(): array
    {
        try {
            [$from, $to] = $this->dateRange();

            $starts = DB::table('games')
                ->where('is_preview', false)
                ->whereBetween('created_at', [$from, $to])
                ->count();

            $s1Completions = DB::table('game_session_completions as gsc')
                ->join('games as g', 'g.id', '=', 'gsc.game_id')
                ->where('g.is_preview', false)
                ->whereBetween('g.created_at', [$from, $to])
                ->whereBetween('gsc.completed_at', [$from, $to])
                ->where('gsc.session_number', 1)
                ->distinct('gsc.game_id')
                ->count('gsc.game_id');

            $storyCompletions = DB::table('game_completions as gc')
                ->join('games as g', 'g.id', '=', 'gc.game_id')
                ->where('g.is_preview', false)
                ->whereBetween('g.created_at', [$from, $to])
                ->whereBetween('gc.completed_at', [$from, $to])
                ->distinct('gc.game_id')
                ->count('gc.game_id');

            $replayedGames = DB::table('game_resets as gr')
                ->join('games as g', 'g.id', '=', 'gr.game_id')
                ->where('g.is_preview', false)
                ->whereBetween('g.created_at', [$from, $to])
                ->whereBetween('gr.created_at', [$from, $to])
                ->where('gr.had_prior_completion', true)
                ->distinct('gr.game_id')
                ->count('gr.game_id');

            $steps = [
                'Story Starts' => $starts,
                'Session 1 Done' => $s1Completions,
                'Story Complete' => $storyCompletions,
                'Replayed Games' => $replayedGames,
            ];
        } catch (Throwable) {
            return [
                'datasets' => [['label' => 'Unavailable', 'data' => []]],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Games',
                    'data' => array_values($steps),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                    ],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_keys($steps),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => ['beginAtZero' => true, 'grid' => ['display' => true]],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function dateRange(): array
    {
        $baseline = Analytics::baseline();
        $to = Analytics::asOf();

        $days = match ($this->filter) {
            '7' => 7,
            '90' => 90,
            'all' => null,
            default => 30,
        };

        $from = $days !== null
            ? Carbon::now()->subDays($days)->startOfDay()->max($baseline)
            : $baseline;

        return [$from, $to];
    }
}
