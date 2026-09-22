<?php

declare(strict_types=1);

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the pricing page', function () {
    $this->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pricing')
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
    $pricing = file_get_contents(resource_path('js/pages/Pricing.vue'));

    expect($card)
        ->toContain('Actions / month')
        ->toContain('Voice Narration / month')
        ->toContain('>/month<')
        ->toContain('pricing-border-flow')
        ->not->toContain('/monthly')
        ->not->toContain('what you will get')
        ->not->toContain('min-h-[40.3125rem]');

    expect($pricing)
        ->toContain('xl:grid-cols-4')
        ->toContain('items-stretch')
        ->toContain('Try 20 actions and 10 minutes of voice free')
        ->toContain('One-time allowance for new accounts.')
        ->toContain('Try Free')
        ->not->toContain('View FAQs')
        ->not->toContain('pricing-faq');
});
