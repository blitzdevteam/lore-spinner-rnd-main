<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Adaptation\AdaptationStatusEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $story_id
 * @property AdaptationStatusEnum $adaptation_status
 * @property bool $boiler_enabled
 * @property array|null $ip_trimming
 * @property array|null $format_detection
 * @property array|null $ip_audit
 * @property array|null $voice_profile
 * @property array|null $story_session_map
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read Collection<int, SessionAdaptation> $sessionAdaptations
 * @property-read int|null $session_adaptations_count
 */
final class StoryAdaptation extends Model
{
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'adaptation_status' => AdaptationStatusEnum::class,
        'boiler_enabled' => 'boolean',
        'ip_trimming' => 'json',
        'format_detection' => 'json',
        'ip_audit' => 'json',
        'voice_profile' => 'json',
        'story_session_map' => 'json',
    ];

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * @return HasMany<SessionAdaptation, $this>
     */
    public function sessionAdaptations(): HasMany
    {
        return $this->hasMany(SessionAdaptation::class);
    }
}
