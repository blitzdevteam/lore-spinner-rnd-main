<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\ComingSoonStories\Schemas;

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ComingSoonStoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->heading('Coming soon story details')
                    ->schema([
                        Toggle::make('is_active')
                            ->required()
                            ->default(true)
                            ->columnSpanFull(),
                        Fieldset::make('Story metadata')
                            ->columns(3)
                            ->schema([
                                Select::make('section')
                                    ->options(ComingSoonStorySectionEnum::class)
                                    ->required(),
                                TextInput::make('position')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0),
                                TextInput::make('slug')
                                    ->maxLength(255)
                                    ->helperText('Optional slug for matching theme tags and story route context.'),
                            ])
                            ->columnSpanFull(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Textarea::make('teaser')
                            ->rows(4)
                            ->required()
                            ->columnSpan(2),
                        Fieldset::make('Display data')
                            ->columns(3)
                            ->schema([
                                TextInput::make('category')
                                    ->maxLength(255),
                                TextInput::make('rating')
                                    ->maxLength(255)
                                    ->default('Everyone'),
                                TextInput::make('author')
                                    ->maxLength(255),
                                TextInput::make('series_label')
                                    ->maxLength(255)
                                    ->helperText('Optional hero badge text (e.g. Lore Spinner Classic).'),
                                TextInput::make('image_position')
                                    ->maxLength(255)
                                    ->placeholder('object-center md:object-top')
                                    ->helperText('Tailwind object-position classes for the hero image alignment.'),
                            ])
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Card / Hero image')
                            ->collection('cover')
                            ->image()
                            ->imageEditor()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
