<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property int $chapter_id
 * @property int $position
 * @property string $title
 * @property string $content
 * @property string|null $objectives
 * @property array<string>|null $attributes
 * @property int|null $session_number
 * @property bool $requires_choice
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Chapter $chapter
 * @property-read Collection<int, Prompt> $prompts
 * @property-read int|null $prompts_count
 * @property-read Collection<int, PlayerTokenUsage> $tokenUsages
 */
final class Event extends Model
{
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * @return HasMany<Prompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /** @return BelongsToMany<PlayerTokenUsage, $this> */
    public function tokenUsages(): BelongsToMany
    {
        return $this->belongsToMany(
            PlayerTokenUsage::class,
            'player_token_usage_events',
        )->withPivot('attribution_role');
    }

    /**
     * Convenience helper for non-hot-path contexts (Filament, debugging, seeders).
     * Production runtime controllers must use the explicit inline query with readiness gate.
     */
    public function sessionAdaptation(): ?SessionAdaptation
    {
        if ($this->session_number === null) {
            return null;
        }

        return SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $this->chapter->story_id))
            ->where('session_number', $this->session_number)
            ->first();
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'attributes' => 'json',
            'requires_choice' => 'boolean',
        ];
    }
}
