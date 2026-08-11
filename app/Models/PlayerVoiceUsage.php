<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * Immutable, player-runtime-only voice generation usage entry.
 *
 * @property string $id
 * @property string $game_id
 * @property string|null $prompt_id
 * @property string $prompt_reference_id
 * @property int $story_id
 * @property int $user_id
 * @property int $story_cycle_number
 * @property int $session_number
 * @property string $provider
 * @property string $model
 * @property string|null $voice_id
 * @property string $output_format
 * @property string $billing_unit
 * @property int $input_characters
 * @property int $billable_units
 * @property int $audio_bytes
 * @property int $latency_ms
 * @property string $currency
 * @property bool $pricing_available
 * @property string|null $pricing_version
 * @property Carbon|null $pricing_effective_from
 * @property string|null $pricing_source
 * @property string|null $rate_per_thousand_usd
 * @property string|null $estimated_cost_usd
 * @property string|null $provider_request_id
 * @property string|null $provider_trace_id
 * @property string $text_snapshot
 * @property array<string, mixed> $provider_metadata
 * @property Carbon $occurred_at
 * @property-read Game $game
 * @property-read Prompt|null $prompt
 * @property-read Story $story
 * @property-read User $user
 */
final class PlayerVoiceUsage extends Model
{
    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

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

    #[Override]
    protected function casts(): array
    {
        return [
            'input_characters' => 'integer',
            'billable_units' => 'integer',
            'audio_bytes' => 'integer',
            'latency_ms' => 'integer',
            'pricing_available' => 'boolean',
            'pricing_effective_from' => 'immutable_datetime',
            'rate_per_thousand_usd' => 'decimal:6',
            'estimated_cost_usd' => 'decimal:12',
            'provider_metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
