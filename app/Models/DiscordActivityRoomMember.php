<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DiscordActivityRoomMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string $id
 * @property string $room_id
 * @property string $discord_user_id
 * @property string $username
 * @property string|null $global_name
 * @property Carbon $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordActivityRoom $room
 */
final class DiscordActivityRoomMember extends Model
{
    /** @use HasFactory<DiscordActivityRoomMemberFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * @return BelongsTo<DiscordActivityRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(DiscordActivityRoom::class, 'room_id');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
