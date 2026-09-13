<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $gender
 * @property string|null $username
 * @property string $email
 * @property string $password
 * @property string|null $avatar
 * @property string|null $bio
 * @property bool $is_active
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 * @property-read bool $is_profile_completed
 * @property-read Collection<int, Comment> $comments
 * @property-read int|null $comments_count
 * @property-read Collection<int, Game> $games
 * @property-read int|null $games_count
 * @property-read Collection<int, MultiplayerLobby> $hostedMultiplayerLobbies
 * @property-read Collection<int, MultiplayerLobbyMember> $multiplayerLobbyMemberships
 * @property-read Collection<int, Story> $bookmarkedStories
 * @property-read int|null $bookmarked_stories_count
 * @property-read Collection<int, SubscriptionEntitlement> $subscriptionEntitlements
 * @property-read Collection<int, SubscriptionUsageEvent> $subscriptionUsageEvents
 */
final class User extends Authenticatable implements CanResetPassword, HasMedia
{
    use Billable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use Notifiable;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'email_verified_at', 'is_active', 'created_at', 'updated_at',
        'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at',
    ];

    protected $appends = [
        'full_name',
        'is_profile_completed',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * Register the media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml'])
            ->singleFile()
            ->useDisk('public')
            ->useFallbackUrl('/storage/profile.svg');
    }

    /**
     * Public URL for the user's avatar, always as a same-origin /storage/ path.
     */
    public function resolveAvatarUrl(): string
    {
        $mediaUrl = $this->getFirstMediaUrl('avatar');

        if (filled($mediaUrl)) {
            return self::toRelativeStoragePath($mediaUrl) ?? $this->defaultAvatarPath();
        }

        return $this->defaultAvatarPath();
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'author');
    }

    /**
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * @return HasMany<MultiplayerLobby, $this>
     */
    public function hostedMultiplayerLobbies(): HasMany
    {
        return $this->hasMany(MultiplayerLobby::class, 'host_user_id');
    }

    /**
     * @return HasMany<MultiplayerLobbyMember, $this>
     */
    public function multiplayerLobbyMemberships(): HasMany
    {
        return $this->hasMany(MultiplayerLobbyMember::class);
    }

    /**
     * @return HasMany<SubscriptionEntitlement, $this>
     */
    public function subscriptionEntitlements(): HasMany
    {
        return $this->hasMany(SubscriptionEntitlement::class);
    }

    /**
     * @return HasMany<SubscriptionUsageEvent, $this>
     */
    public function subscriptionUsageEvents(): HasMany
    {
        return $this->hasMany(SubscriptionUsageEvent::class);
    }

    /**
     * @return BelongsToMany<Story, $this>
     */
    public function bookmarkedStories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'bookmarks')->withTimestamps();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function isProfileCompleted(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => ! blank($this->username)
        );
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

    /**
     * @return Attribute<string, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->resolveAvatarUrl(),
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): ?string => blank($value) ? null : mb_strtolower($value)
        );
    }

    private static function toRelativeStoragePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return null;
        }

        return $path;
    }

    private function defaultAvatarPath(): string
    {
        if ($this->id) {
            return '/storage/avatar/'.(($this->id % 9) + 1).'.png';
        }

        return '/storage/profile.svg';
    }
}
