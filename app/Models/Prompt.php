<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $game_id
 * @property string|null $source_prompt_id
 * @property int|null $session_number
 * @property string|null $moment_key
 * @property bool|null $exit_condition_met
 * @property string|null $moment_result
 * @property string|null $moment_footnote
 * @property string|null $route_relation
 * @property string|null $impact_scope
 * @property string $response
 * @property array $choices
 * @property string|null $prompt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read Prompt|null $sourcePrompt
 * @property-read Prompt|null $responsePrompt
 * @property-read PlayerTokenUsage|null $tokenUsage
 */
final class Prompt extends Model
{
    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'choices' => 'array',
        'exit_condition_met' => 'boolean',
    ];

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function sourcePrompt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_prompt_id');
    }

    public function responsePrompt(): HasOne
    {
        return $this->hasOne(self::class, 'source_prompt_id');
    }

    /** @return HasOne<PlayerTokenUsage, $this> */
    public function tokenUsage(): HasOne
    {
        return $this->hasOne(PlayerTokenUsage::class);
    }
}
