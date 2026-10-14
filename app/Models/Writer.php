<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\LockableAccount;
use App\Models\Concerns\LocksAfterFailedLogins;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Writer extends Authenticatable implements LockableAccount
{
    use LocksAfterFailedLogins;
    use Notifiable;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
