<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('renders the pricing page', function () {
    $this->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pricing')
            ->where('checkoutStatus', null));
});
