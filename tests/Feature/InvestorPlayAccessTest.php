<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an investor play stories on the assigned plan', function (): void {
    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Adventurer Pass',
        'action_limit' => 80,
        'voice_minutes_limit' => 20,
    ]);
    $user = User::factory()->investor()->create([
        'subscription_plan_id' => $plan->id,
    ]);
    $story = playableStory();
    $summary = app(SubscriptionEntitlementService::class)->summary($user);

    expect(app(SubscriptionEntitlementService::class)->hasPlayAccess($user))->toBeTrue()
        ->and($summary['is_complimentary'])->toBeFalse()
        ->and($summary['plan']['name'])->toBe('Adventurer Pass')
        ->and($summary['actions']['limit'])->toBe(80);

    $this->actingAs($user, 'user')
        ->post(route('user.games.store'), [
            'story_id' => $story->id,
        ])
        ->assertRedirect();

    expect($user->games()->where('story_id', $story->id)->exists())->toBeTrue();
});

it('blocks play until the investor buys a subscription', function (): void {
    $user = User::factory()->investor()->blocked()->create();
    $story = playableStory();

    expect(app(SubscriptionEntitlementService::class)->hasPlayAccess($user))->toBeFalse()
        ->and(app(SubscriptionEntitlementService::class)->summary($user))->toBeNull();

    $this->actingAs($user, 'user')
        ->post(route('user.games.store'), [
            'story_id' => $story->id,
            'return_to' => '/stories/example',
        ])
        ->assertRedirect(route('pricing', ['return_to' => '/stories/example']));

    expect($user->games()->exists())->toBeFalse();
});

it('blocks play when the investor test window has expired', function (): void {
    $user = User::factory()->investor()->expired()->create();
    $story = playableStory();

    expect($user->hasActiveInvestorAccess())->toBeFalse()
        ->and(app(SubscriptionEntitlementService::class)->hasPlayAccess($user))->toBeFalse()
        ->and($user->magicLoginUrl())->toBeNull();

    $this->actingAs($user, 'user')
        ->post(route('user.games.store'), [
            'story_id' => $story->id,
        ])
        ->assertRedirect(route('pricing'));
});

it('meters investor story usage against the assigned plan', function (): void {
    $user = User::factory()->investor()->create();
    $game = $user->games()->make();

    expect(app(SubscriptionEntitlementService::class)->shouldTrackUsageFor($user, $game))->toBeTrue();
});

it('keeps paid play access after investor access is blocked', function (): void {
    $user = User::factory()->investor()->blocked()->create();
    $user->forceFill(['stripe_id' => 'cus_investor'])->save();

    $plan = SubscriptionPlan::factory()->create([
        'stripe_price_id' => 'price_investor',
        'action_limit' => 100,
        'voice_minutes_limit' => 60,
    ]);

    $periodStart = now()->startOfMonth()->timestamp;
    $periodEnd = now()->startOfMonth()->addMonth()->timestamp;

    app(SubscriptionEntitlementService::class)->synchronizeStripeSubscription($user, [
        'id' => 'sub_investor',
        'customer' => 'cus_investor',
        'status' => 'active',
        'metadata' => ['type' => 'default'],
        'current_period_start' => $periodStart,
        'current_period_end' => $periodEnd,
        'items' => [
            'data' => [[
                'id' => 'si_investor',
                'quantity' => 1,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'price' => [
                    'id' => 'price_investor',
                    'product' => 'prod_investor',
                ],
            ]],
        ],
    ]);

    $user = $user->fresh();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->hasActiveInvestorAccess())->toBeFalse()
        ->and(app(SubscriptionEntitlementService::class)->hasPlayAccess($user))->toBeTrue()
        ->and(app(SubscriptionEntitlementService::class)->summary($user)['is_complimentary'])->toBeFalse()
        ->and(app(SubscriptionEntitlementService::class)->summary($user)['plan']['name'])->toBe($plan->name);
});

function playableStory(): Story
{
    $story = Story::factory()->create([
        'slug' => 'the-adventure-of-the-speckled-band',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    return $story;
}
