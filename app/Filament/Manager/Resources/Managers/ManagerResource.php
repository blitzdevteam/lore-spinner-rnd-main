<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Managers;

use App\Filament\Manager\Resources\Managers\Pages\CreateManager;
use App\Filament\Manager\Resources\Managers\Pages\EditManager;
use App\Filament\Manager\Resources\Managers\Pages\ListManagers;
use App\Filament\Manager\Resources\Managers\Schemas\ManagerForm;
use App\Filament\Manager\Resources\Managers\Tables\ManagersTable;
use App\Models\Manager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class ManagerResource extends Resource
{
    protected static ?string $model = Manager::class;

    protected static ?string $slug = 'admins';

    protected static ?string $navigationLabel = 'Admins';

    protected static ?string $modelLabel = 'admin';

    protected static ?string $pluralModelLabel = 'admins';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationBadge(): string
    {
        return (string) self::getEloquentQuery()->count();
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ManagerForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ManagersTable::configure($table);
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
            'index' => ListManagers::route('/'),
            'create' => CreateManager::route('/create'),
            'edit' => EditManager::route('/{record}/edit'),
        ];
    }
}
