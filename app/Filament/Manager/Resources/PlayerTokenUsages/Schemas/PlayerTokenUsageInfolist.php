<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Schemas;

use App\Models\PlayerTokenUsage;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PlayerTokenUsageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request summary')
                ->columns(4)
                ->schema([
                    TextEntry::make('occurred_at')
                        ->label('Occurred')
                        ->dateTime('M j, Y H:i:s'),
                    TextEntry::make('kind')
                        ->label('Request type')
                        ->badge()
                        ->formatStateUsing(static fn (string $state): string => match ($state) {
                            PlayerTokenUsage::KIND_BEGIN => 'Opening',
                            PlayerTokenUsage::KIND_NEXT_SESSION => 'Next session',
                            default => 'Player turn',
                        }),
                    TextEntry::make('model')->badge(),
                    TextEntry::make('total_cost_usd')
                        ->label('Total cost')
                        ->formatStateUsing(static fn (mixed $state): string => '$'.self::formatUsd($state)),
                    TextEntry::make('input_tokens')->label('Input')->numeric(),
                    TextEntry::make('cache_read_input_tokens')->label('Cache read')->numeric(),
                    TextEntry::make('cache_write_input_tokens')->label('Cache write')->numeric(),
                    TextEntry::make('output_tokens')->label('Output')->numeric(),
                    TextEntry::make('reasoning_tokens')->label('Reasoning')->numeric(),
                    TextEntry::make('total_tokens')->label('Total tokens')->numeric(),
                    TextEntry::make('usage_reported')
                        ->label('Usage data')
                        ->badge()
                        ->formatStateUsing(static fn (bool $state): string => $state ? 'Reported' : 'Missing')
                        ->color(static fn (bool $state): string => $state ? 'success' : 'danger'),
                    TextEntry::make('provider')->badge(),
                ]),
            Section::make('Gameplay context')
                ->columns(3)
                ->schema([
                    TextEntry::make('story.title')->label('Story'),
                    TextEntry::make('user.email')->label('Player'),
                    TextEntry::make('game_id')->label('Game')->copyable(),
                    TextEntry::make('story_cycle_number')->label('Story cycle'),
                    TextEntry::make('session_number')->label('Session'),
                    TextEntry::make('moment_key')->label('Moment')->placeholder('Legacy gameplay'),
                    TextEntry::make('prompt_reference_id')->label('Prompt reference')->copyable(),
                    TextEntry::make('source_prompt_reference_id')->label('Source prompt')->copyable()->placeholder('—'),
                    TextEntry::make('chapters.title')
                        ->label('Chapters')
                        ->bulleted()
                        ->placeholder('—'),
                    TextEntry::make('events.title')
                        ->label('Events')
                        ->bulleted()
                        ->placeholder('—')
                        ->columnSpan(2),
                ]),
            Section::make('Pricing snapshot')
                ->columns(4)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextEntry::make('pricing_version')->label('Pricing version'),
                    TextEntry::make('input_price_per_million_usd')->label('Input / MTok')->prefix('$'),
                    TextEntry::make('cache_read_price_per_million_usd')->label('Cache read / MTok')->prefix('$'),
                    TextEntry::make('cache_write_price_per_million_usd')->label('Cache write / MTok')->prefix('$'),
                    TextEntry::make('output_price_per_million_usd')->label('Output / MTok')->prefix('$'),
                    TextEntry::make('input_cost_usd')->label('Input cost')->formatStateUsing(static fn (mixed $state): string => '$'.self::formatUsd($state)),
                    TextEntry::make('cache_read_cost_usd')->label('Cache read cost')->formatStateUsing(static fn (mixed $state): string => '$'.self::formatUsd($state)),
                    TextEntry::make('cache_write_cost_usd')->label('Cache write cost')->formatStateUsing(static fn (mixed $state): string => '$'.self::formatUsd($state)),
                    TextEntry::make('output_cost_usd')->label('Output cost')->formatStateUsing(static fn (mixed $state): string => '$'.self::formatUsd($state)),
                    TextEntry::make('pricing_source')
                        ->label('Official pricing source')
                        ->url(static fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->columnSpanFull(),
                    KeyValueEntry::make('raw_usage')
                        ->label('Provider usage payload')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function formatUsd(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.000000000000';
        }

        return number_format((float) $value, 12, '.', '');
    }
}
