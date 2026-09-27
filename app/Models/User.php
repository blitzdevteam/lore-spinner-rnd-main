<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Investor\SyncInvestorPlanAccessAction;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Laravel\Cashier\Billable;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property UserType $type
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $gender
 * @property string|null $username
 * @property string $email
 * @property string $password
 * @property string|null $avatar
 * @property string|null $bio
 * @property bool $is_active
 * @property int|null $subscription_plan_id
 * @property int $duration_months
 * @property bool $access_enabled
 * @property string|null $notes
 * @property Carbon|null $access_granted_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $access_blocked_at
 * @property Carbon|null $credentials_set_at
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
 * @property-read Collection<int, CompletionReceipt> $completionReceipts
 * @property-read int|null $completion_receipts_count
 * @property-read Collection<int, MultiplayerLobby> $hostedMultiplayerLobbies
 * @property-read Collection<int, MultiplayerLobbyMember> $multiplayerLobbyMemberships
 * @property-read Collection<int, Story> $bookmarkedStories
 * @property-read int|null $bookmarked_stories_count
 * @property-read Collection<int, SubscriptionEntitlement> $subscriptionEntitlements
 * @property-read Collection<int, SubscriptionUsageEvent> $subscriptionUsageEvents
 * @property-read SubscriptionPlan|null $plan
 */
final class User extends Authenticatable implements CanResetPassword, HasMedia
{
    use Billable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use Notifiable;

    public const string PENDING_EMAIL_DOMAIN = 'pending.invalid';

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'email_verified_at', 'is_active', 'created_at', 'updated_at',
        'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at',
        'type', 'subscription_plan_id', 'duration_months', 'access_enabled', 'notes',
        'access_granted_at', 'expires_at', 'access_blocked_at', 'credentials_set_at',
    ];

    protected $appends = [
        'full_name',
        'is_profile_completed',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
        'type' => UserType::class,
        'duration_months' => 'integer',
        'access_enabled' => 'boolean',
        'access_granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'access_blocked_at' => 'datetime',
        'credentials_set_at' => 'datetime',
    ];

    public const UNLIMITED_DURATION_MONTHS = 0;

    /**
     * @return array<int, string>
     */
    public static function durationOptions(): array
    {
        return [
            1 => '1 month',
            2 => '2 months',
            3 => '3 months',
            6 => '6 months',
            12 => '12 months',
            self::UNLIMITED_DURATION_MONTHS => 'Unlimited',
        ];
    }

    public static function normalizeDurationMonths(int $months): int
    {
        if ($months === self::UNLIMITED_DURATION_MONTHS) {
            return self::UNLIMITED_DURATION_MONTHS;
        }

        return max(1, $months);
    }

    public static function accessExpiresAt(int $months, Carbon $grantedAt): ?Carbon
    {
        $months = self::normalizeDurationMonths($months);

        if ($months === self::UNLIMITED_DURATION_MONTHS) {
            return null;
        }

        return $grantedAt->copy()->addMonthsNoOverflow($months);
    }

    public function hasUnlimitedAccessDuration(): bool
    {
        return (int) $this->duration_months === self::UNLIMITED_DURATION_MONTHS;
    }

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
     * Public URL for the user's avatar, always as a same-origin path.
     */
    public function resolveAvatarUrl(): string
    {
        $mediaUrl = $this->getFirstMediaUrl('avatar');

        if (filled($mediaUrl)) {
            return self::toRelativePublicPath($mediaUrl) ?? $this->defaultAvatarPath();
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
     * @return HasMany<CompletionReceipt, $this>
     */
    public function completionReceipts(): HasMany
    {
        return $this->hasMany(CompletionReceipt::class);
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

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isInvestor(): bool
    {
        return $this->type === UserType::Investor;
    }

    public function isExpired(): bool
    {
        return $this->isInvestor()
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function hasActiveAccess(): bool
    {
        return $this->isInvestor() && $this->access_enabled && ! $this->isExpired();
    }

    public function hasActiveInvestorAccess(): bool
    {
        return $this->hasActiveAccess();
    }

    public function hasCompletedSetup(): bool
    {
        return $this->isInvestor() && $this->credentials_set_at !== null;
    }

    public function magicLoginUrl(): ?string
    {
        if (! $this->hasActiveAccess()) {
            return null;
        }

        if ($this->expires_at === null) {
            return URL::signedRoute('investor.access', ['user' => $this]);
        }

        return URL::temporarySignedRoute(
            'investor.access',
            $this->expires_at,
            ['user' => $this],
        );
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeInvestors(Builder $query): Builder
    {
        return $query->where('type', UserType::Investor);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopePlayers(Builder $query): Builder
    {
        return $query->where('type', UserType::User);
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

    #[Override]
    protected static function booted(): void
    {
        self::deleting(static function (User $user): void {
            if ($user->isInvestor()) {
                app(SyncInvestorPlanAccessAction::class)->revoke($user);
            }
        });
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

    private static function toRelativePublicPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        if (str_starts_with($path, '/storage/') || str_starts_with($path, '/media/')) {
            return $path;
        }

        return null;
    }

    private function defaultAvatarPath(): string
    {
        if ($this->id) {
            return '/storage/avatar/'.(($this->id % 9) + 1).'.png';
        }

        return '/storage/profile.svg';
    }
}
