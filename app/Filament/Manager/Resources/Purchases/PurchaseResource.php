<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Purchases;

use App\Filament\Manager\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Manager\Resources\Purchases\Pages\ViewPurchase;
use App\Filament\Manager\Resources\Purchases\Schemas\PurchaseInfolist;
use App\Filament\Manager\Resources\Purchases\Tables\PurchasesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Cashier\Subscription;
use Override;

final class PurchaseResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $slug = 'purchases';

    protected static ?string $modelLabel = 'purchase';

    protected static ?string $pluralModelLabel = 'Purchases';

    protected static ?string $navigationLabel = 'Purchases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'stripe_id';

    public static function getNavigationBadge(): string
    {
        return (string) self::getEloquentQuery()->count();
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return PurchaseInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    /** @return Builder<Subscription> */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user:id,email,first_name,last_name,stripe_id');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'view' => ViewPurchase::route('/{record}'),
        ];
    }
}
