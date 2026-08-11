<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $playable_session_cut_id
 * @property string $moment_key
 * @property int $moment_order
 * @property int|null $anchor_event_id
 * @property array<int, int> $source_event_ids
 * @property array<string, mixed> $scene_card
 * @property-read PlayableSessionCut $cut
 * @property-read Event|null $anchorEvent
 * @property-read Collection<int, PlayerTokenUsage> $tokenUsages
 */
final class PlayableMoment extends Model
{
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'source_event_ids' => 'json',
        'scene_card' => 'json',
    ];

    public function cut(): BelongsTo
    {
        return $this->belongsTo(PlayableSessionCut::class, 'playable_session_cut_id');
    }

    public function anchorEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'anchor_event_id');
    }

    public function activeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'current_playable_moment_id');
    }

    /** @return HasMany<PlayerTokenUsage, $this> */
    public function tokenUsages(): HasMany
    {
        return $this->hasMany(PlayerTokenUsage::class);
    }
}
