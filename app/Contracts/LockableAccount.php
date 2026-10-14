<?php

declare(strict_types=1);

namespace App\Contracts;

interface LockableAccount
{
    public function isLoginLocked(): bool;

    public function releaseExpiredLoginLock(): void;

    public function recordFailedLogin(): void;

    public function clearLoginLockout(): void;

    public function loginLockMessage(): string;
}
