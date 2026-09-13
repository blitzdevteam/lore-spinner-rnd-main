<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

final class StripeSubscriptionCheckout
{
    public function redirect(User $user, SubscriptionPlan $plan): RedirectResponse
    {
        return $this->session($user, $plan)->redirect();
    }

    public function session(User $user, SubscriptionPlan $plan): Checkout
    {
        $priceId = $plan->stripe_price_id;

        if (! $plan->is_active || ! is_string($priceId) || $priceId === '') {
            abort(503, 'This plan is not available for checkout yet.');
        }

        return $user->newSubscription('default', $priceId)->checkout([
            'success_url' => route('pricing', ['checkout' => 'success']),
            'cancel_url' => route('pricing', ['checkout' => 'cancel']),
        ]);
    }
}
