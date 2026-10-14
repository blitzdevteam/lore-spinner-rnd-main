<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Filament\Manager\Resources\Creators\Pages\ListCreators;
use App\Filament\Manager\Resources\Managers\Pages\ListManagers;
use App\Filament\Manager\Resources\Users\Pages\CreateUser;
use App\Filament\Manager\Resources\Users\Pages\EditUser;
use App\Filament\Manager\Resources\Users\Pages\ListUsers;
use App\Filament\Manager\Resources\Writers\Pages\ListWriters;
use App\Models\Creator;
use App\Models\Manager;
use App\Models\User;
use App\Models\Writer;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets a manager unlock a locked player', function (): void {
    $manager = Manager::factory()->create();
    $user = User::factory()->create(['password' => 'password']);
    $user->forceFill([
        'failed_login_attempts' => 5,
        'locked_until' => now()->addHour(),
    ])->save();

    $this->actingAs($manager, 'manager');

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('unlockAccount')->table($user))
        ->callAction(TestAction::make('unlockAccount')->table($user))
        ->assertNotified();

    $user->refresh();

    expect($user->isLoginLocked())->toBeFalse()
        ->and($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_until)->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeTrue();

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('unlockAccount')->table($user));
});

it('lets a manager unlock a locked writer, creator, and admin', function (string $kind): void {
    $manager = Manager::factory()->create();

    [$page, $account] = match ($kind) {
        'writer' => [
            ListWriters::class,
            Writer::query()->create([
                'name' => 'Ada Writer',
                'email' => 'ada.writer@example.test',
                'password' => 'password',
            ]),
        ],
        'creator' => [
            ListCreators::class,
            Creator::factory()->create(['password' => 'password']),
        ],
        'admin' => [
            ListManagers::class,
            Manager::factory()->create(['password' => 'password']),
        ],
        default => throw new InvalidArgumentException('Unknown account kind.'),
    };

    $account->forceFill([
        'failed_login_attempts' => 5,
        'locked_until' => now()->addHour(),
    ])->save();

    $this->actingAs($manager, 'manager');

    Livewire::test($page)
        ->callAction(TestAction::make('unlockAccount')->table($account))
        ->assertNotified();

    expect($account->fresh()?->isLoginLocked())->toBeFalse()
        ->and(Hash::check('password', (string) $account->fresh()?->password))->toBeTrue();
})->with(['writer', 'creator', 'admin']);

it('rejects a weak password when a manager creates a player', function (): void {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'type' => UserType::User,
            'is_active' => true,
            'first_name' => 'Ada',
            'last_name' => 'Player',
            'username' => 'ada-player',
            'email' => 'ada.player@example.test',
            'password' => 'password',
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);

    expect(User::query()->where('email', 'ada.player@example.test')->exists())->toBeFalse();
});

it('keeps the current password when the manager leaves it blank or enters a weak one', function (): void {
    $manager = Manager::factory()->create();
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Player',
        'username' => 'ada-player',
        'email' => 'ada.player@example.test',
        'password' => 'password',
        'is_active' => true,
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm([
            'first_name' => 'Ada',
            'last_name' => 'Player',
            'username' => 'ada-player',
            'email' => 'ada.player@example.test',
            'is_active' => true,
            'password' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('password', (string) $user->fresh()?->password))->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm([
            'password' => '12345678',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect(Hash::check('password', (string) $user->fresh()?->password))->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm([
            'password' => 'n7K!mQ2vLx9p',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('n7K!mQ2vLx9p', (string) $user->fresh()?->password))->toBeTrue();
});
