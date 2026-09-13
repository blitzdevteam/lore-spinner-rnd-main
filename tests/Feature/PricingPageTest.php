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

it('displays the current monthly plan prices', function () {
    $pricing = file_get_contents(resource_path('js/pages/Pricing.vue'));

    expect($pricing)
        ->toContain("price: '$14.9'")
        ->toContain("price: '$29.9'")
        ->toContain("price: '$39.9'")
        ->not->toContain("price: '$9.9'")
        ->not->toContain("price: '$19.9'");
});

it('displays the current plan descriptions and feature lists', function () {
    $pricing = file_get_contents(resource_path('js/pages/Pricing.vue'));

    expect($pricing)
        ->toContain('Built for casual players stepping into interactive stories with standard Actions and multiplayer privileges.')
        ->toContain('Best for solo adventurers and duos wanting steady voice immersion and private lobby access.')
        ->toContain('Built for active role-players wanting deeper campaign arcs, frequent voice immersion, and lobby hosting.')
        ->toContain("'225 Actions'")
        ->toContain("'450 Actions'")
        ->toContain("'600 Actions'")
        ->toContain("'135 Minutes of Voice Narration'")
        ->toContain("'270 Minutes of Voice Narration'")
        ->toContain("'360 Minutes of Voice Narration'")
        ->toContain("'Access to All Interactive Stories'")
        ->toContain("'Full Multiplayer Access'")
        ->toContain("'2-3 Full solo story experiences'")
        ->toContain("'4-5 Full solo story experiences'")
        ->toContain("'5-6 Full solo story experiences'")
        ->toContain("'Shared token pooling for hosted lobbies'")
        ->not->toContain('100 Spinners');
});

it('sizes pricing cards to their feature lists instead of a shared min height', function () {
    $card = file_get_contents(resource_path('js/components/PricingPlanCard.vue'));
    $pricing = file_get_contents(resource_path('js/pages/Pricing.vue'));

    expect($card)
        ->not->toContain('min-h-[40.3125rem]')
        ->not->toContain('flex h-full min-h-');

    expect($pricing)
        ->toContain('items-start')
        ->not->toContain('items-stretch');
});
