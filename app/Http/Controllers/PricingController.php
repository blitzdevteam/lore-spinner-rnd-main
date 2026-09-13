<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Http\Request;
use Inertia\Response;

final class PricingController extends Controller
{
    public function __invoke(
        Request $request,
        SubscriptionEntitlementService $entitlements,
    ): Response {
        $status = $request->string('checkout')->toString();
        $user = $request->user('user');

        if ($status === 'success' && $user instanceof User) {
            $this->synchronizeCompletedCheckout($request, $user, $entitlements);
        }

        return inertia('Pricing', [
            'checkoutStatus' => in_array($status, ['success', 'cancel'], true) ? $status : null,
            'plans' => SubscriptionPlan::query()
                ->availableForCheckout()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (SubscriptionPlan $plan): array => [
                    'name' => mb_strtoupper($plan->name),
                    'tagline' => $plan->tagline ?? '',
                    'price' => $this->formattedPrice($plan),
                    'description' => $plan->description ?? '',
                    'features' => array_values(array_filter($plan->features, 'is_string')),
                    'cta' => 'Choose Plan',
                    'checkoutUrl' => route('user.checkout', ['plan' => $plan->slug]),
                    'iconKey' => $plan->icon_key,
                    'accent' => in_array($plan->accent, ['teal', 'gold'], true) ? $plan->accent : 'teal',
                    'featured' => $plan->is_featured,
                ])
                ->all(),
        ]);
    }

    private function synchronizeCompletedCheckout(
        Request $request,
        User $user,
        SubscriptionEntitlementService $entitlements,
    ): void {
        $checkoutSessionId = $request->string('session_id')->toString();

        if ($checkoutSessionId === '') {
            return;
        }

        $checkoutSession = $user->stripe()->checkout->sessions->retrieve($checkoutSessionId);
        $subscriptionId = $checkoutSession->subscription;

        if (
            $checkoutSession->mode !== 'subscription'
            || ! in_array($checkoutSession->payment_status, ['paid', 'no_payment_required'], true)
            || $checkoutSession->customer !== $user->stripe_id
            || ! is_string($subscriptionId)
            || $subscriptionId === ''
        ) {
            abort(422, 'The completed checkout session could not be verified.');
        }

        $entitlements->synchronizeStripeSubscription(
            $user,
            $user->stripe()->subscriptions->retrieve($subscriptionId)->toArray(),
        );
    }

    private function formattedPrice(SubscriptionPlan $plan): string
    {
        $amount = number_format($plan->price_cents / 100, 2);

        return mb_strtoupper($plan->currency) === 'USD'
            ? '$'.$amount
            : mb_strtoupper($plan->currency).' '.$amount;
    }
}
