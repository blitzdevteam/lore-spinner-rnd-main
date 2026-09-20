<?php

declare(strict_types=1);

use App\Actions\Authentication\CreateAuthenticatableGuardAction;
use App\Enums\UserType;
use App\Filament\Manager\Resources\Investors\InvestorResource;
use App\Filament\Manager\Resources\Users\Pages\CreateUser;
use App\Filament\Manager\Resources\Users\Pages\EditUser;
use App\Filament\Manager\Resources\Users\Pages\ListUsers;
use App\Filament\Manager\Resources\Users\UserResource;
use App\Models\Manager;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('hides the separate investors resource from manager navigation', function (): void {
    expect(InvestorResource::shouldRegisterNavigation())->toBeFalse()
        ->and(UserResource::getModel())->toBe(User::class);
});

it('lets a manager open the users list and create pages', function (): void {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager')
        ->get(UserResource::getUrl('index'))
        ->assertSuccessful();

    $this->actingAs($manager, 'manager')
        ->get(UserResource::getUrl('create'))
        ->assertSuccessful();
});

it('registers a simple user from the users form', function (): void {
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
        ->assertHasNoFormErrors()
        ->assertNotified();

    $user = User::query()->where('email', 'ada.player@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user?->type)->toBe(UserType::User)
        ->and($user?->isInvestor())->toBeFalse()
        ->and($user?->username)->toBe('ada-player');
});

it('registers an investor from the users form with a plan, expiry, and magic login link', function (): void {
    $manager = Manager::factory()->create();
    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Adventurer Pass',
        'action_limit' => 120,
        'voice_minutes_limit' => 40,
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'type' => UserType::Investor,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'subscription_plan_id' => $plan->id,
            'duration_months' => 3,
            'notes' => 'Seed-round demo account',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $user = User::query()
        ->where('first_name', 'Ada')
        ->where('last_name', 'Lovelace')
        ->first();
    $summary = $user === null ? null : app(SubscriptionEntitlementService::class)->summary($user);

    expect($user)->not->toBeNull()
        ->and($user?->type)->toBe(UserType::Investor)
        ->and($user?->hasActiveInvestorAccess())->toBeTrue()
        ->and(str_ends_with((string) $user?->email, '@'.User::PENDING_EMAIL_DOMAIN))->toBeTrue()
        ->and($user?->hasCompletedSetup())->toBeFalse()
        ->and($user?->notes)->toBe('Seed-round demo account')
        ->and($user?->subscription_plan_id)->toBe($plan->id)
        ->and($user?->duration_months)->toBe(3)
        ->and($user?->expires_at?->isSameDay(now()->addMonthsNoOverflow(3)))->toBeTrue()
        ->and($user?->magicLoginUrl())->not->toBeNull()
        ->and($user?->subscriptions()->where('type', UserType::Investor->value)->exists())->toBeTrue()
        ->and($summary['plan']['name'])->toBe('Adventurer Pass')
        ->and($summary['actions']['limit'])->toBe(120)
        ->and($summary['is_complimentary'])->toBeFalse();
});

it('lets a manager assign a different plan and duration from the edit page', function (): void {
    $manager = Manager::factory()->create();
    $investor = User::factory()->investor()->create(['duration_months' => 1]);
    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Hero Pass',
        'action_limit' => 200,
        'voice_minutes_limit' => 80,
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(EditUser::class, ['record' => $investor->getKey()])
        ->fillForm([
            'first_name' => $investor->first_name,
            'last_name' => $investor->last_name,
            'subscription_plan_id' => $plan->id,
            'duration_months' => 6,
            'notes' => $investor->notes,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $investor->refresh();
    $summary = app(SubscriptionEntitlementService::class)->summary($investor);

    expect($investor->subscription_plan_id)->toBe($plan->id)
        ->and($investor->duration_months)->toBe(6)
        ->and($summary['plan']['name'])->toBe('Hero Pass')
        ->and($summary['actions']['limit'])->toBe(200);
});

it('lets a manager block and restore test access from the users table', function (): void {
    $manager = Manager::factory()->create();
    $investor = User::factory()->investor()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('blockAccess')->table($investor))
        ->assertNotified();

    expect($investor->fresh()?->access_enabled)->toBeFalse()
        ->and($investor->fresh()?->hasActiveInvestorAccess())->toBeFalse()
        ->and($investor->fresh()?->magicLoginUrl())->toBeNull();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('restoreAccess')->table($investor))
        ->assertNotified();

    $investor->refresh();

    expect($investor->access_enabled)->toBeTrue()
        ->and($investor->hasActiveInvestorAccess())->toBeTrue()
        ->and($investor->magicLoginUrl())->not->toBeNull();
});

it('deletes the investor account when a manager removes an investor', function (): void {
    $manager = Manager::factory()->create();
    $investor = User::factory()->investor()->create();
    $userId = $investor->id;

    $this->actingAs($manager, 'manager');

    Livewire::test(EditUser::class, ['record' => $investor->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    expect(User::query()->find($userId))->toBeNull();
});

it('lists investors alongside players in the users table', function (): void {
    $manager = Manager::factory()->create();
    $player = User::factory()->create(['is_active' => true]);
    $investor = User::factory()->investor()->create([
        'first_name' => 'Nima',
        'last_name' => 'ASF',
        'username' => 'nima-asf',
        'email' => 'nima@g.com',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$player, $investor])
        ->assertSee('nima@g.com')
        ->assertSee('nima-asf')
        ->assertSee('Investor')
        ->assertSee('User');
});

it('continues public registration as a player user type', function (): void {
    $user = app(CreateAuthenticatableGuardAction::class)->handle('user', 'player@example.test', 'password');

    expect($user->type)->toBe(UserType::User)
        ->and($user->isInvestor())->toBeFalse()
        ->and($user->hasActiveInvestorAccess())->toBeFalse();
});
