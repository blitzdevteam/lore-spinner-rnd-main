<?php

declare(strict_types=1);

use App\Enums\PricingPlan;
use App\Filament\Manager\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Manager\Resources\Purchases\PurchaseResource;
use App\Filament\Manager\Resources\Purchases\Tables\PurchasesTable;
use App\Models\Manager;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.stripe.prices.explorer' => 'price_explorer_monthly',
        'services.stripe.prices.adventurer' => 'price_adventurer_monthly',
        'services.stripe.prices.chronicle' => 'price_chronicle_monthly',
    ]);
});

it('registers webhook-synced Cashier subscriptions as a read-only purchase tracker', function () {
    expect(PurchaseResource::getModel())->toBe(Subscription::class)
        ->and(PurchaseResource::getSlug())->toBe('purchases')
        ->and(PurchaseResource::getNavigationLabel())->toBe('Purchases')
        ->and(array_keys(PurchaseResource::getPages()))->toBe(['index', 'view'])
        ->and(PricingPlan::fromStripePriceId('price_explorer_monthly'))->toBe(PricingPlan::Explorer)
        ->and(PricingPlan::stripePriceOptions())->toBe([
            'price_explorer_monthly' => 'Adventurer Pass',
            'price_adventurer_monthly' => 'Hero Pass',
            'price_chronicle_monthly' => 'Mythic Pass',
        ]);
});

it('provides plan, status, customer, and lifecycle controls without mutation actions', function () {
    $table = PurchasesTable::configure(
        Table::make(app(ListPurchases::class)),
    );

    expect(array_keys($table->getColumns()))
        ->toContain('user.email', 'stripe_price', 'stripe_status', 'quantity', 'created_at', 'ends_at')
        ->and(array_keys($table->getFilters()))
        ->toContain('stripe_price', 'stripe_status', 'user_id')
        ->and($table->getRecordActions())->toHaveCount(1)
        ->and($table->getRecordActions()[0])->toBeInstanceOf(ViewAction::class)
        ->and($table->getToolbarActions())->toBe([]);
});

it('lets a manager track a locally synchronized purchased plan', function () {
    $manager = Manager::factory()->create();
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'stripe_id' => 'cus_ada',
    ]);

    Subscription::factory()
        ->for($user)
        ->withPrice('price_explorer_monthly')
        ->create();

    $this->actingAs($manager, 'manager')
        ->get(PurchaseResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('ada@example.test')
        ->assertSee('Adventurer Pass')
        ->assertSee('Active');
});

it('shows the purchased-plan details without invoice history', function () {
    $manager = Manager::factory()->create();
    $user = User::factory()->create([
        'email' => 'new-player@example.test',
    ]);
    $subscription = Subscription::factory()
        ->for($user)
        ->withPrice('price_chronicle_monthly')
        ->create();

    $this->actingAs($manager, 'manager')
        ->get(PurchaseResource::getUrl('view', ['record' => $subscription]))
        ->assertSuccessful()
        ->assertSee('Purchased plan')
        ->assertSee('Mythic Pass')
        ->assertSee('Stripe references')
        ->assertDontSee('Invoice history');
});
