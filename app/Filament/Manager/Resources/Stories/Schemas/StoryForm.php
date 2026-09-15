<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Stories\Schemas;

use App\Enums\Story\StoryRatingEnum;
use App\Filament\Forms\Components\MediaFileUpload;
use App\Models\Creator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class StoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->heading('Story details')
                    ->schema([
                        Fieldset::make('Basic information')
                            ->schema([
                                Select::make('category_id')
                                    ->label('Category')
                                    ->searchable()
                                    ->preload()
                                    ->relationship('category', 'title')
                                    ->required(),
                                Select::make('creator_id')
                                    ->searchable()
                                    ->required()
                                    ->preload()
                                    ->relationship('creator')
                                    ->getOptionLabelFromRecordUsing(fn (Creator $record) => $record->full_name),
                                Select::make('rating')
                                    ->label('Rating')
                                    ->options(StoryRatingEnum::class)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Textarea::make('teaser')
                            ->required()
                            ->helperText('Provide a short teaser of the story without revealing spoilers.')
                            ->rows(3)
                            ->columnSpan(2),
                        Fieldset::make('Images')
                            ->schema([
                                MediaFileUpload::make('cover')
                                    ->label('Cover Image')
                                    ->imageEditor()
                                    ->collection('cover')
                                    ->image()
                                    ->required()
                                    ->columnSpanFull(),
                                MediaFileUpload::make('gallery')
                                    ->label('Gallery Images')
                                    ->collection('gallery')
                                    ->image()
                                    ->imageEditor()
                                    ->multiple()
                                    ->panelLayout('grid')
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
