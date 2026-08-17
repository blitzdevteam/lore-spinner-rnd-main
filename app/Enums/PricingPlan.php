<?php

declare(strict_types=1);

namespace App\Enums;

enum PricingPlan: string
{
    case Explorer = 'explorer';
    case Adventurer = 'adventurer';
    case Chronicle = 'chronicle';

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
