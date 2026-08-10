<?php

declare(strict_types=1);

use App\Filament\Manager\Resources\PlayerTokenUsages\Pages\ListPlayerTokenUsages;
use App\Filament\Manager\Resources\PlayerTokenUsages\PlayerTokenUsageResource;
use App\Filament\Manager\Resources\PlayerTokenUsages\Tables\PlayerTokenUsagesTable;
use App\Models\PlayerTokenUsage;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

it('registers token usage as a standalone read-only manager resource', function () {
    expect(PlayerTokenUsageResource::getModel())->toBe(PlayerTokenUsage::class)
        ->and(PlayerTokenUsageResource::getSlug())->toBe('token-usage')
        ->and(PlayerTokenUsageResource::getNavigationLabel())->toBe('Token Usage')
        ->and(PlayerTokenUsageResource::getNavigationGroup())->toBeNull()
        ->and(array_keys(PlayerTokenUsageResource::getPages()))->toBe(['index', 'view']);
});

it('provides focused ledger columns filters totals and only a view action', function () {
    $table = PlayerTokenUsagesTable::configure(
        Table::make(app(ListPlayerTokenUsages::class)),
    );

    expect(array_keys($table->getColumns()))
        ->toContain('occurred_at', 'story.title', 'game_id', 'model', 'total_tokens', 'total_cost_usd')
        ->and(array_keys($table->getFilters()))
        ->toContain('occurred_at', 'story_id', 'model', 'kind', 'session_number', 'playable_moment_id', 'chapter', 'game_scope', 'usage_reported')
        ->and($table->getRecordActions())->toHaveCount(1)
        ->and($table->getRecordActions()[0])->toBeInstanceOf(ViewAction::class)
        ->and($table->getToolbarActions())->toBe([]);
});
