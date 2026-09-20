<?php

declare(strict_types=1);

namespace App\Actions\Investor;

use App\Enums\UserType;
use App\Models\SubscriptionEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use RuntimeException;

final readonly class SyncInvestorPlanAccessAction
{
    public function grant(User $user): void
    {
        if (! $user->isInvestor()) {
            throw new RuntimeException('Plan access can only be granted to investor accounts.');
        }

        $user->loadMissing('plan');
        $plan = $user->plan;

        if ($plan === null) {
            throw new RuntimeException('An investor must have a subscription plan before access can be granted.');
        }

        $expiresAt = $user->expires_at ?? now()->addMonthsNoOverflow(max(1, $user->duration_months));
        $startsAt = $user->access_granted_at ?? now();

        $subscription = $this->investorSubscription($user) ?? $user->subscriptions()->create([
            'type' => UserType::Investor->value,
            'stripe_id' => 'inv_'.Str::lower((string) Str::ulid()),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_price_id,
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => $expiresAt,
        ]);

        $subscription->update([
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_price_id,
            'trial_ends_at' => null,
            'ends_at' => $expiresAt,
        ]);

        $this->syncEntitlement($subscription, $user->id, $plan, $startsAt, $expiresAt);
    }

    public function revoke(User $user): void
    {
        $subscription = $this->investorSubscription($user);

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => now(),
        ]);
    }

    private function investorSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->where('type', UserType::Investor->value)
            ->first();
    }

    private function syncEntitlement(
        Subscription $subscription,
        int $userId,
        SubscriptionPlan $plan,
        DateTimeInterface $startsAt,
        DateTimeInterface $expiresAt,
    ): void {
        $entitlement = SubscriptionEntitlement::query()
            ->where('subscription_id', $subscription->id)
            ->orderByDesc('period_start')
            ->first();

        $payload = [
            'user_id' => $userId,
            'subscription_plan_id' => $plan->id,
            'period_start' => $startsAt,
            'period_end' => $expiresAt,
            'action_limit' => $plan->action_limit,
            'voice_milliseconds_limit' => $plan->voiceMillisecondsLimit(),
        ];

        if ($entitlement === null) {
            SubscriptionEntitlement::query()->create([
                'subscription_id' => $subscription->id,
                ...$payload,
            ]);

            return;
        }

        if ($entitlement->subscription_plan_id !== $plan->id) {
            $payload['actions_used'] = 0;
            $payload['voice_milliseconds_used'] = 0;
        }

        $entitlement->update($payload);
    }
}
