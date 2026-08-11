<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * Immutable, player-runtime-only token and price ledger entry.
 *
 * @property string $id
 * @property string $game_id
 * @property string|null $prompt_id
 * @property string $prompt_reference_id
 * @property string|null $source_prompt_reference_id
 * @property int $story_id
 * @property int $user_id
 * @property int|null $game_session_completion_id
 * @property int|null $playable_moment_id
 * @property int|null $anchor_event_id
 * @property int|null $anchor_chapter_id
 * @property int $story_cycle_number
 * @property int $session_number
 * @property string|null $moment_key
 * @property array<int, int> $source_event_ids
 * @property array<int, int> $source_chapter_ids
 * @property string $kind
 * @property string|null $system_prompt
 * @property string|null $generated_output
 * @property string $provider
 * @property string $model
 * @property string $currency
 * @property string $pricing_version
 * @property Carbon|null $pricing_effective_from
 * @property string $pricing_source
 * @property string $input_price_per_million_usd
 * @property string $output_price_per_million_usd
 * @property string $cache_write_price_per_million_usd
 * @property string $cache_read_price_per_million_usd
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_write_input_tokens
 * @property int $cache_read_input_tokens
 * @property int $cached_input_tokens
 * @property int $total_input_tokens
 * @property int $total_tokens
 * @property int $reasoning_tokens
 * @property string $input_cost_usd
 * @property string $output_cost_usd
 * @property string $cache_write_cost_usd
 * @property string $cache_read_cost_usd
 * @property string $total_cost_usd
 * @property bool $usage_reported
 * @property array<string, mixed> $raw_usage
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read Prompt|null $prompt
 * @property-read Story $story
 * @property-read User $user
 * @property-read GameSessionCompletion|null $gameSessionCompletion
 * @property-read PlayableMoment|null $playableMoment
 * @property-read Event|null $anchorEvent
 * @property-read Chapter|null $anchorChapter
 * @property-read Collection<int, Event> $events
 * @property-read Collection<int, Chapter> $chapters
 */
final class PlayerTokenUsage extends Model
{
    use HasUlids;

    public const KIND_BEGIN = 'begin';

    public const KIND_TURN = 'turn';

    public const KIND_NEXT_SESSION = 'next_session';

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return Builder<PlayerTokenUsage>
     */
    public function scopeForSession(
        Builder $query,
        string $gameId,
        int $storyCycleNumber,
        int $sessionNumber,
    ): Builder {
        return $query
            ->where('game_id', $gameId)
            ->where('story_cycle_number', $storyCycleNumber)
            ->where('session_number', $sessionNumber);
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return Builder<PlayerTokenUsage>
     */
    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->whereHas('events', fn (Builder $events): Builder => $events
            ->whereKey($eventId));
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return Builder<PlayerTokenUsage>
     */
    public function scopeForChapter(Builder $query, int $chapterId): Builder
    {
        return $query->whereHas('chapters', fn (Builder $chapters): Builder => $chapters
            ->whereKey($chapterId));
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

    /** @return BelongsTo<Story, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<GameSessionCompletion, $this> */
    public function gameSessionCompletion(): BelongsTo
    {
        return $this->belongsTo(GameSessionCompletion::class);
    }

    /** @return BelongsTo<PlayableMoment, $this> */
    public function playableMoment(): BelongsTo
    {
        return $this->belongsTo(PlayableMoment::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function anchorEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'anchor_event_id');
    }

    /** @return BelongsTo<Chapter, $this> */
    public function anchorChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'anchor_chapter_id');
    }

    /** @return BelongsToMany<Event, $this> */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(
            Event::class,
            'player_token_usage_events',
        )->withPivot('attribution_role');
    }

    /** @return BelongsToMany<Chapter, $this> */
    public function chapters(): BelongsToMany
    {
        return $this->belongsToMany(
            Chapter::class,
            'player_token_usage_chapters',
        )->withPivot('attribution_role');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'source_event_ids' => 'array',
            'source_chapter_ids' => 'array',
            'pricing_effective_from' => 'immutable_datetime',
            'input_price_per_million_usd' => 'decimal:6',
            'output_price_per_million_usd' => 'decimal:6',
            'cache_write_price_per_million_usd' => 'decimal:6',
            'cache_read_price_per_million_usd' => 'decimal:6',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_write_input_tokens' => 'integer',
            'cache_read_input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'total_input_tokens' => 'integer',
            'total_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'input_cost_usd' => 'decimal:12',
            'output_cost_usd' => 'decimal:12',
            'cache_write_cost_usd' => 'decimal:12',
            'cache_read_cost_usd' => 'decimal:12',
            'total_cost_usd' => 'decimal:12',
            'usage_reported' => 'boolean',
            'raw_usage' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
