<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Managers\Schemas;

use App\Support\PasswordRequirements;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class ManagerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('first_name')
                ->required()
                ->maxLength(255),
            TextInput::make('last_name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email address')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
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
        ]);
    }
}
