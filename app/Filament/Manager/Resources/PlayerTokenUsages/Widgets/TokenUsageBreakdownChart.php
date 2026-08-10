<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Widgets;

use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ListPlayerTokenUsages;
use App\Services\Runtime\PlayerTokenUsageInsights;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

final class TokenUsageBreakdownChart extends ChartWidget
{
    use InteractsWithPageTable;

    public ?string $filter = PlayerTokenUsageInsights::DIMENSION_GAME;

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '360px';

    public function getHeading(): string
    {
        return 'Top consumption by '.$this->dimensionLabel();
    }

    public function getDescription(): string
    {
        return match ($this->dimension()) {
            PlayerTokenUsageInsights::DIMENSION_PLAYER => 'Top 8 players ranked by total tokens in the current filters.',
            PlayerTokenUsageInsights::DIMENSION_SESSION => 'Each game cycle/session is ranked independently, exposing the heaviest session.',
            PlayerTokenUsageInsights::DIMENSION_CHAPTER => 'Apply a Game filter below for game → chapter totals. A request attributed to multiple chapters appears in each chapter.',
            default => 'Top 8 individual games ranked by total tokens in the current filters.',
        };
    }

    protected function getTablePage(): string
    {
        return ListPlayerTokenUsages::class;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, string> */
    protected function getFilters(): array
    {
        return [
            PlayerTokenUsageInsights::DIMENSION_GAME => 'Game',
            PlayerTokenUsageInsights::DIMENSION_PLAYER => 'Player',
            PlayerTokenUsageInsights::DIMENSION_SESSION => 'Session',
            PlayerTokenUsageInsights::DIMENSION_CHAPTER => 'Chapter',
        ];
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $rows = app(PlayerTokenUsageInsights::class)->topConsumers(
            $this->getPageTableQuery(),
            $this->dimension(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Total tokens',
                    'data' => array_column($rows, 'total_tokens'),
                    'backgroundColor' => 'rgba(124, 58, 237, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 32,
                ],
            ],
            'labels' => array_column($rows, 'label'),
        ];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Tokens',
                    ],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }

    private function dimension(): string
    {
        return in_array($this->filter, [
            PlayerTokenUsageInsights::DIMENSION_GAME,
            PlayerTokenUsageInsights::DIMENSION_PLAYER,
            PlayerTokenUsageInsights::DIMENSION_SESSION,
            PlayerTokenUsageInsights::DIMENSION_CHAPTER,
        ], true) ? (string) $this->filter : PlayerTokenUsageInsights::DIMENSION_GAME;
    }

    private function dimensionLabel(): string
    {
        return match ($this->dimension()) {
            PlayerTokenUsageInsights::DIMENSION_PLAYER => 'player',
            PlayerTokenUsageInsights::DIMENSION_SESSION => 'session',
            PlayerTokenUsageInsights::DIMENSION_CHAPTER => 'chapter',
            default => 'game',
        };
    }
}
