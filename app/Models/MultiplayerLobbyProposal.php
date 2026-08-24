<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string $id
 * @property string $lobby_id
 * @property string $source_prompt_id
 * @property string $member_id
 * @property string $text
 * @property Carbon|null $selected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MultiplayerLobby $lobby
 * @property-read Prompt $sourcePrompt
 * @property-read MultiplayerLobbyMember $member
 */
final class MultiplayerLobbyProposal extends Model
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
     * @return BelongsTo<Prompt, $this>
     */
    public function sourcePrompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'source_prompt_id');
    }

    /**
     * @return BelongsTo<MultiplayerLobbyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(MultiplayerLobbyMember::class, 'member_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'selected_at' => 'datetime',
        ];
    }
}
