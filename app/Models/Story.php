<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryRatingEnum;
use App\Enums\Story\StoryStatusEnum;
use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $creator_id
 * @property int $category_id
 * @property string $title
 * @property string|null $teaser
 * @property string|null $opening
 * @property array|null $system_prompt
 * @property StoryStatusEnum $status
 * @property StoryRatingEnum $rating
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Creator $creator
 * @property-read Category $category
 * @property-read Collection<int, Chapter> $chapters
 * @property-read int|null $chapters_count
 * @property-read Collection<int, Comment> $comments
 * @property-read int|null $comments_count
 * @property-read Collection<int, Event> $events
 * @property-read int|null $events_count
 * @property-read Collection<int, Game> $games
 * @property-read int|null $games_count
 * @property-read Collection<int, CompletionReceipt> $completionReceipts
 * @property-read Collection<int, MultiplayerLobby> $multiplayerLobbies
 * @property-read Collection<int, World> $worlds
 * @property-read int|null $worlds_count
 * @property-read StoryAdaptation|null $adaptation
 */
final class Story extends Model implements HasMedia
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'status' => StoryStatusEnum::class,
        'rating' => StoryRatingEnum::class,
        'published_at' => 'datetime',
        'system_prompt' => 'json',
    ];

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('script')
            ->acceptsMimeTypes(['text/plain'])
            ->singleFile()
            ->useDisk('private');

        $this
            ->addMediaCollection('cover')
            ->acceptsMimeTypes(['image/jpeg', 'image/png'])
            ->singleFile()
            ->useDisk('public');

        $this
            ->addMediaCollection('banner')
            ->acceptsMimeTypes(['image/jpeg', 'image/png'])
            ->singleFile()
            ->useDisk('public');

        $this
            ->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');

        $this
            ->addMediaCollection('outro')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile()
            ->useDisk('public');
    }

    /**
     * @return BelongsTo<Creator, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Chapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    /**
     * @return HasManyThrough<Event, Chapter, $this>
     */
    public function events(): HasManyThrough
    {
        return $this->hasManyThrough(Event::class, Chapter::class);
    }

    /**
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * @return HasMany<CompletionReceipt, $this>
     */
    public function completionReceipts(): HasMany
    {
        return $this->hasMany(CompletionReceipt::class);
    }

    /**
     * @return HasMany<MultiplayerLobby, $this>
     */
    public function multiplayerLobbies(): HasMany
    {
        return $this->hasMany(MultiplayerLobby::class);
    }

    /**
     * @return BelongsToMany<World, $this>
     */
    public function worlds(): BelongsToMany
    {
        return $this->belongsToMany(World::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * @return HasOne<StoryAdaptation, $this>
     */
    public function adaptation(): HasOne
    {
        return $this->hasOne(StoryAdaptation::class);
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Return the trimmed source text slice that corresponds to the given session
     * number. Used by per-session adaptation jobs instead of raw source windows.
     *
     * Resolution order:
     *   1. Chapter segments in ip_trimming.trimmed_source_text.chapter_segments
     *      that belong to events assigned to $sessionNumber.
     *   2. Full trimmed text window if chapter segments are missing.
     *   3. Raw source window as a final fallback (pre-ip_trimming runs).
     */
    public function getSessionTrimmedText(int $sessionNumber, int $maxChars = 16000): string
    {
        $adaptation = $this->adaptation;

        if (empty($adaptation?->ip_trimming)) {
            return mb_substr($this->getScriptContent(), 0, $maxChars);
        }

        $chapterSegments = collect($adaptation->ip_trimming['trimmed_source_text']['chapter_segments'] ?? []);

        if ($chapterSegments->isEmpty()) {
            $fullTrimmed = (string) ($adaptation->ip_trimming['trimmed_source_text']['text'] ?? '');

            return mb_substr($fullTrimmed !== '' ? $fullTrimmed : $this->getScriptContent(), 0, $maxChars);
        }

        // Find which chapters have events assigned to this session number.
        $sessionChapterIds = DB::table('events')
            ->join('chapters', 'chapters.id', '=', 'events.chapter_id')
            ->where('chapters.story_id', $this->id)
            ->where('events.session_number', $sessionNumber)
            ->distinct()
            ->pluck('events.chapter_id')
            ->all();

        if (empty($sessionChapterIds)) {
            // Session number not yet assigned — fall back to full trimmed window.
            $fullTrimmed = (string) ($adaptation->ip_trimming['trimmed_source_text']['text'] ?? '');

            return mb_substr($fullTrimmed !== '' ? $fullTrimmed : $this->getScriptContent(), 0, $maxChars);
        }

        $text = $chapterSegments
            ->filter(fn ($seg) => in_array($seg['chapter_id'] ?? null, $sessionChapterIds, true))
            ->sortBy('chapter_position')
            ->map(fn ($seg) => (string) ($seg['text'] ?? ''))
            ->implode("\n\n");

        return mb_substr($text, 0, $maxChars);
    }

    /**
     * Read the full script text, falling back to reconstructed event content
     * when the media file is unavailable (e.g. ephemeral Cloud filesystems).
     */
    public function getScriptContent(): string
    {
        $path = $this->getFirstMediaPath('script');

        if ($path && file_exists($path)) {
            return file_get_contents($path);
        }

        return $this->chapters()
            ->orderBy('position')
            ->with(['events' => fn ($q) => $q->orderBy('position')])
            ->get()
            ->flatMap(fn (Chapter $chapter) => $chapter->events->map(
                fn (Event $event) => "## {$chapter->title} — {$event->title}\n\n{$event->content}"
            ))
            ->implode("\n\n---\n\n");
    }

    public function canMarkAsPublished(): bool
    {
        return $this->status === StoryStatusEnum::DRAFT
            && $this->chapters()
                ->orderBy('position')
                ->limit(1)
                ->value('status') === ChapterStatusEnum::READY_TO_PLAY;
    }

    /**
     * @param  Builder<Story>  $query
     */
    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', StoryStatusEnum::DRAFT);
    }

    /**
     * @param  Builder<Story>  $query
     */
    #[Scope]
    protected function awaitingExtractingChaptersRequest(Builder $query): void
    {
        $query->where('status', StoryStatusEnum::AWAITING_EXTRACTING_CHAPTERS_REQUEST);
    }

    /**
     * @param  Builder<Story>  $query
     */
    #[Scope]
    protected function extractingChapters(Builder $query): void
    {
        $query->where('status', StoryStatusEnum::EXTRACTING_CHAPTERS);
    }

    /**
     * @param  Builder<Story>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', StoryStatusEnum::PUBLISHED);
    }
}
