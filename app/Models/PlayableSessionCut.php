<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $story_id
 * @property int $session_adaptation_id
 * @property int $session_number
 * @property string $cut_key
 * @property string $status
 * @property-read Story $story
 * @property-read SessionAdaptation $sessionAdaptation
 * @property-read Collection<int, PlayableMoment> $moments
 * @property-read Collection<int, EventPlayabilityRole> $eventRoles
 */
final class PlayableSessionCut extends Model
{
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function sessionAdaptation(): BelongsTo
    {
        return $this->belongsTo(SessionAdaptation::class);
    }

    public function moments(): HasMany
    {
        return $this->hasMany(PlayableMoment::class)->orderBy('moment_order');
    }

    public function eventRoles(): HasMany
    {
        return $this->hasMany(EventPlayabilityRole::class);
    }
}
