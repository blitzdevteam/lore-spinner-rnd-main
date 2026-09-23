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

it('grants a new account twenty actions and ten minutes of voice', function (): void {
    $user = User::factory()->create();

    expect(app(SubscriptionEntitlementService::class)->summary($user))->toBeNull()
        ->and(app(SubscriptionEntitlementService::class)->hasPlayAccess($user))->toBeFalse();

    $this->actingAs($user, 'user')
        ->post(route('user.free-trial.store'))
        ->assertRedirect(route('pricing'))
        ->assertSessionHas('success', 'Your free trial is active. You have 20 actions and 10 minutes of voice.');

    $plan = SubscriptionPlan::query()->where('slug', 'free')->first();

    expect($plan)->not->toBeNull()
        ->and($plan->action_limit)->toBe(20)
        ->and($plan->voice_minutes_limit)->toBe(10);

    $this->assertDatabaseHas(SubscriptionEntitlement::class, [
        'user_id' => $user->id,
        'subscription_id' => null,
        'subscription_plan_id' => $plan->id,
        'is_one_time' => true,
        'one_time_key' => 'free:'.$user->id,
        'action_limit' => 20,
        'voice_milliseconds_limit' => 600_000,
    ]);

    expect(app(SubscriptionEntitlementService::class)->hasPlayAccess($user))->toBeTrue();

    $this->actingAs($user, 'user')
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.subscription_usage.plan.name', 'Free Pass')
            ->where('auth.subscription_usage.actions.limit', 20)
            ->where('auth.subscription_usage.actions.remaining', 20)
            ->where('auth.subscription_usage.voice.limit_minutes', 10)
            ->where('auth.subscription_usage.voice.remaining_milliseconds', 600_000)
            ->where('auth.subscription_usage.is_one_time', true)
            ->where('plans', fn (mixed $plans): bool => collect($plans)->pluck('name')->doesntContain('Free Pass')));
});

it('rejects a second free trial claim', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'user')
        ->post(route('user.free-trial.store'))
        ->assertRedirect(route('pricing'));

    $this->actingAs($user, 'user')
        ->post(route('user.free-trial.store'))
        ->assertRedirect(route('pricing'))
        ->assertSessionHas('error', 'You have already claimed the free trial.');

    expect(SubscriptionEntitlement::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects a free trial when the account already has an active subscription', function (): void {
    $user = User::factory()->create();

    Subscription::factory()
        ->for($user)
        ->create();

    $this->actingAs($user, 'user')
        ->post(route('user.free-trial.store'))
        ->assertRedirect(route('pricing'))
        ->assertSessionHas('error', 'You already have an active subscription.');

    expect(SubscriptionEntitlement::query()->where('one_time_key', 'free:'.$user->id)->exists())->toBeFalse();
});

it('sends guests to log in before they can claim the free trial', function (): void {
    $this->post(route('user.free-trial.store'))
        ->assertRedirect(route('user.authentication.login.create'));
});

it('reports that the free trial is unavailable when the plan is inactive', function (): void {
    SubscriptionPlan::query()->where('slug', 'free')->update(['is_active' => false]);

    $user = User::factory()->create();

    $this->actingAs($user, 'user')
        ->post(route('user.free-trial.store'))
        ->assertRedirect(route('pricing'))
        ->assertSessionHas('error', 'The free trial is not available right now.');
});
