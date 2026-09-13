<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Billings\Schemas;

use App\Services\StripeSubscriptionInvoiceHistory;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Laravel\Cashier\Subscription;

final class BillingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.manager.resources.billings.billing-detail')
                    ->columnSpanFull()
                    ->viewData(static fn (Subscription $record): array => [
                        'subscription' => $record,
                        'invoiceHistory' => app(StripeSubscriptionInvoiceHistory::class)->for($record),
                    ]),
            ]);
    }
}
