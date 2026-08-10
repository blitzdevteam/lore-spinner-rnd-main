<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Widgets;

use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ListPlayerTokenUsages;
use App\Services\Runtime\PlayerTokenUsageInsights;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TokenUsageOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Usage at a glance';

    protected ?string $description = 'Every value follows the active ledger filters.';

    protected function getTablePage(): string
    {
        return ListPlayerTokenUsages::class;
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $overview = app(PlayerTokenUsageInsights::class)->overview(
            $this->getPageTableQuery(),
        );
        $requests = $overview['requests'];
        $averageCost = $requests > 0
            ? (float) $overview['total_cost_usd'] / $requests
            : 0.0;
        $topSession = $overview['top_session'];
        $usageQuality = $overview['unreported_requests'] > 0
            ? ' · '.number_format($overview['unreported_requests']).' missing provider usage'
            : ' · all provider usage reported';

        return [
            Stat::make('Tracked spend', '$'.$this->formatUsd($overview['total_cost_usd']))
                ->description(
                    number_format($requests)
                    .' requests · $'
                    .$this->formatUsd($averageCost)
                    .' average'
                    .$usageQuality,
                )
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color($overview['unreported_requests'] > 0 ? 'warning' : 'success'),

            Stat::make('Total tokens', number_format($overview['total_tokens']))
                ->description(implode(' · ', [
                    number_format($overview['input_tokens']).' input',
                    number_format($overview['cached_input_tokens']).' cached',
                    number_format($overview['output_tokens']).' output',
                ]))
                ->descriptionIcon(Heroicon::OutlinedCpuChip)
                ->color('primary'),

            Stat::make('Cached input share', number_format($overview['cache_share'], 1).'%')
                ->description(
                    number_format($overview['cached_input_tokens'])
                    .' of '
                    .number_format($overview['total_input_tokens'])
                    .' input tokens',
                )
                ->descriptionIcon(Heroicon::OutlinedBolt)
                ->color('info'),

            Stat::make(
                'Largest session',
                $topSession === null ? 'No usage' : number_format($topSession['total_tokens']),
            )
                ->description($topSession === null
                    ? 'No session matches the current filters.'
                    : $topSession['label']
                        .' · '
                        .number_format($topSession['requests'])
                        .' requests · $'
                        .$this->formatUsd($topSession['total_cost_usd']))
                ->descriptionIcon(Heroicon::OutlinedTrophy)
                ->color($topSession === null ? 'gray' : 'warning'),
        ];
    }

    private function formatUsd(mixed $value): string
    {
        return number_format(is_numeric($value) ? (float) $value : 0.0, 9, '.', '');
    }
}
