<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages\Widgets;

use App\Filament\Manager\Resources\PlayerVoiceUsages\Pages\ListPlayerVoiceUsages;
use App\Services\Runtime\PlayerVoiceUsageInsights;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

final class VoiceProviderComparisonChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Voice provider averages';

    protected ?string $description = 'Average billed characters and estimated public PAYG cost per successful request. Filter to similar stories and models for a fair comparison.';

    protected ?string $maxHeight = '360px';

    protected function getTablePage(): string
    {
        return ListPlayerVoiceUsages::class;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $rows = app(PlayerVoiceUsageInsights::class)->providerAverages(
            $this->getPageTableQuery(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Average billable units',
                    'data' => array_column($rows, 'average_billable_units'),
                    'backgroundColor' => 'rgba(14, 165, 233, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 56,
                    'yAxisID' => 'units',
                ],
                [
                    'type' => 'line',
                    'label' => 'Average estimated cost (USD)',
                    'data' => array_column($rows, 'average_cost_usd'),
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.18)',
                    'pointBackgroundColor' => 'rgb(245, 158, 11)',
                    'pointRadius' => 4,
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
            'interaction' => ['intersect' => false, 'mode' => 'index'],
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'units' => [
                    'beginAtZero' => true,
                    'position' => 'left',
                    'title' => ['display' => true, 'text' => 'Average units / request'],
                ],
                'cost' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => ['drawOnChartArea' => false],
                    'title' => ['display' => true, 'text' => 'Average USD / request'],
                ],
            ],
        ];
    }
}
