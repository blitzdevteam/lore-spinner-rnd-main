<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\SubscriptionPlans\Pages;

use App\Filament\Manager\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListSubscriptionPlans extends ListRecords
{
    protected static string $resource = SubscriptionPlanResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
