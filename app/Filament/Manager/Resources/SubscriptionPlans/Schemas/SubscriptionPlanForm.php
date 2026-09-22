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
use Filament\Schemas\Components\Utilities\Get;
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
                        ->live()
                        ->required(),
                    TextInput::make('featured_label')
                        ->label('Featured badge')
                        ->maxLength(255)
                        ->default('Most Recommended')
                        ->dehydrated()
                        ->visible(fn (Get $get): bool => (bool) $get('is_featured'))
                        ->helperText('Tab above the card. Example: Most Recommended.')
                        ->columnSpanFull(),
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
                                ->minValue(0)
                                ->helperText('Shown as the actions figure on the pricing card.'),
                            TextInput::make('voice_minutes_limit')
                                ->label('Voice minutes')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->helperText('Whole hours display as hours. 120 minutes becomes 2 hours.'),
                        ])
                        ->columnSpanFull(),
                    TextInput::make('tagline')
                        ->maxLength(255)
                        ->helperText('Shown under the plan name. Example: Base rate.')
                        ->columnSpanFull(),
                    TextInput::make('bonus_label')
                        ->label('Bonus badge')
                        ->maxLength(255)
                        ->helperText('Optional badge beside the price. Example: 25% Bonus. Leave empty to hide it.')
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->rows(3)
                        ->maxLength(1000)
                        ->helperText('Short paragraph under the price.')
                        ->columnSpanFull(),
                    Textarea::make('action_hint')
                        ->label('Actions info')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('Tooltip for the info icon beside Actions / month.')
                        ->columnSpanFull(),
                    TagsInput::make('features')
                        ->placeholder('Access to all interactive stories')
                        ->default([])
                        ->required()
                        ->helperText('Checkmark lines under the allowance. Actions and voice are already shown from the limits above.')
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
                                ->default('teal')
                                ->helperText('Kept on the plan record. Featured cards use the teal treatment.'),
                            Select::make('icon_key')
                                ->label('Icon')
                                ->options([
                                    'book' => 'Open book',
                                    'books-plus' => 'Books with plus',
                                    'library' => 'Bookshelf',
                                    'crown' => 'Crown',
                                    'star' => 'Star',
                                    'people' => 'People',
                                    'footprints' => 'Footprints',
                                    'mountains' => 'Mountains',
                                ])
                                ->required()
                                ->default('book'),
                        ])
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
