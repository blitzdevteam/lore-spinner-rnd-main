<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompletionReceiptMode;
use Database\Factories\CompletionReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * Snapshot of a player-facing completion ceremony, saved when a session or story ends.
 *
 * @property int $id
 * @property int $user_id
 * @property string $game_id
 * @property int $story_id
 * @property int $story_cycle_number
 * @property int $session_number
 * @property CompletionReceiptMode $mode
 * @property array<string, mixed> $receipt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read Story $story
 * @property-read User $user
 */
final class CompletionReceipt extends Model
{
    /** @use HasFactory<CompletionReceiptFactory> */
    use HasFactory;

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

    #[Override]
    protected function casts(): array
    {
        return [
            'story_cycle_number' => 'integer',
            'session_number' => 'integer',
            'mode' => CompletionReceiptMode::class,
            'receipt' => 'array',
        ];
    }
}
