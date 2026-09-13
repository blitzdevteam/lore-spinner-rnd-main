<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;
use Override;

/**
 * @property string $id
 * @property int $user_id
 * @property int $subscription_id
 * @property int $subscription_plan_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $action_limit
 * @property int $actions_used
 * @property int $voice_milliseconds_limit
 * @property int $voice_milliseconds_used
 * @property-read User $user
 * @property-read Subscription $subscription
 * @property-read SubscriptionPlan $plan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SubscriptionUsageEvent> $usageEvents
 */
final class SubscriptionEntitlement extends Model
{
    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return HasMany<SubscriptionUsageEvent, $this> */
    public function usageEvents(): HasMany
    {
        return $this->hasMany(SubscriptionUsageEvent::class);
    }

    public function remainingActions(): int
    {
        return max(0, $this->action_limit - $this->actions_used);
    }

    public function remainingVoiceMilliseconds(): int
    {
        return max(0, $this->voice_milliseconds_limit - $this->voice_milliseconds_used);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'action_limit' => 'integer',
            'actions_used' => 'integer',
            'voice_milliseconds_limit' => 'integer',
            'voice_milliseconds_used' => 'integer',
        ];
    }
}
