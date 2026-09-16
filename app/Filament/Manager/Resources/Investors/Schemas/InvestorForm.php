<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Schemas;

use App\Models\Investor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class InvestorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->heading('Investor account')
                    ->description('Registers a player who can play stories without a paid plan until complimentary access is blocked.')
                    ->schema([
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('username')
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                fn (?Investor $record): Unique => Rule::unique('users', 'username')->ignore($record?->user_id),
                            ]),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                fn (?Investor $record): Unique => Rule::unique('users', 'email')->ignore($record?->user_id),
                            ]),
                        TextInput::make('password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255)
                            ->helperText('Leave blank on edit to keep the current password.'),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Internal notes only. The investor never sees this.'),
                    ]),
            ]);
    }
}
