<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Tables;

use App\Actions\Investor\UpdateInvestorAccessAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class InvestorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->label('Email address')
                    ->placeholder('Waiting for investor')
                    ->formatStateUsing(function (?string $state, User $record): string {
                        if (! $record->hasCompletedSetup()) {
                            return 'Waiting for investor';
                        }

                        return $state ?? '';
                    })
                    ->searchable(),
                TextColumn::make('username')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('duration_months')
                    ->label('Duration')
                    ->formatStateUsing(fn (int $state): string => User::durationOptions()[$state] ?? "{$state} months"),
                TextColumn::make('access_status')
                    ->label('Access')
                    ->badge()
                    ->state(function (User $record): string {
                        if (! $record->access_enabled) {
                            return 'Blocked';
                        }

                        if ($record->isExpired()) {
                            return 'Expired';
                        }

                        return 'Active';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Expired' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('magic_login_url')
                    ->label('Magic link')
                    ->state(fn (User $record): ?string => $record->magicLoginUrl())
                    ->copyable()
                    ->copyMessage('Magic login link copied')
                    ->limit(24)
                    ->placeholder('Unavailable')
                    ->toggleable(),
                TextColumn::make('access_granted_at')
                    ->label('Granted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('access_blocked_at')
                    ->label('Blocked')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('access_enabled')
                    ->label('Test access')
                    ->boolean()
                    ->trueLabel('Enabled')
                    ->falseLabel('Blocked'),
            ])
            ->recordActions([
                Action::make('blockAccess')
                    ->label('Block test access')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Block test access?')
                    ->modalDescription('This investor will need a paid subscription to keep playing stories.')
                    ->successNotificationTitle('Test access blocked')
                    ->visible(fn (User $record): bool => $record->access_enabled)
                    ->action(fn (User $record): User => app(UpdateInvestorAccessAction::class)->block($record)),
                Action::make('restoreAccess')
                    ->label('Restore test access')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Restore test access?')
                    ->modalDescription('This investor will receive a new access window on the assigned plan.')
                    ->successNotificationTitle('Test access restored')
                    ->visible(fn (User $record): bool => ! $record->hasActiveAccess())
                    ->action(fn (User $record): User => app(UpdateInvestorAccessAction::class)->restore($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Removes the selected investor accounts and test access.'),
                ]),
            ]);
    }
}
