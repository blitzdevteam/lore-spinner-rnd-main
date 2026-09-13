<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Response;

final class PricingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $status = $request->string('checkout')->toString();

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

    private function formattedPrice(SubscriptionPlan $plan): string
    {
        $amount = number_format($plan->price_cents / 100, 2);

        return mb_strtoupper($plan->currency) === 'USD'
            ? '$'.$amount
            : mb_strtoupper($plan->currency).' '.$amount;
    }
}
