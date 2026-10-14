<?php

declare(strict_types=1);

namespace App\Auth;

use App\Contracts\LockableAccount;
use App\Models\Creator;
use App\Models\Manager;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Validation\ValidationException;

final class EnforceLoginLockout
{
    public function blockLockedAccount(Attempting $event): void
    {
        $account = $this->accountFromAttempt($event);

        if (! $account instanceof LockableAccount) {
            return;
        }

        $account->releaseExpiredLoginLock();

        if ($account->isLoginLocked()) {
            $this->deny($account);
        }
    }

    public function recordFailure(Failed $event): void
    {
        $account = $event->user;

        if (! $account instanceof LockableAccount) {
            return;
        }

        $account->recordFailedLogin();

        if ($account->isLoginLocked()) {
            $this->deny($account);
        }
    }

    public function clearAfterSuccess(Login $event): void
    {
        $account = $event->user;

        if ($account instanceof LockableAccount) {
            $account->clearLoginLockout();
        }
    }

    private function deny(LockableAccount $account): never
    {
        $message = $account->loginLockMessage();

        throw ValidationException::withMessages([
            'email' => $message,
            'data.email' => $message,
        ]);
    }

    private function accountFromAttempt(Attempting $event): ?LockableAccount
    {
        $email = $this->emailFromCredentials($event->credentials);

        if ($email === null) {
            return null;
        }

        $account = match ($event->guard) {
            'user' => User::query()->where('email', $email)->first(),
            'writer' => Writer::query()->where('email', $email)->first(),
            'creator' => Creator::query()->where('email', $email)->first(),
            'manager' => Manager::query()->where('email', $email)->first(),
            default => null,
        };

        return $account instanceof LockableAccount ? $account : null;
    }

    private function emailFromCredentials(mixed $credentials): ?string
    {
        if (! is_array($credentials)) {
            return null;
        }

        $email = $credentials['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
