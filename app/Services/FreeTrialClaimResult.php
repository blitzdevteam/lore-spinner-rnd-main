<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscriptionPlan;

final readonly class FreeTrialClaimResult
{
    private function __construct(
        public bool $granted,
        public string $message,
    ) {}

    public static function granted(SubscriptionPlan $plan): self
    {
        return new self(
            true,
            "Your free trial is active. You have {$plan->action_limit} actions and {$plan->voiceAllowanceLabel()} of voice.",
        );
    }

    public static function alreadyClaimed(): self
    {
        return new self(false, 'You have already claimed the free trial.');
    }

    public static function alreadySubscribed(): self
    {
        return new self(false, 'You already have an active subscription.');
    }

    public static function unavailable(): self
    {
        return new self(false, 'The free trial is not available right now.');
    }
}
