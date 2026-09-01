<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MultiplayerLobby>
 */
final class MultiplayerLobbyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'host_user_id' => User::factory(),
            'room_code' => 'TRS-'.Str::upper(Str::random(6)),
            'mode' => MultiplayerLobbyModeEnum::Arbiter,
            'status' => MultiplayerLobbyStatusEnum::Waiting,
            'max_players' => 4,
            'settings' => [
                'custom_input_enabled' => true,
                'voice_over_enabled' => false,
                'power_ups_per_user' => 10,
                'decision_window_seconds' => 20,
            ],
        ];
    }

    public function roulette(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => MultiplayerLobbyModeEnum::Roulette,
        ]);
    }
}
