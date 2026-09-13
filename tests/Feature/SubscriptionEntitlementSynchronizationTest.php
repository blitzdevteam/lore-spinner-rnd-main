<?php

declare(strict_types=1);

use App\Models\SubscriptionEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;

uses(RefreshDatabase::class);

it('creates an active local subscription and entitlement from a verified Stripe subscription', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_test']);
    $plan = SubscriptionPlan::factory()->create([
        'stripe_price_id' => 'price_test',
        'action_limit' => 225,
        'voice_minutes_limit' => 135,
    ]);
    $periodStart = now()->startOfMonth()->timestamp;
    $periodEnd = now()->startOfMonth()->addMonth()->timestamp;

    app(SubscriptionEntitlementService::class)->synchronizeStripeSubscription($user, [
        'id' => 'sub_test',
        'customer' => 'cus_test',
        'status' => 'active',
        'metadata' => ['type' => 'default'],
        'current_period_start' => $periodStart,
        'current_period_end' => $periodEnd,
        'items' => [
            'data' => [[
                'id' => 'si_test',
                'quantity' => 1,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'price' => [
                    'id' => 'price_test',
                    'product' => 'prod_test',
                ],
            ]],
        ],
    ]);

    expect($user->fresh()->subscribed('default'))->toBeTrue()
        ->and(Subscription::query()->where('stripe_id', 'sub_test')->exists())->toBeTrue();

    $this->assertDatabaseHas(SubscriptionEntitlement::class, [
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'action_limit' => 225,
        'voice_milliseconds_limit' => 8_100_000,
    ]);
});

it('does not synchronize a Stripe subscription for another customer', function (): void {
    $user = User::factory()->create(['stripe_id' => 'cus_test']);

    app(SubscriptionEntitlementService::class)->synchronizeStripeSubscription($user, [
        'id' => 'sub_test',
        'customer' => 'cus_other',
        'status' => 'active',
        'items' => ['data' => []],
    ]);

    expect(Subscription::query()->count())->toBe(0)
        ->and(SubscriptionEntitlement::query()->count())->toBe(0);
});
