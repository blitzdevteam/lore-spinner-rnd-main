<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Purchases\Pages;

use App\Filament\Manager\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\ListRecords;

final class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    public function getSubheading(): string
    {
        return 'Cashier keeps these purchased-plan records synchronized from Stripe webhooks. Open a record to inspect the player, plan, status, and access dates.';
    }
}
