<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors;

use App\Filament\Manager\Resources\Investors\Pages\CreateInvestor;
use App\Filament\Manager\Resources\Investors\Pages\EditInvestor;
use App\Filament\Manager\Resources\Investors\Pages\ListInvestors;
use App\Filament\Manager\Resources\Investors\Schemas\InvestorForm;
use App\Filament\Manager\Resources\Investors\Tables\InvestorsTable;
use App\Models\Investor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

final class InvestorResource extends Resource
{
    protected static ?string $model = Investor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Entities';

    protected static ?string $navigationLabel = 'Investors';

    protected static ?string $modelLabel = 'investor';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationBadge(): string
    {
        return (string) self::getEloquentQuery()->count();
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return InvestorForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return InvestorsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListInvestors::route('/'),
            'create' => CreateInvestor::route('/create'),
            'edit' => EditInvestor::route('/{record}/edit'),
        ];
    }
}
