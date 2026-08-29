<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Worlds;

use App\Filament\Manager\Resources\Worlds\Pages\CreateWorld;
use App\Filament\Manager\Resources\Worlds\Pages\EditWorld;
use App\Filament\Manager\Resources\Worlds\Pages\ListWorlds;
use App\Filament\Manager\Resources\Worlds\Schemas\WorldForm;
use App\Filament\Manager\Resources\Worlds\Tables\WorldsTable;
use App\Models\World;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class WorldResource extends Resource
{
    protected static ?string $model = World::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Worlds';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): string
    {
        return (string) self::getEloquentQuery()->count();
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return WorldForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return WorldsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListWorlds::route('/'),
            'create' => CreateWorld::route('/create'),
            'edit' => EditWorld::route('/{record}/edit'),
        ];
    }
}
