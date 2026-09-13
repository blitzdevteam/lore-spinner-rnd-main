<?php

declare(strict_types=1);

namespace App\Enums;

enum PricingPlan: string
{
    case Explorer = 'explorer';
    case Adventurer = 'adventurer';
    case Chronicle = 'chronicle';

    public static function fromStripePriceId(?string $priceId): ?self
    {
        if (blank($priceId)) {
            return null;
        }

        foreach (self::cases() as $plan) {
            if ($plan->stripePriceId() === $priceId) {
                return $plan;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function stripePriceOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static function (self $plan): array {
                $priceId = $plan->stripePriceId();

                return filled($priceId) ? [$priceId => $plan->label()] : [];
            })
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::Explorer => 'Explorer',
            self::Adventurer => 'Adventurer',
            self::Chronicle => 'Chronicle',
        };
    }

    public function stripePriceId(): string
    {
        $priceId = match ($this) {
            self::Explorer => config('services.stripe.prices.explorer'),
            self::Adventurer => config('services.stripe.prices.adventurer'),
            self::Chronicle => config('services.stripe.prices.chronicle'),
        };

        return is_string($priceId) ? $priceId : '';
    }
}
