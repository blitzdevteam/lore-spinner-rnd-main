<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\PlayerTokenUsage;
use App\Models\PlayerVoiceUsage;

/**
 * Player-facing usage, deliberately expressed in turns and generated audio
 * duration rather than provider tokens or characters.
 */
final class PlayerGameplayUsageSummary
{
    /**
     * @return array{turns:int, voice_duration_ms:int}
     */
    public function forGame(string $gameId): array
    {
        return $this->summarize('game_id', $gameId);
    }

    /**
     * @return array{turns:int, voice_duration_ms:int}
     */
    public function forUser(int $userId): array
    {
        return $this->summarize('user_id', $userId);
    }

    /**
     * The durable ledgers intentionally outlive a game reset, whereas prompt
     * rows are cleared. A turn ledger entry is recorded only after a player's
     * action successfully produces its next narration.
     *
     * @param  'game_id'|'user_id'  $column
     * @return array{turns:int, voice_duration_ms:int}
     */
    private function summarize(string $column, string|int $value): array
    {
        return [
            'turns' => PlayerTokenUsage::query()
                ->where($column, $value)
                ->where('kind', PlayerTokenUsage::KIND_TURN)
                ->count(),
            'voice_duration_ms' => (int) PlayerVoiceUsage::query()
                ->where($column, $value)
                ->sum('audio_duration_ms'),
        ];
    }
}
