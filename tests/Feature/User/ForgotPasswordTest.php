<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('brands the reset email as lorespinner', function () {
    config()->set('app.name', 'lore-spinner-rnd');

    $user = User::factory()->make([
        'email' => 'player@example.com',
    ]);

    $html = (new ResetPassword('token'))->toMail($user)->render()->toHtml();

    expect($html)
        ->toContain('lorespinner')
        ->not->toContain('lore-spinner-rnd')
        ->and(config('mail.from.name'))->toBe('lorespinner');
});

it('renders the forgot password page before a reset link is sent', function () {
    $this->get(route('user.authentication.forgot-password.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Authentication/ForgotPassword')
            ->where('resetLinkSent', false)
            ->where('email', ''));
});

it('emails a password reset link', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'player@example.com',
    ]);

    $this->from(route('user.authentication.forgot-password.create'))
        ->post(route('user.authentication.forgot-password.store'), [
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user.authentication.forgot-password.create'))
        ->assertSessionHas('success', 'We have emailed your password reset link.');

    $this->get(route('user.authentication.forgot-password.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Authentication/ForgotPassword')
            ->where('resetLinkSent', true)
            ->where('email', $user->email));

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, '/user/authentication/reset-password/')
            && str_contains($url, 'email='.urlencode($user->email));
    });
});

it('keeps resend available when another link is requested too soon', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'player@example.com',
    ]);

    $this->post(route('user.authentication.forgot-password.store'), [
        'email' => $user->email,
    ])->assertSessionHas('success');

    $this->from(route('user.authentication.forgot-password.create'))
        ->post(route('user.authentication.forgot-password.store'), [
            'email' => $user->email,
        ])
        ->assertSessionHas('reset_link_sent')
        ->assertSessionHasErrors([
            'email' => 'Please wait before requesting another reset link.',
        ]);

    $this->get(route('user.authentication.forgot-password.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Authentication/ForgotPassword')
            ->where('resetLinkSent', true)
            ->where('email', $user->email));
});

it('does not email a reset link for an unknown address', function () {
    Notification::fake();

    $this->from(route('user.authentication.forgot-password.create'))
        ->post(route('user.authentication.forgot-password.store'), [
            'email' => 'missing@example.com',
        ])
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

it('resets the password from the emailed link', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'player@example.com',
        'password' => 'old-password',
    ]);

    $this->post(route('user.authentication.forgot-password.store'), [
        'email' => $user->email,
    ]);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->get(route('user.authentication.reset-password.create', [
        'token' => $token,
        'email' => $user->email,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Authentication/ResetPassword')
            ->where('email', $user->email)
            ->where('token', $token));

    $this->post(route('user.authentication.reset-password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user.authentication.login.create'))
        ->assertSessionHas('success', 'Your password has been reset. You can now log in.');

    expect(Hash::check('new-password', (string) $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', (string) $user->fresh()->password))->toBeFalse();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create([
        'email' => 'player@example.com',
    ]);

    $this->from(route('user.authentication.reset-password.create', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
    ]))
        ->post(route('user.authentication.reset-password.store'), [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('email');

    expect(Hash::check('new-password', (string) $user->fresh()->password))->toBeFalse();
});
