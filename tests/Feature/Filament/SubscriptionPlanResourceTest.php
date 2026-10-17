<?php

declare(strict_types=1);

use App\Filament\Manager\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Manager\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Manager\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Models\Manager;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('hides inactive subscription plans until an admin chooses to see them', function (): void {
    $manager = Manager::factory()->create();
    $active = SubscriptionPlan::factory()->create([
        'name' => 'Story',
        'is_active' => true,
    ]);
    $inactive = SubscriptionPlan::factory()->create([
        'name' => 'Adventurer Pass',
        'is_active' => false,
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(ListSubscriptionPlans::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive])
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$active])
        ->filterTable('is_active', null)
        ->assertCanSeeTableRecords([$active, $inactive]);
});

it('saves pricing card copy from the subscription plan form', function (): void {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(CreateSubscriptionPlan::class)
        ->fillForm([
            'is_active' => true,
            'is_featured' => true,
            'name' => 'Story Plus',
            'slug' => 'story-plus',
            'stripe_price_id' => 'price_story_plus_test',
            'sort_order' => 2,
            'price_cents' => 3000,
            'currency' => 'USD',
            'action_limit' => 550,
            'voice_minutes_limit' => 300,
            'tagline' => null,
            'description' => 'For adventures you return to every week.',
            'bonus_label' => '25% Bonus',
            'action_hint' => 'Each action is one story choice.',
            'featured_label' => 'Most Recommended',
            'features' => ['Access to all interactive stories', 'Full multiplayer access'],
            'accent' => 'teal',
            'icon_key' => 'books-plus',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = SubscriptionPlan::query()->where('slug', 'story-plus')->first();

    expect($plan)->not->toBeNull()
        ->and($plan?->bonus_label)->toBe('25% Bonus')
        ->and($plan?->action_hint)->toBe('Each action is one story choice.')
        ->and($plan?->featured_label)->toBe('Most Recommended')
        ->and($plan?->description)->toBe('For adventures you return to every week.')
        ->and($plan?->action_limit)->toBe(550)
        ->and($plan?->voice_minutes_limit)->toBe(300)
        ->and($plan?->features)->toBe(['Access to all interactive stories', 'Full multiplayer access']);
});

it('updates allowance copy on an existing subscription plan', function (): void {
    $manager = Manager::factory()->create();
    $plan = SubscriptionPlan::factory()->create([
        'action_limit' => 225,
        'voice_minutes_limit' => 120,
        'description' => 'Old description',
        'bonus_label' => null,
        'action_hint' => null,
        'is_featured' => true,
        'featured_label' => null,
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(EditSubscriptionPlan::class, ['record' => $plan->getRouteKey()])
        ->fillForm([
            'description' => 'A little adventure, whenever you need it.',
            'bonus_label' => '25% Bonus',
            'action_hint' => 'Actions reset each month.',
            'action_limit' => 225,
            'voice_minutes_limit' => 120,
            'is_featured' => true,
            'featured_label' => 'Most Recommended',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $plan->refresh();

    expect($plan->description)->toBe('A little adventure, whenever you need it.')
        ->and($plan->bonus_label)->toBe('25% Bonus')
        ->and($plan->action_hint)->toBe('Actions reset each month.')
        ->and($plan->action_limit)->toBe(225)
        ->and($plan->is_featured)->toBeTrue()
        ->and($plan->featured_label)->toBe('Most Recommended');
});
