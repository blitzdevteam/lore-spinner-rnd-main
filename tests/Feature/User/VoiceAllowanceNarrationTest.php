<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Exceptions\Subscription\SubscriptionUsageLimitExceededException;
use App\Models\Game;
use App\Models\Story;
use App\Models\SubscriptionEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tells the player to update their plan when narration is longer than the voice time left', function (): void {
    $user = User::factory()->withCompleteProfile()->create(['is_active' => true]);
    $plan = SubscriptionPlan::query()->where('slug', 'free')->firstOrFail();

    SubscriptionEntitlement::query()->create([
        'user_id' => $user->id,
        'subscription_id' => null,
        'subscription_plan_id' => $plan->id,
        'period_start' => now(),
        'period_end' => null,
        'is_one_time' => true,
        'one_time_key' => 'free:'.$user->id,
        'action_limit' => 20,
        'actions_used' => 7,
        'voice_milliseconds_limit' => 600_000,
        'voice_milliseconds_used' => 473_000,
    ]);

    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $prompt = $game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>'.str_repeat('word ', 400).'</p>',
        'choices' => [],
    ]);

    $this->actingAs($user, 'user')
        ->getJson(route('user.games.tts', [$game, $prompt]))
        ->assertStatus(429)
        ->assertJson([
            'message' => SubscriptionUsageLimitExceededException::VOICE_UPGRADE_MESSAGE,
            'reason' => 'voice_allowance_exceeded',
            'pricing_url' => route('pricing'),
        ]);
});
