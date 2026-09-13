<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string $id
 * @property string $subscription_entitlement_id
 * @property int $user_id
 * @property string|null $game_id
 * @property string|null $prompt_id
 * @property string $type
 * @property string $status
 * @property int $quantity
 * @property string|null $idempotency_key
 * @property Carbon|null $expires_at
 * @property Carbon $occurred_at
 * @property-read SubscriptionEntitlement $entitlement
 * @property-read User $user
 * @property-read Game|null $game
 * @property-read Prompt|null $prompt
 */
final class SubscriptionUsageEvent extends Model
{
    use HasUlids;

    public const TYPE_ACTION = 'action';

    public const TYPE_VOICE = 'voice';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_RELEASED = 'released';

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /** @return BelongsTo<SubscriptionEntitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(SubscriptionEntitlement::class, 'subscription_entitlement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Prompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'immutable_datetime',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
