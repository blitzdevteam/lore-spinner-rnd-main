<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\SubscriptionPlans\Pages;

use App\Filament\Manager\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Resources\Pages\EditRecord;

final class EditSubscriptionPlan extends EditRecord
{
    protected static string $resource = SubscriptionPlanResource::class;
}
