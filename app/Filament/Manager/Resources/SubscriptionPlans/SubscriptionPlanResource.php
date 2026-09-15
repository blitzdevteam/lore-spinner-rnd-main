<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\SubscriptionPlans;

use App\Filament\Manager\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Manager\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Manager\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Filament\Manager\Resources\SubscriptionPlans\Schemas\SubscriptionPlanForm;
use App\Filament\Manager\Resources\SubscriptionPlans\Tables\SubscriptionPlansTable;
use App\Models\SubscriptionPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $modelLabel = 'subscription plan';

    protected static ?string $pluralModelLabel = 'Subscription Plans';

    protected static ?string $navigationLabel = 'Subscription Plans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 4;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return SubscriptionPlanForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return SubscriptionPlansTable::configure($table);
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
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
