<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Billings\Tables;

use App\Models\User;
use App\Services\PurchasedPlanName;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;

final class BillingsTable
{
    public static function configure(Table $table): Table
    {
        $planNames = app(PurchasedPlanName::class);

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.email')
                    ->label('Player')
                    ->description(static function (Subscription $record): ?string {
                        $user = $record->user;

                        return $user instanceof User && filled($user->full_name) ? $user->full_name : null;
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stripe_price')
                    ->label('Plan')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => $planNames->label($state))
                    ->color(static fn (?string $state): string => $planNames->recognizes($state) ? 'primary' : 'gray')
                    ->description(static fn (Subscription $record): string => $record->stripe_price ?? 'No single price synchronized')
                    ->sortable(),
                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(static fn (string $state): string => Str::headline($state))
                    ->color(static fn (string $state): string => self::statusColor($state))
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Seats')
                    ->formatStateUsing(static fn (?int $state): string => number_format($state ?? 1))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Purchased')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Access ends')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Renews automatically')
                    ->sortable(),
                TextColumn::make('user.pm_last_four')
                    ->label('Payment method')
                    ->state(static fn (Subscription $record): ?string => self::paymentMethod($record))
                    ->placeholder('Not synchronized')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stripe_id')
                    ->label('Stripe subscription')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.stripe_id')
                    ->label('Stripe customer')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stripe_price')
                    ->label('Plan')
                    ->options(static fn (): array => $planNames->filterOptions()),
                SelectFilter::make('stripe_status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'trialing' => 'Trialing',
                        'past_due' => 'Past due',
                        'unpaid' => 'Unpaid',
                        'incomplete' => 'Incomplete',
                        'incomplete_expired' => 'Incomplete expired',
                        'paused' => 'Paused',
                        'canceled' => 'Canceled',
                    ]),
                SelectFilter::make('user_id')
                    ->label('Player')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->striped();
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'trialing' => 'info',
            'past_due', 'incomplete' => 'warning',
            'unpaid' => 'danger',
            default => 'gray',
        };
    }

    private static function paymentMethod(Subscription $subscription): ?string
    {
        $user = $subscription->user;

        if (! $user instanceof User || blank($user->pm_last_four)) {
            return null;
        }

        $paymentMethodType = is_string($user->pm_type) ? Str::headline($user->pm_type) : 'Card';

        return "{$paymentMethodType} •••• {$user->pm_last_four}";
    }
}
