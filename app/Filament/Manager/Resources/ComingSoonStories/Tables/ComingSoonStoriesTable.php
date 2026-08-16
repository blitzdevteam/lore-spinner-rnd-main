<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\ComingSoonStories\Tables;

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class ComingSoonStoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderBy('section')->orderBy('position'))
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('section')
                    ->badge()
                    ->formatStateUsing(fn (ComingSoonStorySectionEnum $state): string => $state->getLabel())
                    ->color(fn (ComingSoonStorySectionEnum $state): string => match ($state) {
                        ComingSoonStorySectionEnum::HERO => 'info',
                        ComingSoonStorySectionEnum::FEATURED_WORLDS => 'warning',
                        ComingSoonStorySectionEnum::NEW_STORIES => 'success',
                        ComingSoonStorySectionEnum::COMING_SOON => 'gray',
                    }),
                TextColumn::make('position')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('section')
                    ->options(ComingSoonStorySectionEnum::class),
                TernaryFilter::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
