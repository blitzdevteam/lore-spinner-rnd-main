<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Multiplayer\MultiplayerLobbyMemberRoleEnum;
use App\Models\MultiplayerLobby;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MultiplayerLobbyMember>
 */
final class MultiplayerLobbyMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lobby_id' => MultiplayerLobby::factory(),
            'user_id' => User::factory(),
            'role' => MultiplayerLobbyMemberRoleEnum::Player,
            'is_ready' => false,
            'joined_at' => now(),
        ];
    }

    public function host(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MultiplayerLobbyMemberRoleEnum::Host,
        ]);
    }
}
