<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Widgets;

use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ListPlayerTokenUsages;
use App\Services\Runtime\PlayerTokenUsageInsights;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

final class TokenUsageTrendChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Daily token mix';

    protected ?string $description = 'Latest 30 active days in the current ledger filters.';

    protected ?string $maxHeight = '360px';

    protected function getTablePage(): string
    {
        return ListPlayerTokenUsages::class;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $mix = app(PlayerTokenUsageInsights::class)->dailyTokenMix(
            $this->getPageTableQuery(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Input',
                    'data' => $mix['input_tokens'],
                    'backgroundColor' => 'rgba(124, 58, 237, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 40,
                ],
                [
                    'label' => 'Cached input',
                    'data' => $mix['cached_input_tokens'],
                    'backgroundColor' => 'rgba(14, 165, 233, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 40,
                ],
                [
                    'label' => 'Output',
                    'data' => $mix['output_tokens'],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 40,
                ],
            ],
            'labels' => $mix['labels'],
        ];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
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
                    'stacked' => true,
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'stacked' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Tokens',
                    ],
                ],
            ],
        ];
    }
}
