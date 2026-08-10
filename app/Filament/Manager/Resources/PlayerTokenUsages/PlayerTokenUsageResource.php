<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages;

use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ListPlayerTokenUsages;
use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ViewPlayerTokenUsage;
use App\Filament\Manager\Resources\PlayerTokenUsages\Schemas\PlayerTokenUsageInfolist;
use App\Filament\Manager\Resources\PlayerTokenUsages\Tables\PlayerTokenUsagesTable;
use App\Models\PlayerTokenUsage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class PlayerTokenUsageResource extends Resource
{
    protected static ?string $model = PlayerTokenUsage::class;

    protected static ?string $slug = 'token-usage';

    protected static ?string $modelLabel = 'token usage record';

    protected static ?string $pluralModelLabel = 'Token Usage';

    protected static ?string $navigationLabel = 'Token Usage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'prompt_reference_id';

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return PlayerTokenUsageInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return PlayerTokenUsagesTable::configure($table);
    }

    /** @return Builder<PlayerTokenUsage> */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'story:id,title',
            'user:id,email,first_name,last_name',
            'game:id,is_preview',
            'playableMoment:id,moment_key',
            'chapters:id,title',
            'events:id,title,chapter_id',
        ]);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPlayerTokenUsages::route('/'),
            'view' => ViewPlayerTokenUsage::route('/{record}'),
        ];
    }
}
