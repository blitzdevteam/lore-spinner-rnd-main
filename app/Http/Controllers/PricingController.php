<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use App\Support\ReturnToUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class PricingController extends Controller
{
    public function __invoke(
        Request $request,
        SubscriptionEntitlementService $entitlements,
    ): Response|RedirectResponse {
        $status = $request->string('checkout')->toString();
        $returnTo = ReturnToUrl::fromRequest($request);
        $user = $request->user('user');

        if ($status === 'success' && $user instanceof User) {
            $this->synchronizeCompletedCheckout($request, $user, $entitlements);

            if ($returnTo !== null) {
                return redirect()->to($returnTo);
            }
        }

        $currentPlanId = $user instanceof User
            ? $entitlements->activePlan($user)?->id
            : null;

        return inertia('Pricing', [
            'checkoutStatus' => in_array($status, ['success', 'cancel'], true) ? $status : null,
            'plans' => SubscriptionPlan::query()
                ->availableForCheckout()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (SubscriptionPlan $plan): array => [
                    'name' => $plan->name,
                    'tagline' => $plan->tagline ?? '',
                    'price' => $this->formattedPrice($plan),
                    'bonus' => filled($plan->bonus_label) ? $plan->bonus_label : null,
                    'description' => $plan->description ?? '',
                    'actionAllowance' => (string) $plan->action_limit,
                    'actionHint' => filled($plan->action_hint) ? $plan->action_hint : null,
                    'voiceAllowance' => $plan->voiceAllowanceLabel(),
                    'features' => array_values(array_filter($plan->features, 'is_string')),
                    'cta' => $plan->id === $currentPlanId ? 'Current plan' : 'Choose Plan',
                    'checkoutUrl' => route('user.checkout', array_filter([
                        'plan' => $plan->slug,
                        'return_to' => $returnTo,
                    ], static fn (?string $value): bool => $value !== null)),
                    'iconKey' => $plan->icon_key,
                    'featured' => $plan->is_featured,
                    'badge' => $plan->featuredBadge(),
                    'current' => $plan->id === $currentPlanId,
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

        $checkoutSessionPayload = $checkoutSession->toArray();

        if (! is_array($checkoutSessionPayload)) {
            abort(422, 'The completed checkout session could not be verified.');
        }

        $entitlements->synchronizeCompletedCheckoutSession($user, $checkoutSessionPayload);
    }

    private function formattedPrice(SubscriptionPlan $plan): string
    {
        $amount = $plan->price_cents % 100 === 0
            ? number_format(intdiv($plan->price_cents, 100))
            : number_format($plan->price_cents / 100, 2);

        return mb_strtoupper($plan->currency) === 'USD'
            ? '$'.$amount
            : mb_strtoupper($plan->currency).' '.$amount;
    }
}
