<?php

declare(strict_types=1);

use App\Enums\PricingPlan;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from checkout to login instead of home', function () {
    $this->get(route('user.checkout', PricingPlan::Explorer))
        ->assertRedirect(route('user.authentication.login.create'));
});

it('returns not found for unknown plans', function () {
    $user = User::factory()->make();
    $user->id = 1;

    $this->actingAs($user)
        ->get('/user/checkout/unknown')
        ->assertNotFound();
});

it('passes checkout status to the pricing page', function () {
    $this->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing', ['checkout' => 'success']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pricing')
            ->where('checkoutStatus', 'success'));
});

it('returns a player to the story page after a completed checkout', function () {
    $user = User::factory()->make(['id' => 1]);

    $this->actingAs($user, 'user')
        ->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing', [
            'checkout' => 'success',
            'return_to' => '/stories/example',
        ]))
        ->assertRedirect('/stories/example');
});

it('keeps the return target on every checkout plan link', function () {
    $this->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing', ['return_to' => '/stories/example']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('plans.0.checkoutUrl', fn (mixed $url): bool => is_string($url)
                && str_contains(urldecode($url), 'return_to=/stories/example')));
});

it('carries the original story page through the subscription guard', function () {
    $user = User::factory()->withCompleteProfile()->make(['id' => 1]);

    $this->mock(SubscriptionEntitlementService::class)
        ->shouldReceive('hasPlayAccess')
        ->once()
        ->andReturnFalse();

    $this->actingAs($user, 'user')
        ->post(route('user.games.store'), [
            'story_id' => 1,
            'return_to' => '/stories/example',
        ])
        ->assertRedirect(route('pricing', ['return_to' => '/stories/example']));
});
