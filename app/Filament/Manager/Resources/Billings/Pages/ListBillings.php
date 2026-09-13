<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Billings\Pages;

use App\Filament\Manager\Resources\Billings\BillingResource;
use Filament\Resources\Pages\ListRecords;

final class ListBillings extends ListRecords
{
    protected static string $resource = BillingResource::class;

    public function getSubheading(): string
    {
        return 'Cashier keeps these subscription records synchronized from Stripe webhooks. Open a record to inspect its recent Stripe invoice history.';
    }
}
