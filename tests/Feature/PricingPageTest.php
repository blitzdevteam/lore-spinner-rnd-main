<?php

declare(strict_types=1);

use App\Models\SubscriptionEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Cashier\Subscription;

uses(RefreshDatabase::class);

it('renders the pricing page', function () {
    $this->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pricing')
            ->where('appName', config('app.display_name'))
            ->where('checkoutStatus', null));
});

it('sends allowance, bonus, and featured badge copy to the pricing cards', function () {
    SubscriptionPlan::factory()->create([
        'name' => 'Story',
        'slug' => 'story',
        'price_cents' => 1500,
        'tagline' => 'Base rate',
        'bonus_label' => null,
        'description' => 'A little adventure, whenever you need it.',
        'features' => ['Access to all interactive stories', 'Full multiplayer access'],
        'action_limit' => 225,
        'action_hint' => null,
        'voice_minutes_limit' => 135,
        'is_featured' => false,
        'featured_label' => null,
        'icon_key' => 'book',
        'sort_order' => 0,
    ]);

    SubscriptionPlan::factory()->create([
        'name' => 'Story Plus',
        'slug' => 'story-plus',
        'price_cents' => 3000,
        'tagline' => null,
        'bonus_label' => '25% Bonus',
        'description' => 'For adventures you return to every week.',
        'features' => ['Access to all interactive stories', 'Full multiplayer access'],
        'action_limit' => 550,
        'action_hint' => 'An action is one choice in a story.',
        'voice_minutes_limit' => 300,
        'is_featured' => true,
        'featured_label' => 'Most Recommended',
        'icon_key' => 'books-plus',
        'sort_order' => 0,
    ]);

    $this->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pricing')
            ->where('plans.0.name', 'Story')
            ->where('plans.0.price', '$15')
            ->where('plans.0.tagline', 'Base rate')
            ->where('plans.0.bonus', null)
            ->where('plans.0.actionAllowance', '225')
            ->where('plans.0.voiceAllowance', '2 hours 15 minutes')
            ->where('plans.0.badge', null)
            ->where('plans.0.featured', false)
            ->where('plans.1.name', 'Story Plus')
            ->where('plans.1.price', '$30')
            ->where('plans.1.bonus', '25% Bonus')
            ->where('plans.1.description', 'For adventures you return to every week.')
            ->where('plans.1.actionAllowance', '550')
            ->where('plans.1.actionHint', 'An action is one choice in a story.')
            ->where('plans.1.voiceAllowance', '5 hours')
            ->where('plans.1.badge', 'Most Recommended')
            ->where('plans.1.featured', true)
            ->where('plans.1.features', ['Access to all interactive stories', 'Full multiplayer access']));
});

it('marks the purchased subscription as the current plan', function () {
    SubscriptionPlan::factory()->create([
        'name' => 'Owned Path',
        'slug' => 'owned-path',
        'stripe_price_id' => 'price_owned_path',
        'sort_order' => 1,
    ]);
    SubscriptionPlan::factory()->create([
        'name' => 'Other Path',
        'slug' => 'other-path',
        'stripe_price_id' => 'price_other_path',
        'sort_order' => 2,
    ]);

    $user = User::factory()->create();
    Subscription::factory()
        ->for($user)
        ->withPrice('price_owned_path')
        ->create();

    $this->actingAs($user, 'user')
        ->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plans', function (mixed $plans): bool {
                $plans = collect($plans);
                $owned = $plans->firstWhere('name', 'Owned Path');
                $other = $plans->firstWhere('name', 'Other Path');

                return is_array($owned)
                    && is_array($other)
                    && $owned['current'] === true
                    && $owned['cta'] === 'Active Plan'
                    && $other['current'] === false
                    && $other['cta'] === 'Choose Plan';
            }));
});

it('does not mark a canceled subscription as the current plan', function () {
    SubscriptionPlan::factory()->create([
        'name' => 'Ended Path',
        'slug' => 'ended-path',
        'stripe_price_id' => 'price_ended_path',
        'sort_order' => 1,
    ]);

    $user = User::factory()->create();
    Subscription::factory()
        ->for($user)
        ->withPrice('price_ended_path')
        ->canceled()
        ->create();

    $this->actingAs($user, 'user')
        ->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plans', function (mixed $plans): bool {
                $ended = collect($plans)->firstWhere('name', 'Ended Path');

                return is_array($ended)
                    && $ended['current'] === false
                    && $ended['cta'] === 'Choose Plan';
            }));
});

