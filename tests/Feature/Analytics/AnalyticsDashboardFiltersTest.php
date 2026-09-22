<?php

declare(strict_types=1);

use App\Filament\Manager\Pages\AnalyticsDashboard;
use App\Support\Analytics;
use Illuminate\Support\Carbon;
use Tests\Support\Analytics\AnalyticsTestContext;

beforeEach(function (): void {
    $this->ctx = AnalyticsTestContext::make();
    $this->page = app(AnalyticsDashboard::class);
});

afterEach(function (): void {
    $this->ctx->cleanup();
});

it('filters dashboard metrics by date, player, and story', function (): void {
    $storyA = $this->ctx->createStory(['title' => 'Filtered Story']);
    $storyB = $this->ctx->createStory(['title' => 'Other Story']);
    $userA = $this->ctx->createUser([
        'created_at' => Carbon::parse('2026-06-04 09:00:00'),
        'updated_at' => Carbon::parse('2026-06-04 09:00:00'),
    ]);
    $userB = $this->ctx->createUser([
        'created_at' => Carbon::parse('2026-06-04 09:00:00'),
        'updated_at' => Carbon::parse('2026-06-04 09:00:00'),
    ]);

    $this->ctx->createGame($userA, $storyA, [
        'created_at' => Carbon::parse('2026-06-04 10:00:00'),
        'updated_at' => Carbon::parse('2026-06-04 10:00:00'),
    ]);
    $this->ctx->createGame($userB, $storyA, [
        'created_at' => Carbon::parse('2026-06-04 11:00:00'),
        'updated_at' => Carbon::parse('2026-06-04 11:00:00'),
    ]);
    $this->ctx->createGame($userA, $storyB, [
        'created_at' => Carbon::parse('2026-06-04 12:00:00'),
        'updated_at' => Carbon::parse('2026-06-04 12:00:00'),
    ]);

    $this->page->filters = [
        'range' => 'custom',
        'from' => '2026-06-04',
        'to' => '2026-06-04',
        'user_id' => $userA->id,
        'story_id' => $storyA->id,
    ];

    $funnel = $this->page->getEngagementFunnel();
    $topStories = $this->page->getTopStories();
    $acquisition = $this->page->getAcquisitionMetrics();
    $summary = $this->page->getFilterSummary();

    expect($funnel[0]['value'])->toBe(1)
        ->and($topStories)->toHaveCount(1)
        ->and($topStories[0]['title'])->toBe('Filtered Story')
        ->and($topStories[0]['starts'])->toBe(1)
        ->and($acquisition[1]['value'])->toBe(1)
        ->and($summary['user'])->toContain($userA->email)
        ->and($summary['story'])->toBe('Filtered Story')
        ->and($summary['from'])->toBe('2026-06-04')
        ->and($summary['to'])->toBe('2026-06-04');
});

it('resets quick ranges and custom dates to the baseline view', function (): void {
    $this->page->filters = [
        'range' => 'custom',
        'from' => '2026-06-04',
        'to' => '2026-06-05',
        'user_id' => 123,
        'story_id' => 456,
    ];

    $this->page->setDateRange('30');

    expect($this->page->getAnalyticsFilters())
        ->toMatchArray([
            'range' => '30',
            'from' => null,
            'to' => null,
            'user_id' => 123,
            'story_id' => 456,
        ]);

    $this->page->resetFilters();

    expect($this->page->getAnalyticsFilters())->toMatchArray([
        'range' => 'all',
        'from' => null,
        'to' => null,
        'user_id' => null,
        'story_id' => null,
    ]);
});

it('populates visible dates when a custom range is selected', function (): void {
    $this->page->setDateRange('custom');

    expect($this->page->getAnalyticsFilters())
        ->toMatchArray([
            'range' => 'custom',
            'from' => Analytics::baselineDateString(),
            'to' => Analytics::asOf()->toDateString(),
        ]);
});
