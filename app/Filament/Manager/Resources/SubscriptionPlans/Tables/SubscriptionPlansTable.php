<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\SubscriptionPlans\Tables;

use App\Models\SubscriptionPlan;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_cents')
                    ->label('Monthly price')
                    ->formatStateUsing(static fn (int $state, SubscriptionPlan $record): string => self::formattedPrice($state, $record->currency))
                    ->sortable(),
                TextColumn::make('action_limit')
                    ->label('Actions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('voice_minutes_limit')
                    ->label('Voice')
                    ->suffix(' min')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stripe_price_id')
                    ->label('Stripe price')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->striped();
    }

    private static function formattedPrice(int $priceCents, string $currency): string
    {
        $amount = number_format($priceCents / 100, 2);

        return mb_strtoupper($currency) === 'USD'
            ? '$'.$amount
            : mb_strtoupper($currency).' '.$amount;
    }
}
