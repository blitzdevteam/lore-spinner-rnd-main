<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StripeSubscriptionCheckout;
use App\Support\ReturnToUrl;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CheckoutController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        SubscriptionPlan $plan,
        StripeSubscriptionCheckout $checkout,
        Request $request,
    ): RedirectResponse {
        return $checkout->redirect($user, $plan, ReturnToUrl::fromRequest($request));
    }
}
