<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Users\Schemas;

use App\Enums\UserType;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class UserForm
{
    public static function formIsInvestor(Get $get, ?User $record = null): bool
    {
        return UserType::isInvestor($get('type') ?? $record?->type);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->heading('User details')
                    ->schema([
                        Select::make('type')
                            ->options(UserType::class)
                            ->default(UserType::User)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->live()
                            ->disabledOn('edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Investors get a magic link to set their own email and password.')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->required()
                            ->visible(fn (Get $get, ?User $record): bool => ! self::formIsInvestor($get, $record))
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
                            ->visible(fn (Get $get, ?User $record): bool => ! self::formIsInvestor($get, $record))
                            ->schema([
                                TextInput::make('username')
                                    ->unique(table: User::class, ignoreRecord: true)
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->unique(table: User::class, ignoreRecord: true)
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('password')
                                    ->password()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                        Fieldset::make('Investor access')
                            ->visible(fn (Get $get, ?User $record): bool => self::formIsInvestor($get, $record))
                            ->schema([
                                Select::make('subscription_plan_id')
                                    ->label('Subscription plan')
                                    ->options(fn (): array => SubscriptionPlan::query()
                                        ->orderBy('sort_order')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->helperText('Usage is metered against this plan. This is not an unlimited account.'),
                                Select::make('duration_months')
                                    ->label('Access duration')
                                    ->options(User::durationOptions())
                                    ->required()
                                    ->default(1)
                                    ->live()
                                    ->helperText(fn (Get $get): string => (int) $get('duration_months') === User::UNLIMITED_DURATION_MONTHS
                                        ? 'Access does not expire.'
                                        : 'The test account is blocked when this window ends.'),
                                TextInput::make('email')
                                    ->label('Email address')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (?User $record): bool => $record?->hasCompletedSetup() === true)
                                    ->columnSpanFull(),
                                TextInput::make('magic_login_url')
                                    ->label('Magic login link')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->copyable()
                                    ->visible(fn (?User $record): bool => filled($record?->magicLoginUrl()))
                                    ->helperText('Share this link so the investor can set their email and password, then access the site.')
                                    ->columnSpanFull(),
                                Textarea::make('notes')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->helperText('Internal notes only. The investor never sees this.'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                        Textarea::make('bio')
                            ->rows(3)
                            ->visible(fn (Get $get, ?User $record): bool => ! self::formIsInvestor($get, $record))
                            ->columnSpan(2),
                    ]),
            ]);
    }
}
