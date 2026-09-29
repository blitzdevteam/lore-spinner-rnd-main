<?php

declare(strict_types=1);

use App\Enums\GenderEnum;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const TEST_EMAIL = 'john@example.com';
const TEST_PASSWORD = 'password';

function createUser(array $payload = [], bool $withCompleteProfile = true, bool $markEmailVerified = true): User
{
    $user = User::factory();

    if ($withCompleteProfile) {
        $user = $user->withCompleteProfile();
    }

    if ($markEmailVerified) {
        $user = $user->markEmailAsVerified();
    }

    return $user->create($payload);
}

function startUserRegistration(array $payload = []): TestResponse
{
    return test()->post(route('user.authentication.register.store'), $payload);
}

function getRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => GenderEnum::MALE->value,
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
        'password_confirmation' => TEST_PASSWORD,
    ], $overrides);
}

function getLoginPayload(array $overrides = []): array
{
    return array_merge([
        'email' => TEST_EMAIL,
        'password' => TEST_PASSWORD,
    ], $overrides);
}

describe('registration', function () {
    it('registration page can render', function () {
        $this
            ->get(route('user.authentication.register.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('User/Authentication/Register'));
    });

    test('user can not do register action with duplicate email', function () {
        createUser(['email' => TEST_EMAIL], false);

        startUserRegistration(getRegistrationPayload())
            ->assertSessionHasErrors('email')
            ->assertRedirectBack();
    });

    test('user can register with a gender and is sent to the dashboard', function () {
        startUserRegistration(getRegistrationPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.dashboard.index'))
            ->assertSessionHas('success', 'Your account has been created')
            ->assertSessionMissing('account_credentials');

        $user = User::query()->where('email', TEST_EMAIL)->first();

        expect($user)->not->toBeNull()
            ->and($user->first_name)->toBe('John')
            ->and($user->last_name)->toBe('Doe')
            ->and($user->gender)->toBe(GenderEnum::MALE->value)
            ->and($user->username)->toBe('johndoe')
            ->and($user->is_profile_completed)->toBeTrue()
            ->and($user->email_verified_at)->toBeNull();
    });

    test('user can not register without a valid gender', function () {
        startUserRegistration(getRegistrationPayload(['gender' => 'unknown']))
            ->assertSessionHasErrors('gender')
            ->assertRedirectBack();
    });

    test('registration assigns the next available username when the name is taken', function () {
        User::factory()->create(['username' => 'johndoe']);

        startUserRegistration(getRegistrationPayload(['email' => 'jane@example.com']))
            ->assertSessionHasNoErrors();

        expect(User::query()->where('email', 'jane@example.com')->first()?->username)->toBe('johndoe1');
    });
});

describe('login', function () {
    it('login page can render', function () {
        $this
            ->get(route('user.authentication.login.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('User/Authentication/Login'));
    });

    test('user can not do login action with wrong email or password', function () {
        createUser(['email' => TEST_EMAIL, 'password' => bcrypt(TEST_PASSWORD)]);

        $this
            ->post(route('user.authentication.login.store'), getLoginPayload(['password' => 'wrong-password']))
            ->assertSessionHas('error', 'Credentials do not match our records')
            ->assertRedirectBack();
    });

    test('user can do login action and must redirect to email verification page if email is not verified', function () {
        createUser(
            ['email' => TEST_EMAIL, 'password' => bcrypt(TEST_PASSWORD)],
            false,
            false
        );

        $this
            ->post(route('user.authentication.login.store'), getLoginPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.authentication.verify.index'));
    });

    test('user can do login action and must redirect to dashboard when profile has no username', function () {
        createUser([
            'email' => TEST_EMAIL,
            'password' => bcrypt(TEST_PASSWORD),
            'username' => null,
        ]);

        $this
            ->post(route('user.authentication.login.store'), getLoginPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.dashboard.index'));
    });

    test('user can do login action and must redirect to dashboard when email and profile are completed and verified', function () {
        createUser(['email' => TEST_EMAIL, 'password' => bcrypt(TEST_PASSWORD)]);

        $this
            ->post(route('user.authentication.login.store'), getLoginPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.dashboard.index'));
    });
});

describe('verification', function () {
    it('verification page can render', function () {
        startUserRegistration(getRegistrationPayload());

        $this
            ->get(route('user.authentication.verify.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('User/Authentication/Verify'));
    });

    test('user can do verify action and redirect to user complete profile page', function () {
        Notification::fake();

        startUserRegistration(getRegistrationPayload());
        $user = User::where('email', TEST_EMAIL)->first();

        $verifyUrl = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, &$verifyUrl) {
            $verifyUrl = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->get($verifyUrl)
            ->assertRedirect(route('user.dashboard.index'));

        expect($user->fresh()->email_verified_at)->not()->toBeNull();
    });
});

describe('profile completion', function () {
    test('a signed-in user without a username receives one when entering the app', function () {
        $user = createUser([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'username' => null,
        ]);

        $this
            ->actingAs($user)
            ->get(route('user.dashboard.index'))
            ->assertRedirect(route('index'));

        expect($user->fresh()->username)->toBe('adalovelace')
            ->and($user->fresh()->is_profile_completed)->toBeTrue();
    });
});
