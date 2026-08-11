<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages;

use App\Filament\Manager\Resources\PlayerVoiceUsages\Pages\ListPlayerVoiceUsages;
use App\Filament\Manager\Resources\PlayerVoiceUsages\Pages\ViewPlayerVoiceUsage;
use App\Filament\Manager\Resources\PlayerVoiceUsages\Schemas\PlayerVoiceUsageInfolist;
use App\Filament\Manager\Resources\PlayerVoiceUsages\Tables\PlayerVoiceUsagesTable;
use App\Filament\Manager\Resources\PlayerVoiceUsages\Widgets\VoicePricingCatalog;
use App\Filament\Manager\Resources\PlayerVoiceUsages\Widgets\VoiceProviderComparisonChart;
use App\Filament\Manager\Resources\PlayerVoiceUsages\Widgets\VoiceUsageOverview;
use App\Filament\Manager\Resources\PlayerVoiceUsages\Widgets\VoiceUsageTrendChart;
use App\Models\PlayerVoiceUsage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class PlayerVoiceUsageResource extends Resource
{
    protected static ?string $model = PlayerVoiceUsage::class;

    protected static ?string $slug = 'voice-usage';

    protected static ?string $modelLabel = 'voice usage record';

    protected static ?string $pluralModelLabel = 'Voice Usage';

    protected static ?string $navigationLabel = 'Voice Usage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSpeakerWave;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'provider_request_id';

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return PlayerVoiceUsageInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return PlayerVoiceUsagesTable::configure($table);
    }

    /** @return Builder<PlayerVoiceUsage> */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'story:id,title',
            'user:id,email,first_name,last_name',
            'game:id,is_preview',
        ]);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPlayerVoiceUsages::route('/'),
            'view' => ViewPlayerVoiceUsage::route('/{record}'),
        ];
    }

    /** @return array<class-string> */
    public static function getWidgets(): array
    {
        return [
            VoiceUsageOverview::class,
            VoicePricingCatalog::class,
            VoiceUsageTrendChart::class,
            VoiceProviderComparisonChart::class,
        ];
    }
}
