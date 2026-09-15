<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Purchases\Pages;

use App\Filament\Manager\Resources\Purchases\PurchaseResource;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Laravel\Cashier\Subscription;
use LogicException;

final class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTitle(): string
    {
        return 'Purchase details';
    }

    public function getBreadcrumb(): string
    {
        return 'Details';
    }

    public function getRecordTitle(): string
    {
        return 'Purchase #'.mb_strtoupper(mb_substr($this->subscription()->stripe_id, 0, 10));
    }

    public function getSubheading(): string
    {
        $subscription = $this->subscription();
        $user = $subscription->user;
        $planLabel = is_string($subscription->stripe_price)
            ? SubscriptionPlan::query()
                ->where('stripe_price_id', $subscription->stripe_price)
                ->value('name')
            : null;

        return implode(' · ', [
            $user instanceof User ? $user->email : 'Unknown player',
            is_string($planLabel) ? $planLabel : 'Custom Stripe plan',
            $subscription->created_at?->format('M j, Y H:i') ?? 'Purchase date unavailable',
        ]);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to purchases')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(PurchaseResource::getUrl('index')),
        ];
    }

    private function subscription(): Subscription
    {
        $record = $this->getRecord();

        if (! $record instanceof Subscription) {
            throw new LogicException('Unexpected purchase record type.');
        }

        return $record;
    }
}
