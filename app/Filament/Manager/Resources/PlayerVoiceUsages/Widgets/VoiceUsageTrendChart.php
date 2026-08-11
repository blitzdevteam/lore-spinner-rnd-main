<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages\Widgets;

use App\Filament\Manager\Resources\PlayerVoiceUsages\Pages\ListPlayerVoiceUsages;
use App\Services\Runtime\PlayerVoiceUsageInsights;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

final class VoiceUsageTrendChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Daily voice consumption';

    protected ?string $description = 'Latest 30 active days. Bars show provider billing units and the line shows estimated public PAYG spend.';

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
        $usage = app(PlayerVoiceUsageInsights::class)->dailyUsage(
            $this->getPageTableQuery(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Billable units',
                    'data' => $usage['billable_units'],
                    'backgroundColor' => 'rgba(124, 58, 237, 0.82)',
                    'borderRadius' => 4,
                    'maxBarThickness' => 42,
                    'yAxisID' => 'units',
                ],
                [
                    'type' => 'line',
                    'label' => 'Estimated cost (USD)',
                    'data' => $usage['estimated_cost_usd'],
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.16)',
                    'pointBackgroundColor' => 'rgb(16, 185, 129)',
                    'pointRadius' => 3,
                    'tension' => 0.25,
                    'yAxisID' => 'cost',
                ],
            ],
            'labels' => $usage['labels'],
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
                    'title' => ['display' => true, 'text' => 'Billable units'],
                ],
                'cost' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => ['drawOnChartArea' => false],
                    'title' => ['display' => true, 'text' => 'Estimated USD'],
                ],
            ],
        ];
    }
}
