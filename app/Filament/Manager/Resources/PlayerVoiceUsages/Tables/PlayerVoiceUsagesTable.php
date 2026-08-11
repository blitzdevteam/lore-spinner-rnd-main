<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages\Tables;

use App\Models\Game;
use App\Models\PlayerVoiceUsage;
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

final class PlayerVoiceUsagesTable
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
                    ->tooltip(static fn (PlayerVoiceUsage $record): string => $record->game_id)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(static fn (string $state): string => self::providerLabel($state))
                    ->sortable(),
                TextColumn::make('model')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('session_number')
                    ->label('Cycle / Session')
                    ->formatStateUsing(static fn (PlayerVoiceUsage $record): string => "C{$record->story_cycle_number} · S{$record->session_number}")
                    ->badge()
                    ->sortable(),
                TextColumn::make('input_characters')
                    ->label('Input chars')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Filtered chars')->numeric()),
                TextColumn::make('billable_units')
                    ->label('Billable units')
                    ->numeric()
                    ->description(static fn (PlayerVoiceUsage $record): string => $record->billing_unit)
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Filtered units')->numeric()),
                TextColumn::make('estimated_cost_usd')
                    ->label('Estimated cost')
                    ->formatStateUsing(static fn (mixed $state): string => is_numeric($state)
                        ? '$'.self::formatUsd($state)
                        : 'Unpriced')
                    ->color(static fn (mixed $state): string => is_numeric($state) ? 'success' : 'warning')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Filtered estimate')
                            ->formatStateUsing(static fn (mixed $state): string => '$'.self::formatUsd($state)),
                    ),
                TextColumn::make('latency_ms')
                    ->label('Latency')
                    ->formatStateUsing(static fn (int $state): string => number_format($state).' ms')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('audio_bytes')
                    ->label('Audio size')
                    ->formatStateUsing(static fn (int $state): string => self::formatBytes($state))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('voice_id')
                    ->label('Voice')
                    ->copyable()
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pricing_available')
                    ->label('Pricing')
                    ->badge()
                    ->formatStateUsing(static fn (bool $state): string => $state ? 'Priced' : 'Missing')
                    ->color(static fn (bool $state): string => $state ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider_request_id')
                    ->label('Provider request')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('prompt_reference_id')
                    ->label('Prompt reference')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('quick_range')
                    ->label('Quick range')
                    ->options([
                        '7' => 'Last 7 days',
                        '30' => 'Last 30 days',
                        '90' => 'Last 90 days',
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        $days = is_numeric($value) ? (int) $value : 0;

                        return in_array($days, [7, 30, 90], true)
                            ? $query->where(
                                'occurred_at',
                                '>=',
                                CarbonImmutable::now()->subDays($days - 1)->startOfDay(),
                            )
                            : $query;
                    }),
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
                            $query->where('occurred_at', '>=', CarbonImmutable::parse($from)->startOfDay());
                        }

                        $until = $data['until'] ?? null;
                        if (is_string($until) && $until !== '') {
                            $query->where('occurred_at', '<=', CarbonImmutable::parse($until)->endOfDay());
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
                SelectFilter::make('provider')
                    ->options(self::providerOptions()),
                SelectFilter::make('model')
                    ->options(fn (): array => PlayerVoiceUsage::query()
                        ->distinct()
                        ->orderBy('model')
                        ->pluck('model', 'model')
                        ->all())
                    ->searchable(),
                SelectFilter::make('story_id')
                    ->label('Story')
                    ->relationship('story', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user_id')
                    ->label('Player')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('game_id')
                    ->label('Game')
                    ->relationship(
                        'game',
                        'id',
                        modifyQueryUsing: static fn (Builder $query): Builder => $query
                            ->whereHas('voiceUsages')
                            ->with(['story:id,title', 'user:id,email'])
                            ->latest('games.created_at'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        static fn (Game $record): string => implode(' · ', [
                            mb_substr($record->id, 0, 10).'…',
                            $record->story->title,
                            $record->user->email,
                        ]),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('session_number')
                    ->label('Session')
                    ->options(collect(range(1, 20))
                        ->mapWithKeys(static fn (int $session): array => [$session => "Session {$session}"])
                        ->all()),
                SelectFilter::make('billing_unit')
                    ->label('Billing unit')
                    ->options([
                        'characters' => 'Characters',
                        'credits' => 'Credits',
                    ]),
                SelectFilter::make('game_scope')
                    ->label('Game type')
                    ->options([
                        'player' => 'Player games',
                        'preview' => 'Preview games',
                    ])
                    ->query(static fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'player' => $query->whereHas('game', fn (Builder $game): Builder => $game->where('is_preview', false)),
                        'preview' => $query->whereHas('game', fn (Builder $game): Builder => $game->where('is_preview', true)),
                        default => $query,
                    }),
                TernaryFilter::make('pricing_available')
                    ->label('Pricing snapshot')
                    ->placeholder('All records')
                    ->trueLabel('Priced')
                    ->falseLabel('Missing pricing'),
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
    private static function providerOptions(): array
    {
        return collect(array_keys((array) config('player-voice-usage.providers', [])))
            ->mapWithKeys(static fn (string $provider): array => [
                $provider => self::providerLabel($provider),
            ])
            ->all();
    }

    private static function providerLabel(string $provider): string
    {
        return match (mb_strtolower($provider)) {
            'elevenlabs' => 'ElevenLabs',
            'deepgram' => 'Deepgram',
            default => str($provider)->headline()->toString(),
        };
    }

    private static function formatUsd(mixed $value): string
    {
        return number_format(is_numeric($value) ? (float) $value : 0.0, 9, '.', '');
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1_024) {
            return number_format($bytes).' B';
        }

        if ($bytes < 1_048_576) {
            return number_format($bytes / 1_024, 1).' KB';
        }

        return number_format($bytes / 1_048_576, 2).' MB';
    }
}
