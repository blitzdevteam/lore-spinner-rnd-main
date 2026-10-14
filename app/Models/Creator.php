<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\LockableAccount;
use App\Jobs\Creator\CreatorAvatarGeneratorJob;
use App\Models\Concerns\LocksAfterFailedLogins;
use Database\Factories\CreatorFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $username
 * @property string $email
 * @property string $password
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property string|null $avatar
 * @property string|null $bio
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 * @property-read Collection<int, Story> $stories
 * @property-read int|null $stories_count
 */
final class Creator extends Authenticatable implements FilamentUser, HasMedia, HasName, LockableAccount
{
    /** @use HasFactory<CreatorFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use LocksAfterFailedLogins;
    use Notifiable;

    protected $guarded = [
        'id', 'created_at', 'updated_at', 'failed_login_attempts', 'locked_until',
    ];

    protected $hidden = [
        'password', 'remember_token', 'email_verified_at', 'is_active', 'created_at', 'updated_at',
    ];

    protected $appends = [
        'full_name',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    /**
     * Register the media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->acceptsMimeTypes(['image/jpeg', 'image/png'])
            ->singleFile()
            ->useDisk('public')
            ->useFallbackUrl(Storage::disk('public')->url('avatar/'.str((string) $this->id)->substr(0, 1).'.png'));
    }

    /**
     * Send the email verification notification.
     */
    #[Override]
    public function sendEmailVerificationNotification(): void
    {
        /** @var int $verificationExpire */
        $verificationExpire = Config::get('auth.verification.expire', 60);

        VerifyEmail::createUrlUsing(fn (self $notifiable) => URL::temporarySignedRoute(
            'user.authentication.verify.confirm',
            Carbon::now()->addMinutes($verificationExpire),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ]
        ));

        $this->notify(new VerifyEmail);
    }

    /**
     * @return HasMany<Story, $this>
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    protected static function booted(): void
    {
        self::created(function (Creator $creator): void {
            CreatorAvatarGeneratorJob::dispatch($creator);
        });
    }

    /**
     * @return string[]
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function firstName(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => str($value)->lower()->ucfirst()->toString(),
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function lastName(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => str($value)->lower()->ucfirst()->toString(),
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => mb_trim($this->first_name.' '.$this->last_name)
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->getFirstMediaUrl('avatar')
        );
    }
}
