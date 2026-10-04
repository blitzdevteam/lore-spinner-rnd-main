<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Billings\Pages;

use App\Filament\Manager\Resources\Billings\BillingResource;
use App\Models\User;
use App\Services\PurchasedPlanName;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Laravel\Cashier\Subscription;
use LogicException;

final class ViewBilling extends ViewRecord
{
    protected static string $resource = BillingResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTitle(): string
    {
        return 'Billing details';
    }

    public function getBreadcrumb(): string
    {
        return 'Details';
    }

    public function getRecordTitle(): string
    {
        return 'Subscription #'.mb_strtoupper(mb_substr($this->subscription()->stripe_id, 0, 10));
    }

    public function getSubheading(): string
    {
        $subscription = $this->subscription();
        $user = $subscription->user;

        return implode(' · ', [
            $user instanceof User ? $user->email : 'Unknown player',
            app(PurchasedPlanName::class)->summary($subscription),
            $subscription->created_at?->format('M j, Y H:i') ?? 'Purchase date unavailable',
        ]);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to billing')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(BillingResource::getUrl('index')),
        ];
    }

    private function subscription(): Subscription
    {
        $record = $this->getRecord();

        if (! $record instanceof Subscription) {
            throw new LogicException('Unexpected billing record type.');
        }

        return $record;
    }
}
