<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyMemberRoleEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerLobbyCreated;
use App\Models\MultiplayerLobby;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateMultiplayerLobbyAction
{
    private const int ROOM_CODE_ATTEMPTS = 5;

    /**
     * @param  array{custom_input_enabled?: bool, voice_over_enabled?: bool, decision_window_seconds: int}  $settings
     */
    public function handle(
        User $host,
        Story $story,
        MultiplayerLobbyModeEnum $mode,
        int $maxPlayers,
        array $settings,
    ): MultiplayerLobby {
        $settings = $this->normalizeSettings($mode, $settings);

        for ($attempt = 1; $attempt <= self::ROOM_CODE_ATTEMPTS; $attempt++) {
            try {
                $lobby = DB::transaction(function () use ($host, $story, $mode, $maxPlayers, $settings): MultiplayerLobby {
                    $lobby = MultiplayerLobby::query()->create([
                        'story_id' => $story->id,
                        'host_user_id' => $host->id,
                        'room_code' => $this->generateRoomCode(),
                        'mode' => $mode,
                        'status' => MultiplayerLobbyStatusEnum::Waiting,
                        'max_players' => $maxPlayers,
                        'settings' => $settings,
                        'last_activity_at' => now(),
                    ]);

                    $lobby->members()->create([
                        'user_id' => $host->id,
                        'role' => MultiplayerLobbyMemberRoleEnum::Host,
                        'joined_at' => now(),
                        'last_seen_at' => now(),
                    ]);

                    return $lobby->load(['host', 'members.user']);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === self::ROOM_CODE_ATTEMPTS) {
                    throw $exception;
                }

                continue;
            }

            MultiplayerLobbyCreated::dispatch($lobby);

            return $lobby;
        }

        throw new RuntimeException('Could not generate a unique multiplayer room code.');
    }

    /**
     * @param  array{custom_input_enabled?: bool, voice_over_enabled?: bool, decision_window_seconds: int}  $settings
     * @return array{custom_input_enabled: bool, voice_over_enabled: bool, power_ups_per_user: int, decision_window_seconds: int}
     */
    private function normalizeSettings(MultiplayerLobbyModeEnum $mode, array $settings): array
    {
        $isRoulette = $mode === MultiplayerLobbyModeEnum::Roulette;

        return [
            'custom_input_enabled' => $isRoulette && (bool) ($settings['custom_input_enabled'] ?? false),
            'voice_over_enabled' => (bool) ($settings['voice_over_enabled'] ?? true),
            'power_ups_per_user' => 10,
            'decision_window_seconds' => $settings['decision_window_seconds'],
        ];
    }

    private function generateRoomCode(): string
    {
        return 'TRS-'.Str::upper(Str::random(6));
    }
}
