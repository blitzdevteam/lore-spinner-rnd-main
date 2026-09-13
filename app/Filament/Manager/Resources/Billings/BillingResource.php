<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Billings;

use App\Filament\Manager\Resources\Billings\Pages\ListBillings;
use App\Filament\Manager\Resources\Billings\Pages\ViewBilling;
use App\Filament\Manager\Resources\Billings\Schemas\BillingInfolist;
use App\Filament\Manager\Resources\Billings\Tables\BillingsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Cashier\Subscription;
use Override;

final class BillingResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $slug = 'billing';

    protected static ?string $modelLabel = 'billing subscription';

    protected static ?string $pluralModelLabel = 'Billing';

    protected static ?string $navigationLabel = 'Billing';

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
        return BillingInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BillingsTable::configure($table);
    }

    /** @return Builder<Subscription> */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user:id,email,first_name,last_name,stripe_id,pm_type,pm_last_four');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBillings::route('/'),
            'view' => ViewBilling::route('/{record}'),
        ];
    }
}
