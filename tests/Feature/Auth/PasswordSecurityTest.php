<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Writer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('still signs in with a password saved before these rules', function (): void {
    $user = User::factory()->create([
        'email' => 'player@example.test',
        'password' => 'password',
    ]);

    $this->post(route('user.authentication.login.store'), [
        'email' => 'player@example.test',
        'password' => 'password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user.dashboard.index'));

    expect(Hash::check('password', (string) $user->fresh()?->password))->toBeTrue()
        ->and($user->fresh()?->failed_login_attempts)->toBe(0)
        ->and($user->fresh()?->locked_until)->toBeNull();
});

it('rejects a weak password when registering and does not create the account', function (): void {
    $this->post(route('user.authentication.register.store'), [
        'first_name' => 'Ada',
        'last_name' => 'Player',
        'email' => 'ada@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'ada@example.test')->exists())->toBeFalse();
});

it('rejects a weak replacement and leaves the stored password untouched', function (): void {
    $user = User::factory()->create([
        'email' => 'player@example.test',
        'password' => 'password',
    ]);

    $this->from(route('user.authentication.reset-password.create', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
    ]))->post(route('user.authentication.reset-password.store'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => '12345678',
        'password_confirmation' => '12345678',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', (string) $user->fresh()?->password))->toBeTrue();
});

it('locks the account after five failed sign-ins until an hour has passed', function (): void {
    $user = User::factory()->create([
        'email' => 'player@example.test',
        'password' => 'password',
    ]);

    foreach (range(1, 4) as $attempt) {
        $this->post(route('user.authentication.login.store'), [
            'email' => 'player@example.test',
            'password' => 'wrong-password-'.$attempt,
        ])->assertSessionHas('error', 'Credentials do not match our records');
    }

    expect($user->fresh()?->isLoginLocked())->toBeFalse()
        ->and($user->fresh()?->failed_login_attempts)->toBe(4);

    $this->post(route('user.authentication.login.store'), [
        'email' => 'player@example.test',
        'password' => 'wrong-password-5',
    ])->assertSessionHasErrors([
        'email' => 'This account is temporarily locked for 1 hour. An admin can unlock it sooner.',
    ]);

    $locked = $user->fresh();

    expect($locked?->isLoginLocked())->toBeTrue()
        ->and(Hash::check('password', (string) $locked?->password))->toBeTrue();

    $this->post(route('user.authentication.login.store'), [
        'email' => 'player@example.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('user');

    $this->travel(User::LOCKOUT_MINUTES + 1)->minutes();

    $this->post(route('user.authentication.login.store'), [
        'email' => 'player@example.test',
        'password' => 'password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user.dashboard.index'));

    expect($user->fresh()?->isLoginLocked())->toBeFalse()
        ->and($user->fresh()?->failed_login_attempts)->toBe(0);
});

it('clears failed attempts after a successful sign-in', function (): void {
    $user = User::factory()->create([
        'email' => 'player@example.test',
        'password' => 'password',
    ]);

    foreach (range(1, 3) as $attempt) {
        $this->post(route('user.authentication.login.store'), [
            'email' => 'player@example.test',
            'password' => 'wrong-password-'.$attempt,
        ]);
    }

    $this->post(route('user.authentication.login.store'), [
        'email' => 'player@example.test',
        'password' => 'password',
    ])->assertRedirect(route('user.dashboard.index'));

    expect($user->fresh()?->failed_login_attempts)->toBe(0);

    $this->delete(route('user.authentication.logout'));

    foreach (range(1, 4) as $attempt) {
        $this->post(route('user.authentication.login.store'), [
            'email' => 'player@example.test',
            'password' => 'wrong-password-'.$attempt,
        ]);
    }

    expect($user->fresh()?->isLoginLocked())->toBeFalse();
});

it('does not lock an email that has no account', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('user.authentication.login.store'), [
            'email' => 'missing@example.test',
            'password' => 'wrong-password-'.$attempt,
        ])->assertSessionHas('error', 'Credentials do not match our records');
    }

    expect(User::query()->count())->toBe(0);
});

it('locks a writer account after five failed sign-ins', function (): void {
    $writer = Writer::query()->create([
        'name' => 'Ada Writer',
        'email' => 'ada.writer@example.test',
        'password' => 'password',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('writer.authentication.login.store'), [
            'email' => 'ada.writer@example.test',
            'password' => 'wrong-password-'.$attempt,
        ]);
    }

    expect($writer->fresh()?->isLoginLocked())->toBeTrue();

    $this->post(route('writer.authentication.login.store'), [
        'email' => 'ada.writer@example.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('writer');
});
