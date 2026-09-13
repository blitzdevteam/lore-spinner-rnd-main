<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $stripe_price_id
 * @property int $price_cents
 * @property string $currency
 * @property string|null $tagline
 * @property string|null $description
 * @property array<int, string> $features
 * @property int $action_limit
 * @property int $voice_minutes_limit
 * @property string $accent
 * @property string $icon_key
 * @property bool $is_featured
 * @property bool $is_active
 * @property int $sort_order
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SubscriptionEntitlement> $entitlements
 */
final class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * @param  Builder<SubscriptionPlan>  $query
     * @return Builder<SubscriptionPlan>
     */
    public function scopeAvailableForCheckout(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('stripe_price_id')
            ->where('stripe_price_id', '!=', '');
    }

    /** @return HasMany<SubscriptionEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(SubscriptionEntitlement::class);
    }

    public function voiceMillisecondsLimit(): int
    {
        return $this->voice_minutes_limit * 60_000;
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'features' => 'array',
            'action_limit' => 'integer',
            'voice_minutes_limit' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
