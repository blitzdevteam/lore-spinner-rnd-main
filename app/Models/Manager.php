<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\LockableAccount;
use App\Models\Concerns\LocksAfterFailedLogins;
use Database\Factories\ManagerFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $password
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 */
final class Manager extends Authenticatable implements FilamentUser, HasName, LockableAccount
{
    /** @use HasFactory<ManagerFactory> */
    use HasFactory;

    use LocksAfterFailedLogins;
    use Notifiable;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $appends = [
        'full_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->first_name.' '.$this->last_name
        );
    }
}
