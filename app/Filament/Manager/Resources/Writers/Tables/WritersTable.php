<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Writers\Tables;

use App\Filament\Manager\Actions\UnlockAccountAction;
use App\Models\Writer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WritersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width('60px'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
                UnlockAccountAction::column(),
            ])
            ->filters([])
            ->recordActions([
                UnlockAccountAction::make(),
                Action::make('login_as')
                    ->label('Login as Writer')
                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                    ->color('warning')
                    ->url(fn (Writer $record): string => route('manager.login-as-writer', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
