<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Tables;

use App\Actions\Investor\UpdateInvestorAccessAction;
use App\Models\Investor;
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
                TextColumn::make('user.full_name')
                    ->label('Name')
                    ->searchable(['user.first_name', 'user.last_name']),
                TextColumn::make('user.email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('user.username')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('access_enabled')
                    ->label('Free access')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Blocked')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('access_granted_at')
                    ->label('Granted')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('access_blocked_at')
                    ->label('Blocked')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('access_enabled')
                    ->label('Free access')
                    ->boolean()
                    ->trueLabel('Active')
                    ->falseLabel('Blocked'),
            ])
            ->recordActions([
                Action::make('blockAccess')
                    ->label('Block free access')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Block complimentary access?')
                    ->modalDescription('This investor will need a paid subscription to keep playing stories.')
                    ->successNotificationTitle('Complimentary access blocked')
                    ->visible(fn (Investor $record): bool => $record->access_enabled)
                    ->action(fn (Investor $record): Investor => app(UpdateInvestorAccessAction::class)->block($record)),
                Action::make('restoreAccess')
                    ->label('Restore free access')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Restore complimentary access?')
                    ->modalDescription('This investor will be able to play stories again without a paid plan.')
                    ->successNotificationTitle('Complimentary access restored')
                    ->visible(fn (Investor $record): bool => ! $record->access_enabled)
                    ->action(fn (Investor $record): Investor => app(UpdateInvestorAccessAction::class)->restore($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
