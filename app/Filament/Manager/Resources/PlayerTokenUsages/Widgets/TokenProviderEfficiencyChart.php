<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Widgets;

use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ListPlayerTokenUsages;
use App\Services\Runtime\PlayerTokenUsageInsights;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

final class TokenProviderEfficiencyChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Provider averages per request';

    protected ?string $description = 'Reported player requests only. Compare average token mix and stored USD, then filter to similar models and stories. Cost efficiency does not measure response quality.';

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
        $rows = app(PlayerTokenUsageInsights::class)->providerAverages(
            $this->getPageTableQuery(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Average input',
                    'data' => array_column($rows, 'average_input_tokens'),
                    'backgroundColor' => 'rgba(124, 58, 237, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 56,
                    'stack' => 'tokens',
                    'yAxisID' => 'tokens',
                ],
                [
                    'label' => 'Average cached input',
                    'data' => array_column($rows, 'average_cached_input_tokens'),
                    'backgroundColor' => 'rgba(14, 165, 233, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 56,
                    'stack' => 'tokens',
                    'yAxisID' => 'tokens',
                ],
                [
                    'label' => 'Average output',
                    'data' => array_column($rows, 'average_output_tokens'),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 56,
                    'stack' => 'tokens',
                    'yAxisID' => 'tokens',
                ],
                [
                    'type' => 'line',
                    'label' => 'Average cost (USD)',
                    'data' => array_column($rows, 'average_cost_usd'),
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.18)',
                    'pointBackgroundColor' => 'rgb(245, 158, 11)',
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'tension' => 0.25,
                    'yAxisID' => 'cost',
                ],
            ],
            'labels' => array_column($rows, 'label'),
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
                'tokens' => [
                    'beginAtZero' => true,
                    'stacked' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Average tokens / request',
                    ],
                ],
                'cost' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => ['drawOnChartArea' => false],
                    'title' => [
                        'display' => true,
                        'text' => 'Average USD / request',
                    ],
                ],
            ],
        ];
    }
}
