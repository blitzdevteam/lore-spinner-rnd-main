<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property string $game_id
 * @property int $story_id
 * @property int $user_id
 * @property int $story_cycle_number
 * @property int $session_number
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read Story $story
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlayerTokenUsage> $tokenUsages
 */
final class GameSessionCompletion extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PlayerTokenUsage, $this> */
    public function tokenUsages(): HasMany
    {
        return $this->hasMany(PlayerTokenUsage::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'story_cycle_number' => 'integer',
            'session_number' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
