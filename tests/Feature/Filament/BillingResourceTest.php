<?php

declare(strict_types=1);

use App\Enums\PricingPlan;
use App\Filament\Manager\Resources\Billings\BillingResource;
use App\Filament\Manager\Resources\Billings\Pages\ListBillings;
use App\Filament\Manager\Resources\Billings\Tables\BillingsTable;
use App\Models\Manager;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'cashier.secret' => null,
        'services.stripe.prices.explorer' => 'price_explorer_monthly',
        'services.stripe.prices.adventurer' => 'price_adventurer_monthly',
        'services.stripe.prices.chronicle' => 'price_chronicle_monthly',
    ]);
});

it('registers webhook-synced Cashier subscriptions as a read-only billing resource', function () {
    expect(BillingResource::getModel())->toBe(Subscription::class)
        ->and(BillingResource::getSlug())->toBe('billing')
        ->and(BillingResource::getNavigationLabel())->toBe('Billing')
        ->and(array_keys(BillingResource::getPages()))->toBe(['index', 'view'])
        ->and(PricingPlan::fromStripePriceId('price_explorer_monthly'))->toBe(PricingPlan::Explorer)
        ->and(PricingPlan::stripePriceOptions())->toBe([
            'price_explorer_monthly' => 'Explorer',
            'price_adventurer_monthly' => 'Adventurer',
            'price_chronicle_monthly' => 'Chronicle',
        ]);
});

it('provides plan, status, customer, and lifecycle controls without mutation actions', function () {
    $table = BillingsTable::configure(
        Table::make(app(ListBillings::class)),
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
        'pm_type' => 'card',
        'pm_last_four' => '4242',
    ]);

    Subscription::factory()
        ->for($user)
        ->withPrice('price_explorer_monthly')
        ->create();

    $this->actingAs($manager, 'manager')
        ->get(BillingResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('ada@example.test')
        ->assertSee('Explorer')
        ->assertSee('Active');
});

it('shows local subscription details when Stripe invoices are not yet available', function () {
    $manager = Manager::factory()->create();
    $user = User::factory()->create([
        'email' => 'new-player@example.test',
    ]);
    $subscription = Subscription::factory()
        ->for($user)
        ->withPrice('price_chronicle_monthly')
        ->create();

    $this->actingAs($manager, 'manager')
        ->get(BillingResource::getUrl('view', ['record' => $subscription]))
        ->assertSuccessful()
        ->assertSee('Billing and plan')
        ->assertSee('Chronicle')
        ->assertSee('Invoice history')
        ->assertSee('No invoice records are currently available for this subscription.');
});

it('keeps local subscription details available when Stripe invoice access is not configured', function () {
    $manager = Manager::factory()->create();
    $user = User::factory()->create([
        'email' => 'stripe-customer@example.test',
        'stripe_id' => 'cus_without_invoice_access',
    ]);
    $subscription = Subscription::factory()
        ->for($user)
        ->withPrice('price_adventurer_monthly')
        ->create();

    $this->actingAs($manager, 'manager')
        ->get(BillingResource::getUrl('view', ['record' => $subscription]))
        ->assertSuccessful()
        ->assertSee('Adventurer')
        ->assertSee('Stripe invoice access is not configured.');
});
