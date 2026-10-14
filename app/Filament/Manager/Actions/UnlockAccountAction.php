<?php

declare(strict_types=1);

namespace App\Filament\Manager\Actions;

use App\Contracts\LockableAccount;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

final class UnlockAccountAction
{
    public static function make(): Action
    {
        return Action::make('unlockAccount')
            ->label('Unlock account')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Unlock this account?')
            ->modalDescription('The account can sign in again immediately. Otherwise the lock ends one hour after the fifth failed attempt.')
            ->successNotificationTitle('Account unlocked')
            ->visible(fn (Model $record): bool => $record instanceof LockableAccount && $record->isLoginLocked())
            ->action(function (Model $record): void {
                if (! $record instanceof LockableAccount) {
                    return;
                }

                $record->clearLoginLockout();
            });
    }

    public static function column(): TextColumn
    {
        return TextColumn::make('locked_until')
            ->label('Login')
            ->badge()
            ->placeholder('Open')
            ->formatStateUsing(function (mixed $state, Model $record): string {
                if (! $record instanceof LockableAccount) {
                    return 'Open';
                }

                return $record->isLoginLocked() ? 'Locked' : 'Open';
            })
            ->color(fn (Model $record): string => $record instanceof LockableAccount && $record->isLoginLocked() ? 'danger' : 'gray');
    }
}
