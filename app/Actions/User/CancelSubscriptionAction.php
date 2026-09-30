<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\UserType;
use App\Models\SubscriptionEntitlement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;

final readonly class CancelSubscriptionAction
{
    public function handle(User $user): CancelSubscriptionResult
    {
        $subscription = $this->activeSubscription($user);

        if ($subscription === null) {
            return CancelSubscriptionResult::failed('There is no active subscription to cancel.');
        }

        if ($subscription->canceled()) {
            return CancelSubscriptionResult::canceled($this->message($subscription->ends_at));
        }

        if ($this->canCancelOnStripe($subscription)) {
            try {
                $subscription->cancel();
            } catch (ApiErrorException $exception) {
                report($exception);

                return CancelSubscriptionResult::failed('We could not cancel your subscription. Please try again.');
            }

            return CancelSubscriptionResult::canceled($this->message($subscription->ends_at));
        }

        $endsAt = $subscription->ends_at
            ?? $this->periodEnd($subscription)
            ?? now();

        $subscription->forceFill([
            'ends_at' => $endsAt,
        ])->save();

        return CancelSubscriptionResult::canceled($this->message($subscription->ends_at));
    }

    private function activeSubscription(User $user): ?Subscription
    {
        $subscriptions = $user->subscriptions()->get();

        $paidSubscription = $subscriptions->first(
            static fn (Subscription $subscription): bool => $subscription->type === 'default' && $subscription->valid(),
        );

        if ($paidSubscription !== null) {
            return $paidSubscription;
        }

        return $subscriptions->first(
            static fn (Subscription $subscription): bool => $subscription->type === UserType::Investor->value && $subscription->valid(),
        );
    }

    private function canCancelOnStripe(Subscription $subscription): bool
    {
        return filled(config('cashier.secret'))
            && str_starts_with((string) $subscription->stripe_id, 'sub_');
    }

    private function periodEnd(Subscription $subscription): ?Carbon
    {
        $periodEnd = SubscriptionEntitlement::query()
            ->where('subscription_id', $subscription->id)
            ->whereNotNull('period_end')
            ->orderByDesc('period_end')
            ->value('period_end');

        if ($periodEnd === null) {
            return null;
        }

        return Carbon::parse($periodEnd);
    }

    private function message(?Carbon $endsAt): string
    {
        if ($endsAt === null) {
            return 'Your subscription is canceled. You keep access until the end of the current period.';
        }

        $date = $endsAt->timezone((string) config('app.timezone'))->format('M j, Y');

        return "Your subscription will end on {$date}. You keep access until then.";
    }
}
