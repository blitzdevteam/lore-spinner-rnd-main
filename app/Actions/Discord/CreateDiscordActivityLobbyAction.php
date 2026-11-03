<?php

declare(strict_types=1);

namespace App\Actions\Discord;

use App\Actions\Multiplayer\CreateMultiplayerLobbyAction;
use App\Actions\Multiplayer\JoinMultiplayerLobbyAction;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Models\MultiplayerLobby;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class CreateDiscordActivityLobbyAction
{
    private const int LOCK_SECONDS = 10;

    public function __construct(
        private CreateMultiplayerLobbyAction $createMultiplayerLobby,
        private JoinMultiplayerLobbyAction $joinMultiplayerLobby,
    ) {}

    /**
     * Open the voice channel's lobby, or join the one another player opened first.
     */
    public function handle(User $host, string $instanceId, Story $story, MultiplayerLobbyModeEnum $mode): MultiplayerLobby
    {
        /** @var MultiplayerLobby */
        return Cache::lock('discord-activity-lobby:'.$instanceId, self::LOCK_SECONDS)
            ->block(self::LOCK_SECONDS, function () use ($host, $instanceId, $story, $mode): MultiplayerLobby {
                $existing = MultiplayerLobby::query()
                    ->where('discord_instance_id', $instanceId)
                    ->first();

                if ($existing !== null) {
                    return $this->joinMultiplayerLobby->handle($host, $existing);
                }

                $lobby = $this->createMultiplayerLobby->handle(
                    $host,
                    $story,
                    $mode,
                    MultiplayerLobby::MAX_PLAYERS,
                    [
                        'choices_enabled' => true,
                        'custom_input_enabled' => false,
                        'voice_over_enabled' => true,
                        'decision_window_seconds' => MultiplayerLobby::DEFAULT_DECISION_WINDOW_SECONDS,
                    ],
                );

                $lobby->update(['discord_instance_id' => $instanceId]);

                return $lobby;
            });
    }
}
