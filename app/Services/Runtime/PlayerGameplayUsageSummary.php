<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\PlayerTokenUsage;
use App\Models\PlayerVoiceUsage;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Builder;

/**
 * Player-facing usage, deliberately expressed in turns and generated audio
 * duration rather than provider tokens or characters.
 */
final class PlayerGameplayUsageSummary
{
    /**
     * @return array{turns:int, voice_duration_ms:int}
     */
    public function forGame(string $gameId, int $userId): array
    {
        return [
            'turns' => Prompt::query()
                ->where('game_id', $gameId)
                ->whereNotNull('prompt')
                ->whereHas('game', fn (Builder $game): Builder => $game->where('user_id', $userId))
                ->count(),
            'voice_duration_ms' => (int) PlayerVoiceUsage::query()
                ->where('game_id', $gameId)
                ->where('user_id', $userId)
                ->sum('audio_duration_ms'),
        ];
    }

    /**
     * @return array{turns:int, voice_duration_ms:int}
     */
    public function forUser(int $userId): array
    {
        return $this->summarizeForUser($userId);
    }

    /**
     * The durable ledgers intentionally outlive a game reset, so this is the
     * account-wide total across all games and completed story cycles.
     *
     * @return array{turns:int, voice_duration_ms:int}
     */
    private function summarizeForUser(int $userId): array
    {
        return [
            'turns' => PlayerTokenUsage::query()
                ->where('user_id', $userId)
                ->where('kind', PlayerTokenUsage::KIND_TURN)
                ->count(),
            'voice_duration_ms' => (int) PlayerVoiceUsage::query()
                ->where('user_id', $userId)
                ->sum('audio_duration_ms'),
        ];
    }
}
