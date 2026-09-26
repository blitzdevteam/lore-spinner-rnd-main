<?php

declare(strict_types=1);

use App\Filament\Manager\Resources\Managers\ManagerResource;
use App\Filament\Manager\Resources\Managers\Pages\CreateManager;
use App\Filament\Manager\Resources\Managers\Pages\EditManager;
use App\Filament\Manager\Resources\Managers\Pages\ListManagers;
use App\Models\Manager;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows admins in the manager panel navigation', function (): void {
    expect(ManagerResource::getNavigationLabel())->toBe('Admins')
        ->and(ManagerResource::getSlug())->toBe('admins');
});

it('lets a manager create another admin', function (): void {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(CreateManager::class)
        ->fillForm([
            'first_name' => 'Ada',
            'last_name' => 'Admin',
            'email' => 'ada.admin@example.test',
            'password' => 'a-long-unique-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $created = Manager::query()->where('email', 'ada.admin@example.test')->first();

    expect($created)->not->toBeNull()
        ->and($created?->first_name)->toBe('Ada')
        ->and($created?->last_name)->toBe('Admin')
        ->and(Hash::check('a-long-unique-password', (string) $created?->password))->toBeTrue();
});

it('updates an admin and keeps the password when a new one is not entered', function (): void {
    $manager = Manager::factory()->create();
    $admin = Manager::factory()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.test',
        'password' => 'original-password',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(EditManager::class, ['record' => $admin->getKey()])
        ->fillForm([
            'first_name' => 'Grace',
            'last_name' => 'Murray',
            'email' => 'grace@example.test',
            'password' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $admin->refresh();

    expect($admin->last_name)->toBe('Murray')
        ->and(Hash::check('original-password', $admin->password))->toBeTrue();
});

it('replaces an admin password when a new one is entered', function (): void {
    $manager = Manager::factory()->create();
    $admin = Manager::factory()->create([
        'password' => 'original-password',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(EditManager::class, ['record' => $admin->getKey()])
        ->fillForm([
            'password' => 'replacement-password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('replacement-password', $admin->refresh()->password))->toBeTrue();
});

it('deletes another admin and keeps the signed-in admin', function (): void {
    $manager = Manager::factory()->create();
    $admin = Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(EditManager::class, ['record' => $admin->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    expect(Manager::query()->whereKey($admin->getKey())->exists())->toBeFalse()
        ->and(Manager::query()->whereKey($manager->getKey())->exists())->toBeTrue();
});

it('does not let an admin delete their own account', function (): void {
    $manager = Manager::factory()->create();
    Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(EditManager::class, ['record' => $manager->getKey()])
        ->assertActionHidden(DeleteAction::class);

    expect(Manager::query()->whereKey($manager->getKey())->exists())->toBeTrue();
});

it('lists admins for a signed-in manager', function (): void {
    $manager = Manager::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Admin',
        'email' => 'ada.admin@example.test',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(ListManagers::class)
        ->assertCanSeeTableRecords([$manager])
        ->assertSee('ada.admin@example.test');
});
