<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PricingPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

final class StripeSubscriptionCheckout
{
    public function redirect(User $user, PricingPlan $plan): RedirectResponse
    {
        return $this->session($user, $plan)->redirect();
    }

    public function session(User $user, PricingPlan $plan): Checkout
    {
        $priceId = $plan->stripePriceId();

        if ($priceId === '') {
            abort(503, 'This plan is not available for checkout yet.');
        }

        return $user->newSubscription('default', $priceId)->checkout([
            'success_url' => route('pricing', ['checkout' => 'success']),
            'cancel_url' => route('pricing', ['checkout' => 'cancel']),
        ]);
    }
}
