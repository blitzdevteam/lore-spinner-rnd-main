<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $lobby_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MultiplayerLobby $lobby
 * @property-read User $user
 */
final class MultiplayerLobbyChatMessage extends Model
{
    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * @return BelongsTo<MultiplayerLobby, $this>
     */
    public function lobby(): BelongsTo
    {
        return $this->belongsTo(MultiplayerLobby::class, 'lobby_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
