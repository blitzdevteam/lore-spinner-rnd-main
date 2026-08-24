<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use Database\Factories\MultiplayerLobbyFactory;
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
 * @property int $story_id
 * @property int $host_user_id
 * @property string|null $game_id
 * @property string $room_code
 * @property MultiplayerLobbyModeEnum $mode
 * @property MultiplayerLobbyStatusEnum $status
 * @property string|null $current_arbiter_member_id
 * @property string|null $current_source_prompt_id
 * @property MultiplayerArbiterTurnStatusEnum|null $turn_status
 * @property int $max_players
 * @property array{custom_input_enabled: bool, voice_over_enabled: bool, power_ups_per_user: int, decision_window_seconds: int} $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read User $host
 * @property-read Game|null $game
 * @property-read MultiplayerLobbyMember|null $currentArbiter
 * @property-read Prompt|null $currentSourcePrompt
 * @property-read Collection<int, MultiplayerLobbyMember> $members
 * @property-read Collection<int, MultiplayerLobbyProposal> $proposals
 */
final class MultiplayerLobby extends Model
{
    /** @use HasFactory<MultiplayerLobbyFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

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
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<MultiplayerLobbyMember, $this>
     */
    public function currentArbiter(): BelongsTo
    {
        return $this->belongsTo(MultiplayerLobbyMember::class, 'current_arbiter_member_id');
    }

    /**
     * @return BelongsTo<Prompt, $this>
     */
    public function currentSourcePrompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'current_source_prompt_id');
    }

    /**
     * @return HasMany<MultiplayerLobbyMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(MultiplayerLobbyMember::class, 'lobby_id');
    }

    /**
     * @return HasMany<MultiplayerLobbyProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(MultiplayerLobbyProposal::class, 'lobby_id');
    }

    public function isMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    #[Override]
    public function getRouteKeyName(): string
    {
        return 'room_code';
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'turn_status' => MultiplayerArbiterTurnStatusEnum::class,
            'mode' => MultiplayerLobbyModeEnum::class,
            'status' => MultiplayerLobbyStatusEnum::class,
            'settings' => 'array',
        ];
    }
}
