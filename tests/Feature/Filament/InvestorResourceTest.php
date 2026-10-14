<?php

declare(strict_types=1);

use App\Actions\Authentication\CreateAuthenticatableGuardAction;
use App\Enums\UserType;
use App\Filament\Manager\Pages\AnalyticsDashboard;
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
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
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
            'password' => 'n7K!mQ2vLx9p',
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

it('registers an investor with unlimited access that does not expire', function (): void {
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
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'subscription_plan_id' => $plan->id,
            'duration_months' => User::UNLIMITED_DURATION_MONTHS,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $user = User::query()
        ->where('first_name', 'Grace')
        ->where('last_name', 'Hopper')
        ->first();

    expect($user)->not->toBeNull()
        ->and($user?->duration_months)->toBe(User::UNLIMITED_DURATION_MONTHS)
        ->and($user?->expires_at)->toBeNull()
        ->and($user?->hasActiveInvestorAccess())->toBeTrue()
        ->and($user?->isExpired())->toBeFalse()
        ->and($user?->magicLoginUrl())->not->toBeNull();

    $this->get($user?->magicLoginUrl())->assertSuccessful();

    $subscription = $user?->subscriptions()->where('type', UserType::Investor->value)->first();
    $summary = $user === null ? null : app(SubscriptionEntitlementService::class)->summary($user);

    expect($subscription?->ends_at)->toBeNull()
        ->and($subscription?->valid())->toBeTrue()
        ->and($summary['plan']['name'])->toBe('Adventurer Pass')
        ->and($summary['actions']['limit'])->toBe(120);

    $this->artisan('investors:expire-access')->assertSuccessful();

    expect($user?->fresh()?->access_enabled)->toBeTrue()
        ->and($user?->fresh()?->hasActiveInvestorAccess())->toBeTrue();
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

it('shows a player purchased plan on the users table', function (): void {
    config(['cashier.secret' => null]);

    $manager = Manager::factory()->create();
    SubscriptionPlan::factory()->create([
        'name' => 'Story',
        'slug' => 'story',
        'stripe_price_id' => 'price_story',
    ]);
    $player = User::factory()->create([
        'first_name' => 'Sean',
        'last_name' => 'LeMons',
        'email' => 'sean@lemons.com',
        'subscription_plan_id' => null,
    ]);
    $withoutPlan = User::factory()->create([
        'email' => 'sean@lemons.org',
        'subscription_plan_id' => null,
    ]);

    Subscription::factory()
        ->for($player)
        ->withPrice('price_story')
        ->create();

    Subscription::factory()
        ->for($withoutPlan)
        ->withPrice('price_story')
        ->canceled()
        ->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$player, $withoutPlan])
        ->assertTableColumnStateSet('plan.name', 'Story', $player)
        ->assertTableColumnStateSet('plan.name', null, $withoutPlan);
});

it('links each user to the analytics dashboard for that player', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('manager'));

    $manager = Manager::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('viewAnalytics')->table($user))
        ->assertActionHasUrl(
            TestAction::make('viewAnalytics')->table($user),
            AnalyticsDashboard::getUrl(['user' => $user->getKey()]),
        );
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
