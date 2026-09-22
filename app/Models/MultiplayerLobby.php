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
 * @property Carbon|null $decision_deadline_at
 * @property int|null $decision_remaining_seconds
 * @property int $max_players
 * @property array{choices_enabled: bool, custom_input_enabled: bool, voice_over_enabled: bool, power_ups_per_user: int, decision_window_seconds: int} $settings
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $closed_at
 * @property string|null $closed_reason
 * @property string|null $closed_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read User $host
 * @property-read Game|null $game
 * @property-read MultiplayerLobbyMember|null $currentArbiter
 * @property-read Prompt|null $currentSourcePrompt
 * @property-read Collection<int, MultiplayerLobbyMember> $members
 * @property-read Collection<int, MultiplayerLobbyProposal> $proposals
 * @property-read Collection<int, MultiplayerLobbyChatMessage> $chatMessages
 */
final class MultiplayerLobby extends Model
{
    /** @use HasFactory<MultiplayerLobbyFactory> */
    use HasFactory;

    use HasUlids;

    public const int DEFAULT_DECISION_WINDOW_SECONDS = 180;

    public const int DISABLED_DECISION_WINDOW_SECONDS = 0;

    /** @var list<int> */
    public const array DECISION_WINDOW_SECONDS = [self::DISABLED_DECISION_WINDOW_SECONDS, 180, 240, 300, 360];

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

    /**
     * @return HasMany<MultiplayerLobbyChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(MultiplayerLobbyChatMessage::class, 'lobby_id');
    }

    public function isMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function decisionWindowSeconds(): int
    {
        $decisionWindowSeconds = (int) ($this->settings['decision_window_seconds'] ?? self::DEFAULT_DECISION_WINDOW_SECONDS);

        return in_array($decisionWindowSeconds, self::DECISION_WINDOW_SECONDS, true)
            ? $decisionWindowSeconds
            : self::DEFAULT_DECISION_WINDOW_SECONDS;
    }

    public function hasDecisionTimer(): bool
    {
        return $this->decisionWindowSeconds() > self::DISABLED_DECISION_WINDOW_SECONDS;
    }

    /**
     * @return array{decision_deadline_at: Carbon|null, decision_remaining_seconds: int|null}
     */
    public function nextDecisionTimerAttributes(?Carbon $now = null): array
    {
        if (! $this->hasDecisionTimer()) {
            return [
                'decision_deadline_at' => null,
                'decision_remaining_seconds' => null,
            ];
        }

        if ($this->status === MultiplayerLobbyStatusEnum::Started) {
            return [
                'decision_deadline_at' => ($now ?? now())->copy()->addSeconds($this->decisionWindowSeconds()),
                'decision_remaining_seconds' => null,
            ];
        }

        return [
            'decision_deadline_at' => null,
            'decision_remaining_seconds' => $this->decisionWindowSeconds(),
        ];
    }

    public function choicesEnabled(): bool
    {
        return (bool) ($this->settings['choices_enabled'] ?? true);
    }

    public function customInputEnabled(): bool
    {
        return $this->mode === MultiplayerLobbyModeEnum::Roulette
            && (bool) ($this->settings['custom_input_enabled'] ?? false);
    }

    public function voiceOverEnabled(): bool
    {
        return (bool) ($this->settings['voice_over_enabled'] ?? true);
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
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
            'decision_deadline_at' => 'datetime',
            'decision_remaining_seconds' => 'integer',
        ];
    }
}
