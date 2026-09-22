<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerLobbySettingsUpdated;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Models\MultiplayerLobby;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateMultiplayerLobbySettingsAction
{
    /**
     * @param  array{choices_enabled?: bool, custom_input_enabled?: bool, voice_over_enabled?: bool, decision_window_seconds: int}  $settings
     */
    public function handle(User $user, MultiplayerLobby $lobby, array $settings): MultiplayerLobby
    {
        $settingsChanged = false;

        $lobby = DB::transaction(function () use ($user, $lobby, $settings, &$settingsChanged): MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());

            if ($lockedLobby->host_user_id !== $user->id) {
                throw MultiplayerLobbyStateException::onlyHostCanUpdateSettings();
            }

            if ($lockedLobby->status !== MultiplayerLobbyStatusEnum::Waiting) {
                throw MultiplayerLobbyStateException::isNotWaiting();
            }

            $currentSettings = $lockedLobby->settings;
            $isRoulette = $lockedLobby->mode === MultiplayerLobbyModeEnum::Roulette;
            $updatedSettings = [
                ...$currentSettings,
                'choices_enabled' => array_key_exists('choices_enabled', $settings)
                    ? (bool) $settings['choices_enabled']
                    : (bool) ($currentSettings['choices_enabled'] ?? true),
                'custom_input_enabled' => $isRoulette && (bool) ($settings['custom_input_enabled'] ?? false),
                'voice_over_enabled' => (bool) ($settings['voice_over_enabled'] ?? true),
                'power_ups_per_user' => (int) ($currentSettings['power_ups_per_user'] ?? 10),
                'decision_window_seconds' => (int) $settings['decision_window_seconds'],
            ];

            if ($currentSettings !== $updatedSettings) {
                $lockedLobby->update(['settings' => $updatedSettings]);
                $settingsChanged = true;
            }

            $lockedLobby->update(['last_activity_at' => now()]);

            return $lockedLobby->load(['host', 'members.user']);
        });

        if ($settingsChanged) {
            MultiplayerLobbySettingsUpdated::dispatch($lobby);
        }

        return $lobby;
    }
}
