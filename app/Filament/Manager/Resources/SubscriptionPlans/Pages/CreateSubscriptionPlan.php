<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\SubscriptionPlans\Pages;

use App\Filament\Manager\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSubscriptionPlan extends CreateRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected static bool $canCreateAnother = false;
}
