<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Tables;

use App\Models\PlayerTokenUsage;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PlayerTokenUsagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('story.title')
                    ->label('Story')
                    ->searchable()
                    ->sortable()
                    ->limit(28),
                TextColumn::make('user.email')
                    ->label('Player')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('game_id')
                    ->label('Game')
                    ->formatStateUsing(static fn (string $state): string => mb_substr($state, 0, 10).'…')
                    ->tooltip(static fn (PlayerTokenUsage $record): string => $record->game_id)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('kind')
                    ->label('Request')
                    ->badge()
                    ->formatStateUsing(static fn (string $state): string => match ($state) {
                        PlayerTokenUsage::KIND_BEGIN => 'Opening',
                        PlayerTokenUsage::KIND_NEXT_SESSION => 'Next session',
                        default => 'Player turn',
                    })
                    ->color(static fn (string $state): string => match ($state) {
                        PlayerTokenUsage::KIND_BEGIN => 'info',
                        PlayerTokenUsage::KIND_NEXT_SESSION => 'warning',
                        default => 'primary',
                    }),
                TextColumn::make('session_number')
                    ->label('Cycle / Session')
                    ->formatStateUsing(static fn (PlayerTokenUsage $record): string => "C{$record->story_cycle_number} · S{$record->session_number}")
                    ->badge()
                    ->sortable(),
                TextColumn::make('moment_key')
                    ->label('Moment')
                    ->placeholder('Legacy')
                    ->toggleable(),
                TextColumn::make('model')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('input_tokens')
                    ->label('Input')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('cached_input_tokens')
                    ->label('Cached')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->tooltip(static fn (PlayerTokenUsage $record): string => number_format($record->cache_read_input_tokens).' read · '.number_format($record->cache_write_input_tokens).' written')
                    ->toggleable(),
                TextColumn::make('output_tokens')
                    ->label('Output')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('total_tokens')
                    ->label('Total tokens')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Filtered tokens')
                            ->numeric(),
                    ),
                TextColumn::make('total_cost_usd')
                    ->label('Cost')
                    ->formatStateUsing(static fn (mixed $state): string => '$'.self::formatUsd($state))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Filtered cost')
                            ->formatStateUsing(static fn (mixed $state): string => '$'.self::formatUsd($state)),
                    ),
                TextColumn::make('usage_reported')
                    ->label('Usage data')
                    ->badge()
                    ->formatStateUsing(static fn (bool $state): string => $state ? 'Reported' : 'Missing')
                    ->color(static fn (bool $state): string => $state ? 'success' : 'danger')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('chapters.title')
                    ->label('Chapters')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('events.title')
                    ->label('Events')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('prompt_reference_id')
                    ->label('Prompt reference')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('occurred_at')
                    ->label('Date range')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->columns(2)
                    ->query(static function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        if (is_string($from) && $from !== '') {
                            $query->where(
                                'occurred_at',
                                '>=',
                                CarbonImmutable::parse($from)->startOfDay(),
                            );
                        }

                        $until = $data['until'] ?? null;
                        if (is_string($until) && $until !== '') {
                            $query->where(
                                'occurred_at',
                                '<=',
                                CarbonImmutable::parse($until)->endOfDay(),
                            );
                        }

                        return $query;
                    })
                    ->indicateUsing(static function (array $data): array {
                        $indicators = [];
                        $from = $data['from'] ?? null;
                        if (is_string($from) && $from !== '') {
                            $indicators[] = 'From '.CarbonImmutable::parse($from)->toFormattedDateString();
                        }

                        $until = $data['until'] ?? null;
                        if (is_string($until) && $until !== '') {
                            $indicators[] = 'Until '.CarbonImmutable::parse($until)->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('story_id')
                    ->label('Story')
                    ->relationship('story', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('model')
                    ->options(self::modelOptions())
                    ->searchable(),
                SelectFilter::make('kind')
                    ->label('Request type')
                    ->options([
                        PlayerTokenUsage::KIND_BEGIN => 'Opening',
                        PlayerTokenUsage::KIND_TURN => 'Player turn',
                        PlayerTokenUsage::KIND_NEXT_SESSION => 'Next session',
                    ]),
                SelectFilter::make('session_number')
                    ->label('Session')
                    ->options(self::sessionOptions()),
                SelectFilter::make('playable_moment_id')
                    ->label('Moment')
                    ->relationship('playableMoment', 'moment_key')
                    ->searchable(),
                SelectFilter::make('chapter')
                    ->label('Chapter')
                    ->relationship('chapters', 'title')
                    ->searchable(),
                SelectFilter::make('game_scope')
                    ->label('Game type')
                    ->options([
                        'player' => 'Player games',
                        'preview' => 'Preview games',
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'player' => $query->whereHas('game', fn (Builder $game): Builder => $game->where('is_preview', false)),
                            'preview' => $query->whereHas('game', fn (Builder $game): Builder => $game->where('is_preview', true)),
                            default => $query,
                        };
                    }),
                TernaryFilter::make('usage_reported')
                    ->label('Usage data')
                    ->placeholder('All records')
                    ->trueLabel('Reported')
                    ->falseLabel('Missing'),
            ])
            ->filtersFormColumns(3)
            ->deferFilters()
            ->defaultSort('occurred_at', 'desc')
            ->paginationPageOptions([25, 50, 100])
            ->striped()
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    /** @return array<string, string> */
    private static function modelOptions(): array
    {
        return collect(array_keys((array) config('player-token-usage.models', [])))
            ->mapWithKeys(static fn (string $model): array => [$model => $model])
            ->all();
    }

    /** @return array<int, string> */
    private static function sessionOptions(): array
    {
        return collect(range(1, 20))
            ->mapWithKeys(static fn (int $session): array => [$session => "Session {$session}"])
            ->all();
    }

    private static function formatUsd(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.000000000';
        }

        return number_format((float) $value, 9, '.', '');
    }
}
