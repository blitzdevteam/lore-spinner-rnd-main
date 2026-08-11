<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages\Widgets;

use App\Filament\Manager\Resources\PlayerVoiceUsages\Pages\ListPlayerVoiceUsages;
use App\Services\Runtime\PlayerVoiceUsageInsights;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class VoiceUsageOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Voice usage at a glance';

    protected ?string $description = 'Successful player narration generations under the active ledger filters.';

    protected function getTablePage(): string
    {
        return ListPlayerVoiceUsages::class;
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $overview = app(PlayerVoiceUsageInsights::class)->overview(
            $this->getPageTableQuery(),
        );
        $requests = $overview['requests'];
        $pricingDescription = $overview['unpriced_requests'] > 0
            ? number_format($overview['unpriced_requests']).' requests missing a pricing snapshot'
            : 'All requests have pricing snapshots';

        return [
            Stat::make('Estimated spend', '$'.$this->formatUsd($overview['total_cost_usd']))
                ->description(number_format($requests).' requests · '.$pricingDescription)
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color($overview['unpriced_requests'] > 0 ? 'warning' : 'success'),

            Stat::make('Billable units', number_format($overview['billable_units']))
                ->description(number_format($overview['input_characters']).' input characters')
                ->descriptionIcon(Heroicon::OutlinedSpeakerWave)
                ->color('primary'),

            Stat::make('Average per request', number_format($overview['average_billable_units']))
                ->description('$'.$this->formatUsd($overview['average_cost_usd']).' estimated average')
                ->descriptionIcon(Heroicon::OutlinedScale)
                ->color('info'),

            Stat::make('Average provider latency', number_format($overview['average_latency_ms']).' ms')
                ->description($this->formatBytes($overview['audio_bytes']).' generated audio')
                ->descriptionIcon(Heroicon::OutlinedBolt)
                ->color('warning'),
        ];
    }

    private function formatUsd(mixed $value): string
    {
        return number_format(is_numeric($value) ? (float) $value : 0.0, 9, '.', '');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1_048_576) {
            return number_format($bytes / 1_024, 1).' KB';
        }

        return number_format($bytes / 1_048_576, 2).' MB';
    }
}
