<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Worlds\Schemas;

use App\Filament\Forms\Components\MediaFileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class WorldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->heading('World details')
                    ->schema([
                        Toggle::make('is_active')
                            ->required()
                            ->default(true)
                            ->columnSpanFull(),
                        Fieldset::make('Listing')
                            ->columns(2)
                            ->schema([
                                TextInput::make('position')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('Lower numbers appear first on the homepage.'),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Used in the public /worlds URL.'),
                            ])
                            ->columnSpanFull(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (filled($get('slug')) || blank($state)) {
                                    return;
                                }

                                $set('slug', Str::slug($state));
                            })
                            ->columnSpan(2),
                        TextInput::make('tagline')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Short line shown under the world title.')
                            ->columnSpan(2),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Shown on the world page hero under the tagline.')
                            ->columnSpan(2),
                        TagsInput::make('tags')
                            ->placeholder('Destiny')
                            ->helperText('Atmosphere words shown on the world card, such as Destiny, Courage, Control.')
                            ->columnSpanFull(),
                        MediaFileUpload::make('background')
                            ->label('Background image')
                            ->collection('background')
                            ->image()
                            ->imageEditor()
                            ->required()
                            ->columnSpanFull(),
                        Select::make('stories')
                            ->label('Stories')
                            ->multiple()
                            ->relationship('stories', 'title')
                            ->preload()
                            ->searchable()
                            ->helperText('Stories that share this world’s atmosphere.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
