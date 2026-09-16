<?php

declare(strict_types=1);

use App\Filament\Manager\Resources\Investors\InvestorResource;
use App\Filament\Manager\Resources\Investors\Pages\CreateInvestor;
use App\Filament\Manager\Resources\Investors\Pages\ListInvestors;
use App\Models\Investor;
use App\Models\Manager;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('registers investors as a manager entities resource', function (): void {
    expect(InvestorResource::getModel())->toBe(Investor::class)
        ->and(InvestorResource::getNavigationLabel())->toBe('Investors')
        ->and(InvestorResource::getNavigationGroup())->toBe('Entities')
        ->and(array_keys(InvestorResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('lets a manager open the investors list and create pages', function (): void {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager')
        ->get(InvestorResource::getUrl('index'))
        ->assertSuccessful();

    $this->actingAs($manager, 'manager')
        ->get(InvestorResource::getUrl('create'))
        ->assertSuccessful();
});

it('registers a player as an investor with complimentary play access', function (): void {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(CreateInvestor::class)
        ->fillForm([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'username' => 'ada-investor',
            'email' => 'ada.investor@example.test',
            'password' => 'password',
            'notes' => 'Seed-round demo account',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $user = User::query()->where('email', 'ada.investor@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user?->username)->toBe('ada-investor')
        ->and($user?->hasActiveInvestorAccess())->toBeTrue()
        ->and($user?->investor?->notes)->toBe('Seed-round demo account');
});

it('lets a manager block and restore complimentary access from the investors table', function (): void {
    $manager = Manager::factory()->create();
    $investor = Investor::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(ListInvestors::class)
        ->callAction(TestAction::make('blockAccess')->table($investor))
        ->assertNotified();

    expect($investor->fresh()?->access_enabled)->toBeFalse()
        ->and($investor->user->fresh()?->hasActiveInvestorAccess())->toBeFalse();

    Livewire::test(ListInvestors::class)
        ->callAction(TestAction::make('restoreAccess')->table($investor))
        ->assertNotified();

    expect($investor->fresh()?->access_enabled)->toBeTrue()
        ->and($investor->user->fresh()?->hasActiveInvestorAccess())->toBeTrue();
});
