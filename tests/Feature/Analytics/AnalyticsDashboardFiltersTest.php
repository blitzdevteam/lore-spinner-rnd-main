<?php

declare(strict_types=1);

use App\Filament\Manager\Pages\AnalyticsDashboard;
use App\Models\Manager;
use App\Support\Analytics;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
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

it('searches players by name or email and stories by title', function (): void {
    $story = $this->ctx->createStory(['title' => 'Searchable Lantern Tale']);
    $otherStory = $this->ctx->createStory(['title' => 'Unrelated Chronicle']);
    $user = $this->ctx->createUser([
        'first_name' => 'Maren',
        'last_name' => 'Quill',
        'email' => 'maren.quill@example.com',
    ]);
    $other = $this->ctx->createUser([
        'first_name' => 'Otto',
        'last_name' => 'Vale',
        'email' => 'otto.vale@example.com',
    ]);

    $this->ctx->createGame($user, $story);
    $this->ctx->createGame($other, $otherStory);

    $players = $this->page->getUserOptions('maren.quill');
    $playersByName = $this->page->getUserOptions('Maren Quill');
    $stories = $this->page->getStoryOptions('lantern');

    expect($players)->toHaveKey((string) $user->id)
        ->and($players)->not->toHaveKey((string) $other->id)
        ->and($playersByName)->toHaveKey((string) $user->id)
        ->and($stories)->toHaveKey((string) $story->id)
        ->and($stories)->not->toHaveKey((string) $otherStory->id)
        ->and($stories[(string) $story->id])->toBe('Searchable Lantern Tale');
});

it('makes the player and story filters searchable', function (): void {
    $player = $this->page->playerFilter(Schema::make($this->page))->getFlatFields(withHidden: true)['user_id'];
    $story = $this->page->storyFilter(Schema::make($this->page))->getFlatFields(withHidden: true)['story_id'];

    expect($player)->toBeInstanceOf(Select::class)
        ->and($player->isSearchable())->toBeTrue()
        ->and($player->getPosition())->toBe('bottom')
        ->and($story)->toBeInstanceOf(Select::class)
        ->and($story->isSearchable())->toBeTrue()
        ->and($story->getPosition())->toBe('bottom');
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

it('renders real date inputs that accept a custom range', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('manager'));

    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $from = Analytics::baselineDateString();
    $to = Analytics::asOf()->toDateString();

    try {
        Livewire::test(AnalyticsDashboard::class)
            ->call('setDateRange', 'custom')
            ->assertSeeHtml('id="analytics-date-from"')
            ->assertSeeHtml('type="date"')
            ->assertSeeHtml('value="'.$from.'"')
            ->assertSeeHtml('value="'.$to.'"')
            ->assertDontSeeHtml('<x-filament::input')
            ->set('filters.from', '2026-06-10')
            ->set('filters.to', '2026-06-20')
            ->assertSet('filters.range', 'custom')
            ->assertSet('filters.from', '2026-06-10')
            ->assertSet('filters.to', '2026-06-20');
    } finally {
        $manager->delete();
    }
});
