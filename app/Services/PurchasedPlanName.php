<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PricingPlan;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class PurchasedPlanName
{
    /** @var array<string, string>|null */
    private ?array $localNames = null;

    /** @var array<string, string> */
    private array $resolved = [];

    public function __construct(private StripePriceCatalog $stripePrices) {}

    public function label(?string $priceId): string
    {
        if (blank($priceId)) {
            return 'No single plan';
        }

        return $this->resolved[$priceId] ??= $this->resolve($priceId);
    }

    public function recognizes(?string $priceId): bool
    {
        return filled($priceId) && $this->label($priceId) !== 'Custom Stripe plan';
    }

    public function summary(Subscription $subscription): string
    {
        $labels = $this->labelsFor($subscription);

        return $labels->isNotEmpty() ? $labels->join(' + ') : 'No plan synchronized';
    }

    /**
     * @return Collection<int, string>
     */
    public function labelsFor(Subscription $subscription): Collection
    {
        return $this->priceIds($subscription)
            ->map(fn (string $priceId): string => $this->label($priceId))
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function priceIds(Subscription $subscription): Collection
    {
        $subscription->loadMissing('items');

        return $subscription->items
            ->pluck('stripe_price')
            ->prepend($subscription->stripe_price)
            ->filter(static fn (mixed $priceId): bool => is_string($priceId) && $priceId !== '')
            ->unique()
            ->values();
    }

    /**
     * Price IDs managers can filter by, labeled with the package the customer bought.
     *
     * @return array<string, string>
     */
    public function filterOptions(): array
    {
        $options = PricingPlan::stripePriceOptions();

        foreach ($this->localNames() as $priceId => $name) {
            $options[$priceId] = $name;
        }

        foreach ($this->stripePrices->activeProductNames() as $priceId => $name) {
            $options[$priceId] = $name;
        }

        return $options;
    }

    private function resolve(string $priceId): string
    {
        $stripeName = $this->stripePrices->productName($priceId);

        if (is_string($stripeName) && $stripeName !== '') {
            return $stripeName;
        }

        $localName = $this->localNames()[$priceId] ?? null;

        if (is_string($localName) && $localName !== '') {
            return $localName;
        }

        return PricingPlan::fromStripePriceId($priceId)?->label() ?? 'Custom Stripe plan';
    }

    /**
     * @return array<string, string>
     */
    private function localNames(): array
    {
        if ($this->localNames !== null) {
            return $this->localNames;
        }

        $names = [];

        foreach (SubscriptionPlan::query()->whereNotNull('stripe_price_id')->orderBy('sort_order')->get(['name', 'stripe_price_id']) as $plan) {
            if (! is_string($plan->stripe_price_id) || $plan->stripe_price_id === '' || $plan->name === '') {
                continue;
            }

            $names[$plan->stripe_price_id] = $plan->name;
        }

        return $this->localNames = $names;
    }
}