it('falls back to the recommended badge when a featured plan has no label', function () {
    $plan = SubscriptionPlan::factory()->create([
        'is_featured' => true,
        'featured_label' => null,
        'voice_minutes_limit' => 60,
    ]);

    expect($plan->featuredBadge())->toBe('Most Recommended')
        ->and($plan->voiceAllowanceLabel())->toBe('1 hour');
});

it('renders allowance lines and a four-column pricing card row', function () {
    $card = file_get_contents(resource_path('js/components/PricingPlanCard.vue'));
    $usage = file_get_contents(resource_path('js/components/PricingUsageCard.vue'));
    $pricing = file_get_contents(resource_path('js/pages/Pricing.vue'));

    expect($card)
        ->toContain('Actions / month')
        ->toContain('What is an Action?')
        ->toContain('Actions are credits you use to advance your story and access features such as choice reshuffles, time extensions, and recap. Voice narration uses a separate allowance.')
        ->toContain('Voice Narration / month')
        ->toContain('>/month<')
        ->toContain('pricing-border-flow')
        ->toContain('scale(1.035)')
        ->toContain('border-color: #ffbe58')
        ->toContain('rgb(255 190 88 / 0.2)')
        ->toContain('pricing-card--current')
        ->not->toContain('/monthly')
        ->not->toContain('what you will get')
        ->not->toContain('min-h-[40.3125rem]');

    expect($pricing)
        ->toContain('xl:grid-cols-4')
        ->toContain('items-stretch')
        ->toContain('Try 20 actions and 10 minutes of voice free')
        ->toContain('One-time allowance for new accounts.')
        ->toContain(':current="plan.current"')
        ->toContain('Try Free')
        ->toContain('PricingUsageCard')
        ->toContain('* Percentages are not exact.')
        ->toContain('Prices are subject to change at any time.')
        ->toContain('Plans are subject to change at any time.')
        ->not->toContain('View FAQs')
        ->not->toContain('pricing-faq');

    expect($usage)
        ->toContain('Your Usage')
        ->toContain('Change Plan')
        ->toContain('Cancel subscription')
        ->toContain('remaining');
});

it('keeps the free trial offer for a player without a plan', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user, 'user')
        ->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billingPortalUrl', null)
            ->where('auth.subscription_usage', null));
});

it('shares the free plan allowance after signup so pricing can show usage', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user, 'user')
        ->post(route('user.free-trial.store'))
        ->assertRedirect(route('pricing'));

    $this->actingAs($user, 'user')
        ->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billingPortalUrl', null)
            ->where('auth.subscription_usage.plan.name', 'Free Pass')
            ->where('auth.subscription_usage.plan.icon_key', 'footprints')
            ->where('auth.subscription_usage.actions.remaining', 20)
            ->where('auth.subscription_usage.voice.limit_minutes', 10)
            ->where('auth.subscription_usage.is_one_time', true)
            ->where('auth.subscription_usage.can_manage_billing', false));
});

it('does not error when canceling without a subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'user')
        ->post(route('user.subscription.cancel'))
        ->assertRedirect(route('pricing'))
        ->assertSessionHas('error', 'There is no active subscription to cancel.');
});

it('cancels a renewing subscription without calling stripe', function () {
    config(['cashier.secret' => null]);

    $user = User::factory()->create([
        'stripe_id' => 'cus_test',
        'is_active' => true,
    ]);
    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Hero Pass',
        'action_limit' => 450,
        'voice_minutes_limit' => 270,
    ]);
    $periodEnd = now()->addDays(15);

    $subscription = $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
    ]);

    SubscriptionEntitlement::query()->create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
        'subscription_plan_id' => $plan->id,
        'period_start' => now()->subDay(),
        'period_end' => $periodEnd,
        'action_limit' => 450,
        'voice_milliseconds_limit' => 270 * 60_000,
    ]);

    $this->actingAs($user, 'user')
        ->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billingPortalUrl', route('user.subscription.cancel'))
            ->where('auth.subscription_usage.plan.name', 'Hero Pass')
            ->where('auth.subscription_usage.renews', true)
            ->where('auth.subscription_usage.can_manage_billing', true));

    $this->actingAs($user, 'user')
        ->post(route('user.subscription.cancel'))
        ->assertRedirect(route('pricing'))
        ->assertSessionHas('success');

    $subscription->refresh();

    expect($subscription->ends_at)->not->toBeNull()
        ->and($subscription->ends_at?->toDateString())->toBe($periodEnd->toDateString())
        ->and(app(SubscriptionEntitlementService::class)->hasPlayAccess($user->fresh()))->toBeTrue();

    $this->actingAs($user, 'user')
        ->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billingPortalUrl', null)
            ->where('auth.subscription_usage.renews', false)
            ->where('auth.subscription_usage.can_manage_billing', false));
});
