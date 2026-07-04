<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $story_id
 * @property int $user_id
 * @property int|null $current_session_number
 * @property int $current_story_cycle_number
 * @property string $model
 * @property array<string, mixed>|null $world_state
 * @property string|null $symbolic_memory
 * @property array{chaotic:int, lawful:int, neutral:int}|null $alignment_scaffold
 * @property string|null $defining_choice_id
 * @property string|null $defining_path_label
 * @property string|null $defining_choice_line
 * @property bool $is_climactic_choice
 * @property bool $current_session_complete
 * @property bool $is_preview
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read User $user
 * @property-read Collection<int, Prompt> $prompts
 * @property-read Collection<int, GameSessionCompletion> $sessionCompletions
 * @property-read Collection<int, GameReset> $resets
 * @property-read Collection<int, GameCompletion> $completionHistory
 */
final class Game extends Model
{
    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'world_state'              => 'json',
            'alignment_scaffold'       => 'json',
            'is_climactic_choice'      => 'boolean',
            'current_session_complete' => 'boolean',
            'is_preview'               => 'boolean',
            'completed_at'             => 'datetime',
        ];
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

    /**
     * Intentionally returns an UNORDERED HasMany.
     * Call sites that need a specific order MUST add it explicitly
     * (oldest() for UI rendering, latest() for newest-first reads).
     *
     * @return HasMany<Prompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /**
     * @return HasMany<GameSessionCompletion, $this>
     */
    public function sessionCompletions(): HasMany
    {
        return $this->hasMany(GameSessionCompletion::class);
    }

    /**
     * @return HasMany<GameReset, $this>
     */
    public function resets(): HasMany
    {
        return $this->hasMany(GameReset::class);
    }

    /**
     * Immutable completion history — one row per completed story cycle.
     * Use this for analytics; games.completed_at is current/mutable state.
     *
     * @return HasMany<GameCompletion, $this>
     */
    public function completionHistory(): HasMany
    {
        return $this->hasMany(GameCompletion::class);
    }
}
