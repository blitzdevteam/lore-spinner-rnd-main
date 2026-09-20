<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Schemas;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                    ->description('Enter the investor\'s name, assign a plan, and copy the magic link. They will set their own email and password from that link.')
                    ->schema([
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
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
                            ->helperText('The test account is blocked when this window ends.'),
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
                    ]),
            ]);
    }
}
