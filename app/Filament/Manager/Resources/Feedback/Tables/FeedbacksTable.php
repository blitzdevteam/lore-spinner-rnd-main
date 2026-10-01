<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Feedback\Tables;

use App\Models\Feedback;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class FeedbacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->placeholder('Guest')
                    ->searchable(),
                TextColumn::make('content')
                    ->label('Message')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                ImageColumn::make('screenshot_path')
                    ->label('Screenshot')
                    ->getStateUsing(fn (Feedback $record): ?string => $record->screenshotUrl())
                    ->checkFileExistence(false)
                    ->imageHeight(72)
                    ->extraImgAttributes([
                        'class' => 'rounded-md object-cover object-top',
                    ]),
                TextColumn::make('page_url')
                    ->label('Page')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
