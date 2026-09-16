<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Investor;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an investor play stories without a paid plan', function (): void {
    $investor = Investor::factory()->create();
    $user = $investor->user;
    $story = playableStory();

    expect(app(SubscriptionEntitlementService::class)->hasPlayAccess($user))->toBeTrue()
        ->and(app(SubscriptionEntitlementService::class)->summary($user)['is_complimentary'])->toBeTrue();

    $this->actingAs($user, 'user')
        ->post(route('user.games.store'), [
            'story_id' => $story->id,
        ])
        ->assertRedirect();

    expect($user->games()->where('story_id', $story->id)->exists())->toBeTrue();
});

it('blocks complimentary play until the investor buys a subscription', function (): void {
    $investor = Investor::factory()->blocked()->create();
    $user = $investor->user;
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

it('does not meter investor story usage while complimentary access is active', function (): void {
    $investor = Investor::factory()->create();
    $user = $investor->user;
    $game = $user->games()->make();

    expect(app(SubscriptionEntitlementService::class)->shouldTrackUsageFor($user, $game))->toBeFalse();
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
