<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\SubscriptionPlans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->heading('Subscription plan')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Available for new subscriptions')
                        ->default(true)
                        ->required(),
                    Toggle::make('is_featured')
                        ->label('Feature this plan on pricing')
                        ->default(false)
                        ->required(),
                    Fieldset::make('Identity and Stripe price')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->helperText('Used in the checkout URL.'),
                            TextInput::make('stripe_price_id')
                                ->label('Stripe price ID')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->helperText('Create the product and recurring price in Stripe first, then paste its price_ ID.'),
                            TextInput::make('sort_order')
                                ->numeric()
                                ->required()
                                ->default(0)
                                ->minValue(0)
                                ->helperText('Lower numbers appear first.'),
                        ])
                        ->columnSpanFull(),
                    Fieldset::make('Displayed monthly price')
                        ->columns(2)
                        ->schema([
                            TextInput::make('price_cents')
                                ->label('Price in cents')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->helperText('Must match the Stripe price.'),
                            TextInput::make('currency')
                                ->required()
                                ->default('USD')
                                ->minLength(3)
                                ->maxLength(3),
                        ])
                        ->columnSpanFull(),
                    Fieldset::make('Allowance per billing period')
                        ->columns(2)
                        ->schema([
                            TextInput::make('action_limit')
                                ->label('Actions')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                            TextInput::make('voice_minutes_limit')
                                ->label('Voice minutes')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->columnSpanFull(),
                    TextInput::make('tagline')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    TagsInput::make('features')
                        ->placeholder('225 Actions')
                        ->default([])
                        ->required()
                        ->helperText('One item per line in the pricing card.')
                        ->columnSpanFull(),
                    Fieldset::make('Pricing card appearance')
                        ->columns(3)
                        ->schema([
                            Select::make('accent')
                                ->options([
                                    'teal' => 'Teal',
                                    'gold' => 'Gold',
                                ])
                                ->required()
                                ->default('teal'),
                            Select::make('icon_key')
                                ->label('Icon')
                                ->options([
                                    'footprints' => 'Footprints',
                                    'mountains' => 'Mountains',
                                    'crown' => 'Crown',
                                ])
                                ->required()
                                ->default('footprints'),
                        ])
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
