<?php

declare(strict_types=1);

use App\Enums\GenderEnum;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

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

    test('user can register and is sent to complete their profile', function () {
        startUserRegistration(getRegistrationPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.authentication.complete-profile.edit'))
            ->assertSessionHas('success', 'Your account has been created')
            ->assertSessionMissing('account_credentials');

        $this->assertDatabaseHas('users', [
            'email' => TEST_EMAIL,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_verified_at' => null,
        ]);
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

    test('user can do login action and must redirect to complete profile page when email is verified and profile is incomplete', function () {
        createUser(['email' => TEST_EMAIL, 'password' => bcrypt(TEST_PASSWORD)], false);

        $this
            ->post(route('user.authentication.login.store'), getLoginPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.authentication.complete-profile.edit'));
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
            ->assertRedirect(route('user.authentication.complete-profile.edit'));

        expect($user->fresh()->email_verified_at)->not()->toBeNull();
    });
});

describe('profile completion', function () {
    it('complete profile page can render', function () {
        $user = createUser([], false);

        $this
            ->actingAs($user)
            ->get(route('user.authentication.complete-profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('User/Authentication/CompleteProfile'));
    });

    test('user can complete profile and is redirected to dashboard', function () {
        $user = createUser(['email' => TEST_EMAIL, 'password' => bcrypt(TEST_PASSWORD)], false);

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'gender' => GenderEnum::MALE->value,
        ];

        $this
            ->actingAs($user)
            ->patch(route('user.authentication.complete-profile.update'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.dashboard.index'));

        $user->refresh();

        expect($user->only(['first_name', 'last_name', 'username', 'gender']))
            ->toMatchArray($payload);
    });
});
