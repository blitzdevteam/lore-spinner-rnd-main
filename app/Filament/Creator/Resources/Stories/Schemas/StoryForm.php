<?php

declare(strict_types=1);

namespace App\Filament\Creator\Resources\Stories\Schemas;

use App\Enums\Story\StoryRatingEnum;
use App\Filament\Forms\Components\MediaFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                                Select::make('rating')
                                    ->label('Rating')
                                    ->options(StoryRatingEnum::class)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug()))
                            ->columnSpan(2),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated from title, but you can edit it if needed.')
                            ->columnSpan(2),
                        Textarea::make('teaser')
                            ->required()
                            ->helperText('Provide a short teaser of the story without revealing spoilers.')
                            ->rows(3)
                            ->columnSpan(2),
                        RichEditor::make('opening')
                            ->helperText('This will be used as the opening of the story when it is published')
                            ->toolbarButtons([
                                'redo', 'undo', 'underline', 'italic', 'bold',
                            ])
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
                        Fieldset::make('scripts')
                            ->schema([
                                Checkbox::make('use_script_upload')
                                    ->label('Upload a script and auto-chapterize')
                                    ->helperText('Enable this to upload a .txt script. Chapters will be generated automatically, and you can edit them afterward.')
                                    ->default(false)
                                    ->columnSpanFull()
                                    ->live(),
                                Checkbox::make('boiler_enabled')
                                    ->label('Enable Boiler pacing')
                                    ->helperText('Compile this adaptation into paced playable moments. During R&D this is off by default.')
                                    ->default(false)
                                    ->visibleOn('create')
                                    ->columnSpanFull(),
                                MediaFileUpload::make('script')
                                    ->label('Story Script')
                                    ->helperText('Upload the story script file here and chapterizing will be done automatically.')
                                    ->placeholder('Only `.txt` file are allowed')
                                    ->collection('script')
                                    ->acceptedFileTypes(['text/plain'])
                                    ->preserveFilenames()
                                    ->downloadable()
                                    ->openable()
                                    ->required(fn (Get $get): bool => (bool) $get('use_script_upload'))
                                    ->visible(fn (Get $get): bool => (bool) $get('use_script_upload'))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
