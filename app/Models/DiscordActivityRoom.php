<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DiscordActivityRoomFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $instance_id
 * @property string|null $channel_id
 * @property string|null $guild_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, DiscordActivityRoomMember> $members
 */
final class DiscordActivityRoom extends Model
{
    /** @use HasFactory<DiscordActivityRoomFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * @return HasMany<DiscordActivityRoomMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(DiscordActivityRoomMember::class, 'room_id');
    }
}
