<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Multiplayer\MultiplayerLobbyMemberRoleEnum;
use Database\Factories\MultiplayerLobbyMemberFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string $id
 * @property string $lobby_id
 * @property int $user_id
 * @property MultiplayerLobbyMemberRoleEnum $role
 * @property bool $is_ready
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MultiplayerLobby $lobby
 * @property-read User $user
 * @property-read Collection<int, MultiplayerLobbyProposal> $proposals
 */
final class MultiplayerLobbyMember extends Model
{
    /** @use HasFactory<MultiplayerLobbyMemberFactory> */
    use HasFactory;

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

    /**
     * @return HasMany<MultiplayerLobbyProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(MultiplayerLobbyProposal::class, 'member_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'role' => MultiplayerLobbyMemberRoleEnum::class,
            'is_ready' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
