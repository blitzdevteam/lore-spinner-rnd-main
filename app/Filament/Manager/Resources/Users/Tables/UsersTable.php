<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Users\Tables;

use App\Actions\Investor\UpdateInvestorAccessAction;
use App\Enums\UserType;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['games', 'bookmarkedStories', 'comments']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Role')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state instanceof UserType) {
                            return $state->getLabel();
                        }

                        if (is_string($state)) {
                            return UserType::tryFrom($state)?->getLabel() ?? $state;
                        }

                        return UserType::User->getLabel();
                    })
                    ->color(fn (mixed $state): string => UserType::isInvestor($state) ? 'warning' : 'gray'),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->label('Email address')
                    ->placeholder('Waiting for investor')
                    ->formatStateUsing(function (?string $state, User $record): string {
                        if ($record->isInvestor() && ! $record->hasCompletedSetup()) {
                            return 'Waiting for investor';
                        }

                        return $state ?? '';
                    })
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->sortable()
                    ->boolean(),
                TextColumn::make('games_count')
                    ->label('Games')
                    ->sortable(),
                TextColumn::make('bookmarked_stories_count')
                    ->label('Bookmarks')
                    ->sortable(),
                TextColumn::make('comments_count')
                    ->label('Comments')
                    ->sortable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Signed Up')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(UserType::class),
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
                    ->visible(fn (User $record): bool => $record->isInvestor() && $record->access_enabled)
                    ->action(fn (User $record): User => app(UpdateInvestorAccessAction::class)->block($record)),
                Action::make('restoreAccess')
                    ->label('Restore test access')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Restore test access?')
                    ->modalDescription('This investor will receive a new access window on the assigned plan.')
                    ->successNotificationTitle('Test access restored')
                    ->visible(fn (User $record): bool => $record->isInvestor() && ! $record->hasActiveAccess())
                    ->action(fn (User $record): User => app(UpdateInvestorAccessAction::class)->restore($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
