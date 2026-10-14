<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Creators\Schemas;

use App\Models\Creator;
use App\Support\PasswordRequirements;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CreatorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->heading('Creator details')
                    ->schema([
                        Toggle::make('is_active')
                            ->required()
                            ->columnSpanFull(),
                        Fieldset::make('Basic information')
                            ->schema([
                                TextInput::make('first_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('last_name')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                        Fieldset::make('Account information')
                            ->schema([
                                TextInput::make('username')
                                    ->unique(table: Creator::class, ignoreRecord: true)
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->unique(table: Creator::class, ignoreRecord: true)
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->rules(PasswordRequirements::rules())
                                    ->maxLength(255)
                                    ->helperText(fn (string $operation): string => $operation === 'edit'
                                        ? 'Leave blank to keep the current password.'
                                        : 'At least 8 characters. Avoid common, breached, or sequential passwords.'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                        Textarea::make('bio')
                            ->rows(3)
                            ->columnSpan(2),
                    ]),
            ]);
    }
}
