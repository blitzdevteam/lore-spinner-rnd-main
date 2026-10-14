<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * @property int $failed_login_attempts
 * @property \Illuminate\Support\Carbon|null $locked_until
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait LocksAfterFailedLogins
{
    public const int MAX_FAILED_LOGIN_ATTEMPTS = 5;

    public const int LOCKOUT_MINUTES = 60;

    public function initializeLocksAfterFailedLogins(): void
    {
        $this->mergeCasts([
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
        ]);

        $this->makeHidden([
            'failed_login_attempts',
            'locked_until',
        ]);
    }

    public function isLoginLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function releaseExpiredLoginLock(): void
    {
        if ($this->locked_until === null || $this->locked_until->isFuture()) {
            return;
        }

        $this->clearLoginLockout();
    }

    public function recordFailedLogin(): void
    {
        $this->releaseExpiredLoginLock();

        if ($this->isLoginLocked()) {
            return;
        }

        $attempts = (int) $this->failed_login_attempts + 1;

        $this->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $attempts >= self::MAX_FAILED_LOGIN_ATTEMPTS
                ? now()->addMinutes(self::LOCKOUT_MINUTES)
                : null,
        ])->save();
    }

    public function clearLoginLockout(): void
    {
        if ((int) $this->failed_login_attempts === 0 && $this->locked_until === null) {
            return;
        }

        $this->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();
    }

    public function loginLockMessage(): string
    {
        $lockedUntil = $this->locked_until;

        if ($lockedUntil === null) {
            return 'This account is temporarily locked for 1 hour. An admin can unlock it sooner.';
        }

        $minutes = max(1, (int) ceil(now()->diffInSeconds($lockedUntil, absolute: true) / 60));

        if ($minutes >= self::LOCKOUT_MINUTES) {
            return 'This account is temporarily locked for 1 hour. An admin can unlock it sooner.';
        }

        $unit = $minutes === 1 ? 'minute' : 'minutes';

        return "This account is temporarily locked. Try again in {$minutes} {$unit}, or ask an admin to unlock it.";
    }
}
