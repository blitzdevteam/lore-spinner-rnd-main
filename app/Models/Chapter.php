<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chapter\ChapterStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $story_id
 * @property int $position
 * @property string $title
 * @property string $teaser
 * @property string $content
 * @property ChapterStatusEnum $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read Collection<int, Event> $events
 * @property-read int|null $events_count
 * @property-read Collection<int, PlayerTokenUsage> $tokenUsages
 */
final class Chapter extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'status' => ChapterStatusEnum::class,
    ];

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('cover')
            ->acceptsMimeTypes(['image/jpeg', 'image/png'])
            ->singleFile()
            ->useDisk('public');
    }

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return BelongsToMany<PlayerTokenUsage, $this> */
    public function tokenUsages(): BelongsToMany
    {
        return $this->belongsToMany(
            PlayerTokenUsage::class,
            'player_token_usage_chapters',
        )->withPivot('attribution_role');
    }

    public function isEditable(): bool
    {
        return match ($this->status) {
            ChapterStatusEnum::READY_TO_PLAY => ! $this->events()
                ->whereHas('prompts', fn (Builder $query) => $query
                    ->whereIn('game_id', $this->story->games()->select('id'))
                )
                ->exists(),
            ChapterStatusEnum::AWAITING_CREATOR_REVIEW,
            ChapterStatusEnum::AWAITING_EXTRACTING_EVENTS_REQUEST,
            ChapterStatusEnum::REJECTED => true,
            default => false,
        };
    }
}
