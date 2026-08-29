<?php

declare(strict_types=1);

use App\Enums\PricingPlan;
use App\Models\User;
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
