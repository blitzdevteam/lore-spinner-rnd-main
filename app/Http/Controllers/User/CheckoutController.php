<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Enums\PricingPlan;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StripeSubscriptionCheckout;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class CheckoutController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        PricingPlan $plan,
        StripeSubscriptionCheckout $checkout,
    ): RedirectResponse {
        return $checkout->redirect($user, $plan);
    }
}
