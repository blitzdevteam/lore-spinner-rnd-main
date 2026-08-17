<?php

declare(strict_types=1);

use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ListPlayerTokenUsages;
use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ViewPlayerTokenUsage;
use App\Filament\Manager\Resources\PlayerTokenUsages\PlayerTokenUsageResource;
use App\Filament\Manager\Resources\PlayerTokenUsages\Schemas\PlayerTokenUsageInfolist;
use App\Filament\Manager\Resources\PlayerTokenUsages\Tables\PlayerTokenUsagesTable;
use App\Filament\Manager\Resources\PlayerTokenUsages\Widgets\TokenProviderEfficiencyChart;
use App\Filament\Manager\Resources\PlayerTokenUsages\Widgets\TokenUsageBreakdownChart;
use App\Filament\Manager\Resources\PlayerTokenUsages\Widgets\TokenUsageOverview;
use App\Filament\Manager\Resources\PlayerTokenUsages\Widgets\TokenUsageTrendChart;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\PlayerTokenUsage;
use App\Models\Story;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

it('registers token usage as a standalone read-only manager resource', function () {
    expect(PlayerTokenUsageResource::getModel())->toBe(PlayerTokenUsage::class)
        ->and(PlayerTokenUsageResource::getSlug())->toBe('token-usage')
        ->and(PlayerTokenUsageResource::getNavigationLabel())->toBe('Token Usage')
        ->and(PlayerTokenUsageResource::getNavigationGroup())->toBeNull()
        ->and(array_keys(PlayerTokenUsageResource::getPages()))->toBe(['index', 'view'])
        ->and(PlayerTokenUsageResource::getWidgets())->toBe([
            TokenUsageOverview::class,
            TokenUsageTrendChart::class,
            TokenUsageBreakdownChart::class,
            TokenProviderEfficiencyChart::class,
        ]);
});

it('provides focused ledger columns filters totals and only a view action', function () {
    $table = PlayerTokenUsagesTable::configure(
        Table::make(app(ListPlayerTokenUsages::class)),
    );

    expect(array_keys($table->getColumns()))
        ->toContain('occurred_at', 'story.title', 'game_id', 'model', 'total_tokens', 'total_cost_usd')
        ->and(array_keys($table->getFilters()))
        ->toContain('quick_range', 'occurred_at', 'story_id', 'user_id', 'game_id', 'model', 'kind', 'session_number', 'playable_moment_id', 'chapter', 'game_scope', 'usage_reported')
        ->and($table->getRecordActions())->toHaveCount(1)
        ->and($table->getRecordActions()[0])->toBeInstanceOf(ViewAction::class)
        ->and($table->getToolbarActions())->toBe([]);
});

it('uses a readable page identity and one full-width detail surface', function () {
    $usage = tokenUsageDetailRecord();
    $page = app(ViewPlayerTokenUsage::class);
    $page->record = $usage;

    $schema = PlayerTokenUsageInfolist::configure(
        Schema::make($page)->record($usage),
    );
    $components = array_values($schema->getComponents());

    expect($page->getTitle())->toBe('Token usage details')
        ->and($page->getRecordTitle())->toBe('Request #01PROMPT')
        ->and($page->getBreadcrumb())->toBe('Details')
        ->and($page->getSubheading())->toContain('The Glass Harbor', 'ava@example.test')
        ->and($components)->toHaveCount(1)
        ->and($components[0])->toBeInstanceOf(View::class)
        ->and($components[0]->getView())
        ->toBe('filament.manager.resources.player-token-usages.usage-detail');
});

it('renders a compact hierarchy with long event attribution collapsed', function () {
    $html = view(
        'filament.manager.resources.player-token-usages.usage-detail',
        ['usage' => tokenUsageDetailRecord()],
    )->render();

    expect($html)->toContain(
        'Request overview',
        'Token composition',
        'Gameplay context',
        'Request identity',
        'Story attribution',
        'Show remaining 2 events',
        'System prompt and output',
        'You are the narrator for The Glass Harbor.',
        'The harbor lights appeared through the rain.',
        'Stored price breakdown',
        'Pricing metadata and provider payload',
    );
});

it('explains legacy session scope instead of displaying inferred event links', function () {
    $usage = tokenUsageDetailRecord();
    $usage->forceFill([
        'playable_moment_id' => null,
        'moment_key' => null,
    ]);

    $html = view(
        'filament.manager.resources.player-token-usages.usage-detail',
        ['usage' => $usage],
    )->render();

    expect($html)->toContain(
        'Session-level only',
        'Exact event attribution is unavailable',
        'Assigning it to every event would overstate event and chapter consumption.',
    )->not->toContain(
        'Show remaining 2 events',
        '>Event 1<',
    );
});

function tokenUsageDetailRecord(): PlayerTokenUsage
{
    $usage = (new PlayerTokenUsage)->forceFill([
        'game_id' => '01GAME00000000000000000000',
        'prompt_reference_id' => '01PROMPT000000000000000000',
        'source_prompt_reference_id' => '01SOURCE000000000000000000',
        'story_cycle_number' => 2,
        'session_number' => 3,
        'playable_moment_id' => 42,
        'moment_key' => 'S3_M02',
        'kind' => PlayerTokenUsage::KIND_TURN,
        'system_prompt' => 'You are the narrator for The Glass Harbor.',
        'generated_output' => 'The harbor lights appeared through the rain.',
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-6',
        'currency' => 'USD',
        'pricing_version' => 'anthropic-2026-08-01',
        'pricing_effective_from' => '2026-08-01T00:00:00Z',
        'pricing_source' => 'https://example.test/pricing',
        'input_price_per_million_usd' => '3.000000',
        'output_price_per_million_usd' => '15.000000',
        'cache_write_price_per_million_usd' => '3.750000',
        'cache_read_price_per_million_usd' => '0.300000',
        'input_tokens' => 1200,
        'output_tokens' => 300,
        'cache_write_input_tokens' => 100,
        'cache_read_input_tokens' => 200,
        'cached_input_tokens' => 300,
        'total_input_tokens' => 1500,
        'total_tokens' => 1800,
        'reasoning_tokens' => 40,
        'input_cost_usd' => '0.003600000000',
        'output_cost_usd' => '0.004500000000',
        'cache_write_cost_usd' => '0.000375000000',
        'cache_read_cost_usd' => '0.000060000000',
        'total_cost_usd' => '0.008535000000',
        'usage_reported' => true,
        'raw_usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 300],
        'occurred_at' => '2026-08-10T13:27:15Z',
    ]);
    $usage->setRelation('story', (new Story)->forceFill(['title' => 'The Glass Harbor']));
    $usage->setRelation('user', (new User)->forceFill(['email' => 'ava@example.test']));

    $chapter = (new Chapter)->forceFill([
        'story_id' => 1,
        'position' => 1,
        'title' => 'The Arrival',
    ]);
    $chapter->setAttribute('id', 1);
    $usage->setRelation('chapters', new Collection([$chapter]));

    $events = new Collection;
    foreach (range(1, 8) as $position) {
        $event = (new Event)->forceFill([
            'chapter_id' => 1,
            'position' => $position,
            'session_number' => 3,
            'title' => "Event {$position}",
        ]);
        $event->setAttribute('id', $position);
        $events->push($event);
    }
    $usage->setRelation('events', $events);

    return $usage;
}
