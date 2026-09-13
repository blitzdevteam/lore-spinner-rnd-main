<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Purchases\Schemas;

use App\Models\SubscriptionPlan;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;

final class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchased plan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('stripe_price')
                            ->label('Plan')
                            ->badge()
                            ->formatStateUsing(static fn (?string $state): string => self::planLabel($state))
                            ->color(static fn (?string $state): string => self::planExists($state) ? 'primary' : 'gray'),
                        TextEntry::make('stripe_status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(static fn (?string $state): string => filled($state) ? Str::headline($state) : 'Unknown')
                            ->color(static fn (?string $state): string => self::statusColor($state)),
                        TextEntry::make('quantity')
                            ->label('Seats')
                            ->getStateUsing(static fn (Subscription $record): int => $record->quantity ?? 1)
                            ->numeric(),
                        TextEntry::make('type')
                            ->label('Subscription type')
                            ->formatStateUsing(static fn (?string $state): string => filled($state) ? Str::headline($state) : 'Default'),
                        TextEntry::make('created_at')
                            ->label('Purchased')
                            ->dateTime('M j, Y H:i'),
                        TextEntry::make('trial_ends_at')
                            ->label('Trial ends')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('No trial'),
                        TextEntry::make('ends_at')
                            ->label('Access ends')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('Renews automatically'),
                    ])
                    ->columnSpanFull(),
                Section::make('Player')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('user.full_name')
                            ->label('Name')
                            ->placeholder('Not provided'),
                        TextEntry::make('user.stripe_id')
                            ->label('Stripe customer')
                            ->copyable()
                            ->placeholder('Not synchronized')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Stripe references')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('stripe_id')
                            ->label('Stripe subscription')
                            ->copyable(),
                        TextEntry::make('stripe_price')
                            ->label('Stripe price')
                            ->copyable()
                            ->placeholder('Not synchronized'),
                        TextEntry::make('updated_at')
                            ->label('Latest local sync')
                            ->dateTime('M j, Y H:i:s'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function planLabel(?string $priceId): string
    {
        if (blank($priceId)) {
            return 'No plan synchronized';
        }

        return SubscriptionPlan::query()
            ->where('stripe_price_id', $priceId)
            ->value('name') ?? 'Custom Stripe plan';
    }

    private static function planExists(?string $priceId): bool
    {
        return filled($priceId)
            && SubscriptionPlan::query()
                ->where('stripe_price_id', $priceId)
                ->exists();
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'trialing' => 'info',
            'past_due', 'incomplete' => 'warning',
            'unpaid' => 'danger',
            default => 'gray',
        };
    }
}
