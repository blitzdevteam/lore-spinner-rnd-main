<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordActivityRoom;
use App\Models\DiscordActivityRoomMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordActivityRoomMember>
 */
final class DiscordActivityRoomMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => DiscordActivityRoom::factory(),
            'discord_user_id' => fake()->unique()->numerify('##################'),
            'username' => fake()->userName(),
            'global_name' => fake()->name(),
            'last_seen_at' => now(),
        ];
    }
}
