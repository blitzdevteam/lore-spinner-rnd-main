<?php

declare(strict_types=1);

use App\Filament\Manager\Pages\StoryAnalyticsPage;
use App\Support\Analytics;
use Illuminate\Support\Carbon;
use Tests\Support\Analytics\AnalyticsTestContext;
use Tests\Support\Analytics\AnalyticsTestLogger;

beforeEach(function () {
    $this->ctx = AnalyticsTestContext::make();
    $this->page = app(StoryAnalyticsPage::class);
});

afterEach(function () {
    $this->ctx->cleanup();
});

describe('story analytics dashboard queries', function () {
    it('returns expected per-story metrics from seeded fixtures', function () {
        $story = $this->ctx->createStory(['title' => 'Analytics Test Story']);

        $userA = $this->ctx->createUser();
        $userB = $this->ctx->createUser();

        $started = $this->ctx->createGame($userA, $story);
        $this->ctx->startSession($started, sessionNumber: 1);
        $this->ctx->startSession($started, sessionNumber: 2, startedAt: Carbon::parse('2026-06-04'));

        $completed = $this->ctx->createGame($userB, $story);
        $s1 = $this->ctx->startSession($completed, sessionNumber: 1);
        $this->ctx->completeSession($s1);
        $this->ctx->recordCompletion($completed, storyCycleNumber: 1);
        $this->ctx->recordReplay($completed, Carbon::parse('2026-06-08'));

        $metrics = $this->page->getStoryMetrics();
        $row = $metrics->firstWhere('id', $story->id);

        expect($row)->not->toBeNull();
        expect((int) $row->starts)->toBe(2);
        expect((int) $row->unique_completed)->toBe(1);
        expect((int) $row->completion_events)->toBe(1);
        expect((float) $row->completion_rate)->toBe(50.0);
        expect((int) $row->incomplete)->toBe(1);
        expect((int) $row->replay_events)->toBe(1);
        expect((int) $row->unique_replayers)->toBe(1);

        $progression = $this->page->getStoryProgression()->firstWhere('story_id', $story->id);
        expect((int) $progression->starts)->toBe(2);
        expect((int) $progression->completions)->toBe(1);
        expect($progression->reached[2] ?? null)->toBe(1);

        $funnel = $this->page->getSessionFunnels()->get($story->id);
        expect($funnel)->not->toBeNull();
        expect($funnel->count())->toBeGreaterThan(0);

        AnalyticsTestLogger::log('story_analytics_dashboard', [
            'story_id' => $story->id,
            'metrics' => [
                'starts' => (int) $row->starts,
                'unique_completed' => (int) $row->unique_completed,
                'completion_events' => (int) $row->completion_events,
                'completion_rate' => (float) $row->completion_rate,
                'incomplete' => (int) $row->incomplete,
                'replay_events' => (int) $row->replay_events,
                'unique_replayers' => (int) $row->unique_replayers,
            ],
            'progression' => [
                'starts' => (int) $progression->starts,
                'completions' => (int) $progression->completions,
                'reached' => $progression->reached,
            ],
            'funnel_sessions' => $funnel?->pluck('session_number')->all(),
            'tracked' => $this->ctx->trackedCounts(),
        ]);
    });

    it('leaves no tracked fixtures in database after cleanup', function () {
        $story = $this->ctx->createStory();
        $user = $this->ctx->createUser();
        $game = $this->ctx->createGame($user, $story);
        $this->ctx->startSession($game);
        $this->ctx->recordCompletion($game);

        $trackedBefore = $this->ctx->trackedCounts();
        expect($trackedBefore['games'])->toBe(1);

        $this->ctx->cleanup();

        AnalyticsTestLogger::log('self_cleaning_cleanup', [
            'tracked_before_cleanup' => $trackedBefore,
            'games_remaining' => App\Models\Game::query()->where('id', $game->id)->count(),
        ]);

        expect(App\Models\Game::query()->where('id', $game->id)->count())->toBe(0);
    });

    it('keeps preview and pre-baseline games out of cohort rates', function () {
        $story = $this->ctx->createStory();
        $user = $this->ctx->createUser();

        $this->ctx->createGame($user, $story);

        $legacy = $this->ctx->createGame($user, $story, [
            'created_at' => Analytics::baseline()->copy()->subDay(),
            'updated_at' => Analytics::baseline()->copy()->subDay(),
        ]);
        $this->ctx->recordCompletion($legacy);

        $preview = $this->ctx->createGame($user, $story, ['is_preview' => true]);
        $this->ctx->recordCompletion($preview);

        $row = $this->page->getStoryMetrics()->firstWhere('id', $story->id);

        expect($row)->not->toBeNull()
            ->and((int) $row->starts)->toBe(1)
            ->and((int) $row->unique_completed)->toBe(0)
            ->and((float) $row->completion_rate)->toBe(0.0)
            ->and((int) $row->completion_events)->toBe(1);
    });
});
